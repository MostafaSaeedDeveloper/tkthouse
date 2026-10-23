<?php

namespace Tests\Feature;

use App\Http\Middleware\EnsureAdminPanelAccess;
use App\Models\Customer;
use App\Models\Order;
use App\Models\User;
use App\Support\SystemSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/** Bell notifications: who is told about what, and that nobody can read anybody else's. */
class AdminNotificationsTest extends TestCase
{
    use RefreshDatabase;

    private Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(EnsureAdminPanelAccess::class);
        $this->customer = Customer::create(['first_name' => 'Nour', 'last_name' => 'Hassan', 'email' => 'nour@example.test', 'phone' => '0100000000']);
    }

    private function staff(array $perms, array $attrs = []): User
    {
        foreach ($perms as $p) {
            Permission::findOrCreate($p, 'web');
        }

        return tap(User::factory()->create($attrs))->givePermissionTo($perms);
    }

    private function order(string $status = 'pending_payment', ?User $assignee = null, float $total = 250): Order
    {
        return Order::create([
            'customer_id' => $this->customer->id, 'order_number' => (string) random_int(1000000000, 9999999999), 'status' => $status, 'payment_method' => 'cash',
            'subtotal_amount' => $total, 'total_amount' => $total, 'assigned_user_id' => $assignee?->id,
        ]);
    }

    public function test_a_new_order_notifies_staff_who_manage_orders_and_nobody_else(): void
    {
        $manager = $this->staff(['orders.view', 'orders.update']);
        $viewer = $this->staff(['orders.view']);              // can see orders but does not act on them
        $other = $this->staff(['events.view']);               // no order access
        $customerUser = User::factory()->create();            // not staff

        $order = $this->order();

        $this->assertSame(1, $manager->notifications()->count());
        $n = $manager->notifications()->first();
        $this->assertSame('order.created', $n->data['type']);
        $this->assertStringContainsString('New order', $n->data['title']);
        $this->assertStringContainsString('250.00 EGP', $n->data['body']);
        $this->assertSame(route('admin.orders.show', $order, false), $n->data['url']);   // relative, independent of APP_URL
        $this->assertSame(0, $viewer->notifications()->count());
        $this->assertSame(0, $other->notifications()->count());
        $this->assertSame(0, $customerUser->notifications()->count());
    }

    public function test_orders_waiting_for_approval_and_paid_orders_have_their_own_notifications(): void
    {
        $manager = $this->staff(['orders.view', 'orders.update']);

        $approval = $this->order('pending_approval');
        $this->assertSame('order.needs_approval', $manager->notifications()->latest()->first()->data['type']);

        $paid = $this->order('pending_payment');
        $paid->update(['status' => 'paid', 'paid_at' => now()]);
        $this->assertTrue($manager->notifications()->get()->contains(fn ($n) => $n->data['type'] === 'order.paid' && str_contains($n->data['title'], 'Payment received')));
    }

    public function test_assigning_an_order_sends_no_notification(): void
    {
        $agent = $this->staff(['orders.view', 'orders.update']);
        $manager = $this->staff(['orders.view', 'orders.update']);
        $order = $this->order();
        $agent->notifications()->delete();
        $manager->notifications()->delete();

        $this->actingAs($manager);
        $order->update(['assigned_user_id' => $agent->id]);

        $this->assertSame(0, $agent->notifications()->count());
        $this->assertSame(0, $manager->notifications()->count());
    }

    public function test_a_user_with_the_bell_switched_off_gets_no_notifications(): void
    {
        $off = $this->staff(['orders.view', 'orders.update']);
        $off->forceFill(['notifications_enabled' => false])->save();
        $on = $this->staff(['orders.view', 'orders.update']);

        $this->order();

        $this->assertSame(0, $off->notifications()->count());
        $this->assertSame(1, $on->notifications()->count());
    }

    public function test_with_order_assignment_on_an_agent_is_only_told_about_orders_they_can_see(): void
    {
        SystemSettings::set('order_assignment_enabled', true);
        Role::findOrCreate('Order Agent', 'web');
        $a = $this->staff(['orders.view', 'orders.update', 'orders.view_assigned', 'orders.manage_assigned']);
        $b = $this->staff(['orders.view', 'orders.update', 'orders.view_assigned', 'orders.manage_assigned']);

        $this->order('pending_payment', $a);

        $this->assertSame(1, $a->notifications()->count(), 'the assigned agent hears about their order');
        $this->assertSame(0, $b->notifications()->count(), "another agent never hears about someone else's order");
    }

    public function test_the_feed_open_and_read_endpoints_only_touch_the_signed_in_users_notifications(): void
    {
        $me = $this->staff(['orders.view', 'orders.update']);
        $you = $this->staff(['orders.view', 'orders.update']);
        $order = $this->order();
        $mine = $me->notifications()->first();
        $yours = $you->notifications()->first();

        $feed = $this->actingAs($me)->getJson(route('admin.notifications.feed'))->assertOk()->json();
        $this->assertSame(1, $feed['unread']);
        $this->assertStringContainsString('New order', $feed['html']);

        // someone else's notification is a 404, and stays unread
        $this->actingAs($me)->get(route('admin.notifications.open', $yours->id))->assertNotFound();
        $this->actingAs($me)->post(route('admin.notifications.read', $yours->id))->assertNotFound();
        $this->assertNull($yours->fresh()->read_at);

        // opening mine marks it read and goes to the order
        $this->actingAs($me)->get(route('admin.notifications.open', $mine->id))->assertRedirect(route('admin.orders.show', $order));
        $this->assertNotNull($mine->fresh()->read_at);
        $this->assertSame(0, $this->actingAs($me)->getJson(route('admin.notifications.feed'))->json('unread'));
    }

    public function test_mark_all_read_and_the_full_page(): void
    {
        $me = $this->staff(['orders.view', 'orders.update', 'dashboard.view']);
        $this->order(); $this->order();
        $this->assertSame(2, $me->unreadNotifications()->count());

        $this->actingAs($me)->get(route('admin.notifications.index'))->assertOk()->assertSee('Notifications')->assertSee('New order');
        $this->actingAs($me)->get(route('admin.notifications.index', ['unread' => 1]))->assertOk()->assertSee('New order');

        $this->actingAs($me)->postJson(route('admin.notifications.read-all'))->assertOk();
        $this->assertSame(0, $me->unreadNotifications()->count());
        $this->actingAs($me)->get(route('admin.notifications.index', ['unread' => 1]))->assertOk()->assertSee("You're all caught up", false);
    }

    public function test_the_bell_with_its_unread_badge_is_on_every_admin_page(): void
    {
        $me = $this->staff(['orders.view', 'orders.update', 'dashboard.view']);
        $this->order();

        $html = $this->actingAs($me)->get(route('admin.dashboard'))->assertOk()->getContent();

        $this->assertStringContainsString('data-a-notif', $html);
        $this->assertMatchesRegularExpression('#data-notif-badge\s*>1<#', $html);
        $this->assertStringContainsString('admin-v2/notifications.js', $html);
    }

    public function test_a_failure_while_notifying_never_breaks_the_order(): void
    {
        $this->staff(['orders.view', 'orders.update']);
        \Illuminate\Support\Facades\Schema::drop('notifications');   // notifying will throw

        $order = $this->order();                                      // ...and the order is still created
        $this->assertNotNull($order->fresh());
    }
}
