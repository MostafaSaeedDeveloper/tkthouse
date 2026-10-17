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

    public function test_agent_dashboard_and_global_screens_are_isolated(): void
    {
        $this->on();
        $a = $this->agent(); $b = $this->agent();
        $this->order($a, 'paid', 100); $this->order($b, 'paid', 999);
        $this->order($a, 'pending_payment');

        $page = $this->actingAs($a)->get(route('admin.dashboard'))->assertOk()->assertSee('My dashboard');
        $page->assertDontSee('999.00');
        foreach (['admin.reports.index', 'admin.guest-list.index'] as $route) {
            $this->actingAs($a)->get(route($route))->assertForbidden();
        }
        $ev = $this->makeEvent();
        $this->actingAs($a)->get(route('admin.events.dashboard', $ev))->assertForbidden();
        $this->actingAs($this->manager())->get(route('admin.dashboard'))->assertOk()->assertDontSee('My dashboard');
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
}
