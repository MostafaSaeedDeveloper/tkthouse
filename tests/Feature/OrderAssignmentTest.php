<?php

namespace Tests\Feature;

use App\Http\Middleware\EnsureAdminPanelAccess;
use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderAssignment;
use App\Models\Ticket;
use App\Models\User;
use App\Orders\AgentReport;
use App\Orders\OrderAccess;
use App\Orders\OrderAssignmentService;
use App\Support\SystemSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Http;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\Support\BuildsOrders;
use Tests\TestCase;

class OrderAssignmentTest extends TestCase
{
    use BuildsOrders, RefreshDatabase;

    private Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(EnsureAdminPanelAccess::class);
        Mail::fake();
        Http::preventStrayRequests();
        $this->customer = Customer::create(['first_name' => 'Cus', 'last_name' => 'Tomer', 'email' => 'c@example.test', 'phone' => '0100']);
    }

    private function user(string $role = null, array $perms = [], array $flags = ['can_receive_orders' => true]): User
    {
        $u = User::factory()->create();
        $u->forceFill($flags)->save();
        if ($role) {
            $u->assignRole($role);
        }
        foreach ($perms as $p) {
            Permission::findOrCreate($p, 'web');
        }
        $u->givePermissionTo($perms);

        return $u->fresh();
    }

    private function agent(array $flags = ['can_receive_orders' => true]): User { return $this->user('Order Agent', [], $flags); }
    private function manager(): User { return $this->user('Order Manager', ['orders.reroute_payment_dummy'], ['can_receive_orders' => false]); }
    private function superAdmin(): User { Role::findOrCreate('super_admin', 'web'); return $this->user('super_admin', ['orders.view', 'dashboard.view'], ['can_receive_orders' => false]); }

    private function order(?User $assignee = null, string $status = 'pending_payment', float $total = 100): Order
    {
        return Order::create([
            'customer_id' => $this->customer->id, 'order_number' => (string) random_int(1000000000, 9999999999), 'status' => $status,
            'payment_method' => 'cash', 'total_amount' => $total, 'assigned_user_id' => $assignee?->id,
            'assigned_at' => $assignee ? now() : null, 'paid_at' => $status === 'paid' ? now() : null,
        ]);
    }

    private function on(): void { SystemSettings::set('order_assignment_enabled', true); }

    public function test_migration_installs_roles_and_permissions_and_feature_defaults_off(): void
    {
        $this->assertFalse(OrderAccess::enabled());
        $this->assertTrue(Role::where('name', 'Order Manager')->first()->hasPermissionTo('orders.assign'));
        $this->assertTrue(Role::where('name', 'Order Agent')->first()->hasPermissionTo('orders.view_assigned'));
        $this->assertFalse(Role::where('name', 'Order Agent')->first()->hasPermissionTo('orders.assign'));
    }

    public function test_disabled_feature_changes_nothing_for_existing_staff(): void
    {
        $a = $this->agent();
        $mine = $this->order($a);
        $other = $this->order();
        $this->actingAs($a)->get(route('admin.orders.index'))->assertOk()->assertSee($mine->order_number)->assertSee($other->order_number);
        $this->actingAs($a)->get(route('admin.orders.show', $other))->assertOk();
        $this->actingAs($a)->get(route('admin.dashboard'))->assertOk()->assertDontSee('My dashboard');
        $this->actingAs($a)->get(route('admin.reports.agents'))->assertNotFound();
    }

    public function test_agent_sees_only_assigned_orders_everywhere_when_enabled(): void
    {
        $this->on();
        $a = $this->agent(); $b = $this->agent();
        $mine = $this->order($a); $theirs = $this->order($b); $queue = $this->order();

        $this->actingAs($a)->get(route('admin.orders.index'))->assertOk()
            ->assertSee($mine->order_number)->assertDontSee($theirs->order_number)->assertDontSee($queue->order_number);
        $this->actingAs($a)->get(route('admin.orders.show', $mine))->assertOk();
        foreach ([$theirs, $queue] as $o) {
            $this->actingAs($a)->get(route('admin.orders.show', $o))->assertNotFound();
            $this->actingAs($a)->get(route('admin.orders.edit', $o))->assertNotFound();
            $this->actingAs($a)->post(route('admin.orders.status.update', $o), ['status' => 'canceled'])->assertNotFound();
            $this->actingAs($a)->post(route('admin.orders.notes.store', $o), ['note' => 'x'])->assertNotFound();
            $this->actingAs($a)->delete(route('admin.orders.destroy', $o))->assertStatus(403);
            $this->assertSame('pending_payment', $o->fresh()->status);
        }
    }

    public function test_view_unassigned_permission_shows_the_queue(): void
    {
        $this->on();
        $a = $this->agent();
        $queue = $this->order();
        $this->actingAs($a)->get(route('admin.orders.show', $queue))->assertNotFound();
        $a->givePermissionTo('orders.view_unassigned');
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
        $this->actingAs($a->fresh())->get(route('admin.orders.show', $queue))->assertOk();
    }

    public function test_user_with_only_orders_view_sees_nothing_when_enabled_fail_closed(): void
    {
        $this->on();
        $u = $this->user(null, ['orders.view'], []);
        $o = $this->order();
        $this->actingAs($u)->get(route('admin.orders.index'))->assertOk()->assertDontSee($o->order_number);
        $this->actingAs($u)->get(route('admin.orders.show', $o))->assertNotFound();
        $this->actingAs($u)->get(route('admin.dashboard'))->assertForbidden();
    }

    public function test_manager_and_super_admin_see_everything(): void
    {
        $this->on();
        $a = $this->agent(); $queue = $this->order(); $mine = $this->order($a);
        foreach ([$this->manager(), $this->superAdmin()] as $u) {
            $this->actingAs($u)->get(route('admin.orders.index'))->assertOk()->assertSee($queue->order_number)->assertSee($mine->order_number);
            $this->actingAs($u)->get(route('admin.orders.show', $queue))->assertOk();
        }
    }

    public function test_agent_needs_manage_assigned_to_act_and_only_on_own_order(): void
    {
        $this->on();
        $a = $this->agent();
        $mine = $this->order($a, 'pending_approval');
        $this->actingAs($a)->post(route('admin.orders.notes.store', $mine), ['note' => 'called customer'])->assertRedirect();
        $a->revokePermissionTo('orders.manage_assigned'); // role grants it; remove via role for the test
        Role::findByName('Order Agent')->revokePermissionTo('orders.manage_assigned');
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
        $this->actingAs($a->fresh())->post(route('admin.orders.notes.store', $mine), ['note' => 'again'])->assertForbidden();
    }

    public function test_assign_and_reassign_need_their_permissions_and_write_history(): void
    {
        $this->on();
        $a = $this->agent(); $b = $this->agent();
        $m = $this->manager();
        $o = $this->order();

        $this->actingAs($a)->post(route('admin.orders.assign', $o), ['assignee_id' => $b->id])->assertNotFound(); // cannot even see it
        $mine = $this->order($a);
        $this->actingAs($a)->post(route('admin.orders.assign', $mine), ['assignee_id' => $b->id])->assertForbidden();

        $this->actingAs($m)->post(route('admin.orders.assign', $o), ['assignee_id' => $a->id, 'reason' => 'first'])->assertRedirect();
        $o->refresh();
        $this->assertSame($a->id, $o->assigned_user_id);
        $this->assertSame($m->id, $o->assigned_by_user_id);

        $this->actingAs($m)->post(route('admin.orders.assign', $o), ['assignee_id' => $b->id, 'reason' => 'workload'])->assertRedirect();
        $this->assertSame($b->id, $o->fresh()->assigned_user_id);

        $h = OrderAssignment::where('order_id', $o->id)->orderBy('id')->get();
        $this->assertCount(2, $h);
        $this->assertSame([null, $a->id], [$h[0]->from_user_id, $h[0]->to_user_id]);
        $this->assertSame([$a->id, $b->id], [$h[1]->from_user_id, $h[1]->to_user_id]);
        $this->assertSame('workload', $h[1]->reason);

        // reassign permission is separate
        Role::findByName('Order Manager')->revokePermissionTo('orders.reassign');
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
        $this->actingAs($m->fresh())->post(route('admin.orders.assign', $o), ['assignee_id' => $a->id])->assertForbidden();
        $this->assertSame($b->id, $o->fresh()->assigned_user_id);
    }

    public function test_cannot_assign_to_inactive_or_non_receiving_users(): void
    {
        $this->on();
        $m = $this->manager();
        $o = $this->order();
        $inactive = $this->agent(['can_receive_orders' => true, 'is_active' => false]);
        $optedOut = $this->agent(['can_receive_orders' => false]);
        $nobody = $this->user(null, ['orders.view'], ['can_receive_orders' => true]);

        foreach ([$inactive, $optedOut, $nobody] as $bad) {
            $this->actingAs($m)->post(route('admin.orders.assign', $o), ['assignee_id' => $bad->id])->assertSessionHasErrors('assignee');
        }
        $this->assertNull($o->fresh()->assigned_user_id);
    }

    public function test_bulk_assignment_skips_what_the_actor_may_not_touch(): void
    {
        $this->on();
        $m = $this->manager(); $a = $this->agent();
        $o1 = $this->order(); $o2 = $this->order();
        $this->actingAs($m)->post(route('admin.orders.assign.bulk'), ['order_ids' => [$o1->id, $o2->id], 'assignee_id' => $a->id])->assertRedirect();
        $this->assertSame($a->id, $o1->fresh()->assigned_user_id);
        $this->assertSame($a->id, $o2->fresh()->assigned_user_id);
        $this->assertSame(2, OrderAssignment::count());
    }

    public function test_round_robin_cycles_eligible_users_and_skips_inactive_and_unqualified(): void
    {
        $this->on();
        SystemSettings::set('order_assignment_mode', 'round_robin');
        $a = $this->agent(); $b = $this->agent(); $c = $this->agent();
        $this->agent(['can_receive_orders' => true, 'is_active' => false]);
        $this->agent(['can_receive_orders' => false]);
        $svc = app(OrderAssignmentService::class);

        $got = [];
        for ($i = 0; $i < 6; $i++) {
            $o = $this->order();
            $svc->assignOnCreate($o);
            $got[] = $o->fresh()->assigned_user_id;
        }
        $this->assertSame([$a->id, $b->id, $c->id, $a->id, $b->id, $c->id], $got);
        $this->assertSame('round_robin', OrderAssignment::latest('id')->first()->source);
    }

    public function test_round_robin_without_eligible_users_leaves_order_in_queue_and_never_double_assigns(): void
    {
        $this->on();
        SystemSettings::set('order_assignment_mode', 'round_robin');
        $svc = app(OrderAssignmentService::class);
        $o = $this->order();
        $svc->assignOnCreate($o);
        $this->assertNull($o->fresh()->assigned_user_id);

        $a = $this->agent(); $b = $this->agent();
        $svc->assignOnCreate($o);
        $first = $o->fresh()->assigned_user_id;
        $this->assertNotNull($first);
        $svc->assignOnCreate($o->fresh()); // second call (e.g. a retry) must not move it
        $svc->assignOnCreate($o);          // stale instance too
        $this->assertSame($first, $o->fresh()->assigned_user_id);
        $this->assertSame(1, OrderAssignment::where('order_id', $o->id)->count());
    }

    public function test_default_assignee_mode_and_ineligible_default_falls_to_queue(): void
    {
        $this->on();
        SystemSettings::set('order_assignment_mode', 'default_assignee');
        $a = $this->agent(); $other = $this->agent();
        SystemSettings::set('order_default_assignee_id', $a->id);
        $svc = app(OrderAssignmentService::class);

        $o = $this->order(); $svc->assignOnCreate($o);
        $this->assertSame($a->id, $o->fresh()->assigned_user_id);

        $a->forceFill(['is_active' => false])->save();
        $o2 = $this->order(); $svc->assignOnCreate($o2);
        $this->assertNull($o2->fresh()->assigned_user_id, 'never falls through to someone else');
    }

    public function test_manual_mode_and_disabled_feature_never_auto_assign(): void
    {
        $this->agent();
        $svc = app(OrderAssignmentService::class);
        $o = $this->order(); $svc->assignOnCreate($o);
        $this->assertNull($o->fresh()->assigned_user_id);
        $this->on();
        $o2 = $this->order(); $svc->assignOnCreate($o2);
        $this->assertNull($o2->fresh()->assigned_user_id);
    }

    public function test_agent_sees_the_ordinary_dashboard_with_only_their_numbers_and_global_screens_are_isolated(): void
    {
        $this->on();
        $a = $this->agent(); $b = $this->agent();
        $this->order($a, 'paid', 100); $this->order($b, 'paid', 999);
        $this->order($a, 'pending_payment');

        // The agent gets the ordinary dashboard (nothing says it is a special one), computed from their own orders only.
        $page = $this->actingAs($a)->get(route('admin.dashboard'))->assertOk()->assertDontSee('My dashboard')->assertSee('Total sales');
        $page->assertSee('100 EGP')->assertDontSee('999')->assertDontSee('1,099');
        foreach (['admin.reports.index', 'admin.guest-list.index'] as $route) {
            $this->actingAs($a)->get(route($route))->assertForbidden();
        }
        $ev = $this->makeEvent();
        $this->actingAs($a)->get(route('admin.events.dashboard', $ev))->assertForbidden();
        $this->actingAs($this->manager())->get(route('admin.dashboard'))->assertOk()->assertSee('1,099 EGP');
    }

    public function test_the_ordinary_dashboard_only_shows_the_events_the_agent_works_in(): void
    {
        $this->on();
        $a = $this->agent(); $b = $this->agent();
        $mine = $this->makeEvent(['name' => 'Agent Own Night']);
        $theirs = $this->makeEvent(['name' => 'Someone Elses Gala']);
        foreach ([[$a, $mine], [$b, $theirs]] as [$agent, $event]) {
            $o = $this->order($agent, 'paid', 100);
            \App\Models\OrderItem::create(['order_id' => $o->id, 'event_id' => $event->id, 'ticket_name' => $event->name.' - GA', 'ticket_price' => 100, 'quantity' => 1, 'line_total' => 100, 'holder_name' => 'H', 'holder_email' => 'h@example.test']);
        }

        $page = $this->actingAs($a)->get(route('admin.dashboard'))->assertOk();
        $page->assertSee('Agent Own Night')->assertDontSee('Someone Elses Gala')->assertDontSee('My dashboard');
        $this->actingAs($this->manager())->get(route('admin.dashboard'))->assertOk()->assertSee('Agent Own Night')->assertSee('Someone Elses Gala');
    }

    public function test_customers_tickets_and_search_are_limited_to_visible_orders(): void
    {
        $this->on();
        $a = $this->agent(); $b = $this->agent();
        $other = Customer::create(['first_name' => 'Hidden', 'last_name' => 'Person', 'email' => 'h@example.test', 'phone' => '0111']);
        $o1 = $this->order($a);
        $o2 = Order::create(['customer_id' => $other->id, 'order_number' => '2222222222', 'status' => 'paid', 'payment_method' => 'cash', 'total_amount' => 50, 'assigned_user_id' => $b->id]);
        Permission::findOrCreate('attendees.view', 'web'); Permission::findOrCreate('tickets.view', 'web');
        $a->givePermissionTo(['attendees.view', 'tickets.view']);
        $a = $a->fresh();

        $this->actingAs($a)->get(route('admin.customers.index'))->assertOk()->assertSee('Cus')->assertDontSee('Hidden');
        $this->actingAs($a)->get(route('admin.customers.show', $other))->assertNotFound();
        $this->actingAs($a)->get(route('admin.search', ['q' => 'Hidden']))->assertOk()->assertDontSee('h@example.test');
        $this->assertCount(0, $this->actingAs($a)->get(route('admin.search', ['q' => '2222222222']))->viewData('results')['orders']);
        $this->assertCount(1, $this->actingAs($this->manager())->get(route('admin.search', ['q' => '2222222222']))->viewData('results')['orders']);

        $t = Ticket::create(['order_id' => $o2->id, 'name' => 'T', 'ticket_number' => 'TKT-HIDDEN-1', 'holder_name' => 'Hid', 'holder_email' => 'x@example.test', 'status' => 'active', 'price' => 10]);
        $this->actingAs($a)->get(route('admin.tickets.index'))->assertOk()->assertDontSee('TKT-HIDDEN-1');
        $this->actingAs($a)->get(route('admin.tickets.show', $t))->assertNotFound();
        $this->actingAs($a)->get(route('admin.tickets.download', $t))->assertNotFound();
    }

    public function test_sales_attribution_is_frozen_at_payment_and_survives_reassignment(): void
    {
        $this->on();
        $a = $this->agent(); $b = $this->agent(); $m = $this->manager();
        $o = $this->order($a, 'pending_payment', 200);

        // manual status change to paid (admin path)
        $this->actingAs($m)->post(route('admin.orders.status.update', $o), ['status' => 'paid'])->assertRedirect();
        $o->refresh();
        $this->assertSame($a->id, $o->sold_by_user_id);
        $this->assertNotNull($o->sold_at);

        // reassign afterwards: history of the sale does not move
        $this->actingAs($m)->post(route('admin.orders.assign', $o), ['assignee_id' => $b->id])->assertRedirect();
        $o->refresh();
        $this->assertSame($b->id, $o->assigned_user_id);
        $this->assertSame($a->id, $o->sold_by_user_id);

        $report = app(AgentReport::class)->build($m, now()->startOfDay(), now()->endOfDay());
        $rowA = $report['rows']->firstWhere('user_id', $a->id);
        $this->assertEquals(200.0, $rowA['collected']);
        $this->assertSame(0.0, (float) ($report['rows']->firstWhere('user_id', $b->id)['collected'] ?? 0));
    }

    public function test_gateway_settlement_also_records_the_attribution_snapshot(): void
    {
        config(['payments.fake.enabled' => true]);
        $this->makeMethod('fake', 'fake_card');
        $this->on();
        $a = $this->agent();
        $customerUser = User::factory()->create();
        $order = $this->makePendingOrder($customerUser, null, 2, 150);
        $order->forceFill(['assigned_user_id' => $a->id, 'assigned_at' => now()])->save();

        $this->actingAs($customerUser)->get(route('front.orders.payment.gateway', ['order' => $order, 'token' => $order->payment_link_token, 'method' => 'fake_card']))->assertRedirect();
        $attempt = $order->paymentAttempts()->firstOrFail();
        $this->actingAs($customerUser)->post(route('front.payments.fake.complete', $attempt), ['outcome' => 'paid']);

        $order->refresh();
        $this->assertSame('paid', $order->status);
        $this->assertSame($a->id, $order->sold_by_user_id);
    }

    public function test_agent_report_shows_only_own_row_and_manager_sees_unattributed(): void
    {
        $this->on();
        $a = $this->agent(); $b = $this->agent(); $m = $this->manager();
        $this->order($a, 'pending_payment');
        $this->order($b, 'pending_payment');
        $legacy = $this->order(null, 'paid', 70); // paid before attribution existed
        $legacy->forceFill(['sold_by_user_id' => null])->save();

        $page = $this->actingAs($a)->get(route('admin.reports.agents'))->assertOk();
        $page->assertSee($a->name)->assertDontSee($b->name)->assertDontSee('Unattributed sales');

        $mgr = $this->actingAs($m)->get(route('admin.reports.agents'))->assertOk();
        $mgr->assertSee($a->name)->assertSee($b->name)->assertSee('Unattributed sales');
    }

    public function test_disabling_assignment_keeps_assignees_and_history_and_restores_old_visibility(): void
    {
        $this->on();
        $a = $this->agent(); $m = $this->manager();
        $o = $this->order();
        $this->actingAs($m)->post(route('admin.orders.assign', $o), ['assignee_id' => $a->id]);
        $other = $this->order();

        SystemSettings::set('order_assignment_enabled', false);
        $this->assertSame($a->id, $o->fresh()->assigned_user_id);
        $this->assertSame(1, OrderAssignment::count());
        $this->actingAs($a)->get(route('admin.orders.show', $other))->assertOk(); // old behaviour again
        $this->actingAs($m)->post(route('admin.orders.assign', $o), ['assignee_id' => $a->id])->assertForbidden(); // no assigning while off
    }

    public function test_old_orders_without_assignment_are_unassigned_not_broken(): void
    {
        $this->on();
        $m = $this->manager();
        $old = $this->order(null, 'paid', 40);
        $this->actingAs($m)->get(route('admin.orders.index'))->assertOk()->assertSee('Unassigned');
        $this->actingAs($m)->get(route('admin.orders.show', $old))->assertOk()->assertSee('Unassigned');
        $this->actingAs($m)->get(route('admin.orders.index', ['assignee' => 'unassigned']))->assertSee($old->order_number);
    }

    public function test_enabling_is_blocked_until_actor_keeps_access_and_lockout_is_acknowledged(): void
    {
        Permission::findOrCreate('settings.orders', 'web'); Permission::findOrCreate('settings.update', 'web'); Permission::findOrCreate('settings.view', 'web');
        $admin = $this->user(null, ['settings.orders', 'settings.update', 'settings.view', 'orders.view'], []);
        $this->actingAs($admin)->put(route('admin.settings.section.update', 'orders'), ['order_assignment_enabled' => 1, 'order_assignment_mode' => 'manual'])
            ->assertSessionHasErrors('order_assignment_enabled');
        $this->assertFalse(OrderAccess::enabled());

        $admin->givePermissionTo('orders.view_all');
        $staff = $this->user(null, ['orders.view'], []); // would be locked out
        $this->actingAs($admin->fresh())->put(route('admin.settings.section.update', 'orders'), ['order_assignment_enabled' => 1, 'order_assignment_mode' => 'manual'])
            ->assertSessionHasErrors('order_assignment_enabled');
        $this->assertFalse(OrderAccess::enabled());

        $this->actingAs($admin->fresh())->post(route('admin.settings.orders.grant-view-all'))->assertRedirect();
        $this->assertTrue($staff->fresh()->can('orders.view_all'));
        $this->actingAs($admin->fresh())->put(route('admin.settings.section.update', 'orders'), ['order_assignment_enabled' => 1, 'order_assignment_mode' => 'manual'])->assertRedirect();
        $this->assertTrue(OrderAccess::enabled());
    }

    public function test_inactive_staff_cannot_sign_in_or_keep_a_session_and_customers_are_unaffected(): void
    {
        $staff = User::factory()->create(['username' => 'agent1', 'password' => 'secret-pass-1']);
        $staff->assignRole('Order Agent');
        $staff->forceFill(['is_active' => false])->save();

        $this->post('/login', ['username' => 'agent1', 'password' => 'secret-pass-1'])->assertSessionHasErrors('username');
        $this->assertGuest();

        $this->actingAs($staff)->get('/dashboard')->assertRedirect(route('login'));
        $this->assertGuest();

        $customer = User::factory()->create(['username' => 'cust1', 'password' => 'secret-pass-2']);
        $this->post('/login', ['username' => 'cust1', 'password' => 'secret-pass-2'])->assertRedirect();
        $this->assertAuthenticatedAs($customer);
    }

    public function test_deactivation_guards_super_admin_self_and_customer_accounts(): void
    {
        Permission::findOrCreate('users.update', 'web'); Permission::findOrCreate('users.view', 'web');
        $actor = $this->user(null, ['users.update'], []);
        $staff = $this->agent();
        $customer = User::factory()->create();
        $payload = fn (User $u) => ['name' => $u->name, 'username' => $u->username, 'email' => $u->email, 'account_flags' => 1];

        $this->actingAs($actor)->put(route('admin.users.update', $actor), $payload($actor) + ['is_active' => 0])->assertStatus(422);       // self
        $this->actingAs($actor)->put(route('admin.users.update', $customer), $payload($customer) + ['is_active' => 0])->assertStatus(422); // customer
        $this->assertTrue($customer->fresh()->is_active);

        $this->actingAs($actor)->put(route('admin.users.update', $staff), $payload($staff) + ['role' => 'Order Agent'])->assertRedirect(); // omitted checkbox = off
        $this->assertFalse($staff->fresh()->is_active);

        // a partial update without the marker never touches the flags
        \Illuminate\Support\Facades\DB::table('users')->where('id', $staff->id)->update(['is_active' => true]);
        $this->actingAs($actor)->put(route('admin.users.update', $staff), ['name' => $staff->name, 'username' => $staff->username, 'email' => $staff->email, 'role' => 'Order Agent'])->assertRedirect();
        $this->assertTrue($staff->fresh()->is_active);
    }

    /* ------------------------------------------------------------ several agents per order */

    public function test_several_agents_with_the_same_payment_methods_can_share_an_order_and_all_of_them_see_it(): void
    {
        $this->on();
        $service = app(OrderAssignmentService::class);
        $a = $this->agent(); $b = $this->agent(); $c = $this->agent(); $manager = $this->superAdmin();
        $order = $this->order($a);

        $service->assignMany($order, [$a, $b], $manager);

        $order->refresh();
        $this->assertSame($a->id, $order->assigned_user_id, 'the current primary stays primary');
        $this->assertEqualsCanonicalizing([$a->id, $b->id], $order->assigneeIds());
        foreach ([$a, $b] as $agent) {
            $this->actingAs($agent)->get(route('admin.orders.show', $order))->assertOk();
            $this->actingAs($agent)->get(route('admin.orders.index'))->assertOk()->assertSee($order->order_number);
            $this->assertTrue(OrderAccess::canView($order, $agent));
        }
        $this->actingAs($c)->get(route('admin.orders.show', $order))->assertNotFound();
        $this->assertSame(1, OrderAssignment::where('order_id', $order->id)->count(), 'one history row for the change');
    }

    public function test_agents_with_different_payment_methods_cannot_share_an_order(): void
    {
        $this->on();
        $a = $this->agent(); $b = $this->agent(); $c = $this->agent();
        $a->forceFill(['allowed_payment_methods' => ['fake_card', 'fake_wallet']])->save();
        $b->forceFill(['allowed_payment_methods' => ['fake_wallet', 'fake_card']])->save();   // same set, other order => allowed
        $c->forceFill(['allowed_payment_methods' => ['fake_card']])->save();
        $order = $this->order($a);
        $service = app(OrderAssignmentService::class);

        $service->assignMany($order, [$a, $b], $this->superAdmin());
        $this->assertEqualsCanonicalizing([$a->id, $b->id], $order->fresh()->assigneeIds());

        try {
            $service->assignMany($order, [$a, $b, $c], $this->superAdmin());
            $this->fail('different payment methods must be refused');
        } catch (\Illuminate\Validation\ValidationException $e) {
            $this->assertStringContainsString('same payment methods', $e->errors()['assignee'][0]);
        }
        $this->assertEqualsCanonicalizing([$a->id, $b->id], $order->fresh()->assigneeIds(), 'nothing changed');
    }

    public function test_an_agent_with_no_selection_and_one_with_a_selection_differ(): void
    {
        $this->on();
        $all = $this->agent(); $some = $this->agent();
        $some->forceFill(['allowed_payment_methods' => ['fake_card']])->save();

        $this->expectException(\Illuminate\Validation\ValidationException::class);
        app(OrderAssignmentService::class)->assignMany($this->order(), [$all, $some], $this->superAdmin());
    }

    public function test_removing_the_primary_promotes_the_next_agent_and_unassigning_clears_everyone(): void
    {
        $this->on();
        $service = app(OrderAssignmentService::class);
        $a = $this->agent(); $b = $this->agent(); $manager = $this->superAdmin();
        $order = $this->order($a);
        $service->assignMany($order, [$a, $b], $manager);

        $service->assignMany($order, [$b], $manager);
        $this->assertSame($b->id, $order->fresh()->assigned_user_id);
        $this->assertSame([$b->id], $order->fresh()->assigneeIds());
        $this->actingAs($a)->get(route('admin.orders.show', $order))->assertNotFound();

        $service->assignMany($order, [], $manager);
        $this->assertNull($order->fresh()->assigned_user_id);
        $this->assertSame([], $order->fresh()->assigneeIds());
    }

    public function test_the_assign_endpoint_accepts_several_agents_and_the_list_shows_them(): void
    {
        $this->on();
        $a = $this->agent(); $b = $this->agent(); $manager = $this->superAdmin();
        $order = $this->order();

        $this->actingAs($manager)->post(route('admin.orders.assign', $order), ['assignee_ids' => [$b->id, $a->id]])->assertSessionHasNoErrors()->assertSessionHas('success');

        $this->assertSame($b->id, $order->fresh()->assigned_user_id, 'the first agent of the list is the primary');
        $this->assertEqualsCanonicalizing([$a->id, $b->id], $order->fresh()->assigneeIds());
        $this->actingAs($manager)->get(route('admin.orders.index'))->assertOk()->assertSee($a->name)->assertSee($b->name);
        $this->actingAs($manager)->get(route('admin.orders.show', $order))->assertOk()->assertSee('Assigned agents')->assertSee('primary');

        // the old single-agent field still works and replaces everyone
        $this->actingAs($manager)->post(route('admin.orders.assign', $order), ['assignee_id' => $a->id])->assertSessionHasNoErrors();
        $this->assertSame([$a->id], $order->fresh()->assigneeIds());
    }

    public function test_extra_agents_get_the_notification_and_agent_reports_still_count_the_primary_only(): void
    {
        $this->on();
        $a = $this->agent(); $b = $this->agent();
        $order = $this->order($a, 'paid', 200);
        app(OrderAssignmentService::class)->snapshotSale($order);
        $manager = $this->superAdmin();
        $b->notifications()->delete();
        $this->actingAs($manager);
        app(OrderAssignmentService::class)->assignMany($order, [$a, $b], $manager);

        $this->assertSame(['order.assigned'], $b->notifications()->get()->pluck('data.type')->all());
        $this->assertSame($a->id, $order->fresh()->sold_by_user_id);
        $report = app(AgentReport::class)->build($this->superAdmin(), now()->subDay(), now()->addDay());
        $this->assertSame([$a->id], $report['rows']->pluck('user_id')->map(fn ($id) => (int) $id)->all(), 'only the primary agent is a row');
    }

    public function test_bulk_assign_puts_several_agents_on_every_selected_order(): void
    {
        $this->on();
        $a = $this->agent(); $b = $this->agent(); $manager = $this->superAdmin();
        $o1 = $this->order(); $o2 = $this->order($a); $skipMe = $this->order();

        $this->actingAs($manager)->post(route('admin.orders.assign.bulk'), ['order_ids' => [$o1->id, $o2->id], 'assignee_ids' => [$a->id, $b->id]])
            ->assertSessionHasNoErrors()->assertSessionHas('success');

        foreach ([$o1, $o2] as $o) {
            $this->assertEqualsCanonicalizing([$a->id, $b->id], $o->fresh()->assigneeIds());
        }
        $this->assertSame($a->id, $o1->fresh()->assigned_user_id);
        $this->assertSame([], $skipMe->fresh()->assigneeIds());
        $this->actingAs($b)->get(route('admin.orders.show', $o2))->assertOk();

        // the old single-agent field replaces everyone on the selected orders
        $this->actingAs($manager)->post(route('admin.orders.assign.bulk'), ['order_ids' => [$o1->id], 'assignee_id' => $b->id])->assertSessionHasNoErrors();
        $this->assertSame([$b->id], $o1->fresh()->assigneeIds());
    }

    public function test_bulk_assign_refuses_agents_with_different_payment_methods_before_touching_any_order(): void
    {
        $this->on();
        $a = $this->agent(); $b = $this->agent();
        $b->forceFill(['allowed_payment_methods' => ['fake_card']])->save();
        $o1 = $this->order();

        $this->actingAs($this->superAdmin())->post(route('admin.orders.assign.bulk'), ['order_ids' => [$o1->id], 'assignee_ids' => [$a->id, $b->id]])->assertSessionHasErrors('assignee');

        $this->assertSame([], $o1->fresh()->assigneeIds());
    }

    public function test_several_default_assignees_all_land_on_a_new_order_and_the_old_single_setting_still_works(): void
    {
        $this->on();
        SystemSettings::set('order_assignment_mode', 'default_assignee');
        $a = $this->agent(); $b = $this->agent();
        SystemSettings::set('order_default_assignee_ids', [$a->id, $b->id]);
        $svc = app(OrderAssignmentService::class);

        $o = $this->order(); $svc->assignOnCreate($o);
        $this->assertSame($a->id, $o->fresh()->assigned_user_id);
        $this->assertEqualsCanonicalizing([$a->id, $b->id], $o->fresh()->assigneeIds());
        $this->assertSame(1, OrderAssignment::where('order_id', $o->id)->count());

        // an ineligible one is skipped, the others still get the order
        $b->forceFill(['is_active' => false])->save();
        $o2 = $this->order(); $svc->assignOnCreate($o2);
        $this->assertSame([$a->id], $o2->fresh()->assigneeIds());

        // the old single setting is honoured until the new list is saved
        SystemSettings::set('order_default_assignee_ids', null);
        SystemSettings::set('order_default_assignee_id', $a->id);
        $o3 = $this->order(); $svc->assignOnCreate($o3);
        $this->assertSame([$a->id], $o3->fresh()->assigneeIds());
    }

    public function test_a_checkout_reservation_for_the_default_agent_puts_every_default_agent_on_the_order(): void
    {
        $this->on();
        $a = $this->agent(); $b = $this->agent();
        SystemSettings::set('order_default_assignee_ids', [$a->id, $b->id]);
        $o = $this->order();

        app(OrderAssignmentService::class)->assignFromReservation($o, $b, 'default_assignee');

        $this->assertSame($b->id, $o->fresh()->assigned_user_id, 'the agent the customer was shown stays primary');
        $this->assertEqualsCanonicalizing([$a->id, $b->id], $o->fresh()->assigneeIds());
    }

    public function test_default_assignees_with_different_payment_methods_leave_the_order_in_the_queue(): void
    {
        $this->on();
        SystemSettings::set('order_assignment_mode', 'default_assignee');
        $a = $this->agent(); $b = $this->agent();
        $b->forceFill(['allowed_payment_methods' => ['fake_card']])->save();
        SystemSettings::set('order_default_assignee_ids', [$a->id, $b->id]);

        $o = $this->order(); app(OrderAssignmentService::class)->assignOnCreate($o);

        $this->assertNull($o->fresh()->assigned_user_id, 'never a partial or arbitrary assignment');
    }

    public function test_the_settings_page_saves_several_default_assignees_and_refuses_mixed_payment_methods(): void
    {
        $this->on();
        foreach (['settings.update', 'settings.orders'] as $p) {
            Permission::findOrCreate($p, 'web');
        }
        $admin = $this->superAdmin();
        $admin->givePermissionTo(['settings.update', 'settings.orders']);
        $a = $this->agent(); $b = $this->agent(); $c = $this->agent();
        $c->forceFill(['allowed_payment_methods' => ['fake_card']])->save();
        $base = ['order_assignment_mode' => 'default_assignee', 'order_assignment_enabled' => 1];

        $this->actingAs($admin)->put(route('admin.settings.section.update', 'orders'), $base + ['order_default_assignee_ids' => ['', (string) $a->id, (string) $b->id]])->assertSessionHasNoErrors();
        $this->assertSame([$a->id, $b->id], SystemSettings::get('order_default_assignee_ids'));
        $this->actingAs($admin)->get(route('admin.settings.section', 'orders'))->assertOk()->assertSee('Default assignees')->assertSee($a->name);

        $this->actingAs($admin)->put(route('admin.settings.section.update', 'orders'), $base + ['order_default_assignee_ids' => [(string) $a->id, (string) $c->id]])->assertSessionHasErrors('order_default_assignee_ids');
        $this->assertSame([$a->id, $b->id], SystemSettings::get('order_default_assignee_ids'), 'unchanged');

        $this->actingAs($admin)->put(route('admin.settings.section.update', 'orders'), $base + ['order_default_assignee_ids' => ['']])->assertSessionHasNoErrors();
        $this->assertNull(SystemSettings::get('order_default_assignee_ids'));
    }
}
