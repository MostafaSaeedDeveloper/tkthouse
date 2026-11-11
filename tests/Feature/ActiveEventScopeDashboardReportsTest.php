<?php

namespace Tests\Feature;

use App\Http\Middleware\EnsureAdminPanelAccess;
use App\Models\Customer;
use App\Models\Event;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\User;
use App\Support\SystemSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/** Dashboard, Reports and the Agents report open on the Active events, with a way back to every event (like Orders). */
class ActiveEventScopeDashboardReportsTest extends TestCase
{
    use RefreshDatabase;

    private Event $current;
    private Event $old;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(EnsureAdminPanelAccess::class);
        $this->current = $this->event('Current Night', 'active');
        $this->old = $this->event('Old Night', Event::ENDED);
    }

    private function event(string $name, string $status): Event
    {
        return Event::create(['name' => $name, 'event_date' => now()->addDays(3)->toDateString(), 'event_time' => '21:00:00', 'location' => 'Cairo', 'description' => '', 'status' => $status]);
    }

    private function staff(array $perms): User
    {
        foreach ($perms as $p) {
            Permission::findOrCreate($p, 'web');
        }
        $user = User::factory()->create();
        $user->givePermissionTo($perms);

        return $this->actingAs($user)->app->make('auth')->user();
    }

    private function paidOrder(string $number, Event $event, float $amount, ?User $assignee = null): Order
    {
        $customer = Customer::create(['first_name' => 'C'.$number, 'last_name' => 'T', 'email' => $number.'@example.test', 'phone' => '0100'.random_int(1000000, 9999999)]);
        $order = Order::create(['customer_id' => $customer->id, 'order_number' => $number, 'status' => 'paid', 'payment_method' => 'cash', 'paid_at' => now(),
            'subtotal_amount' => $amount, 'total_amount' => $amount, 'assigned_user_id' => $assignee?->id, 'sold_by_user_id' => $assignee?->id]);
        OrderItem::create(['order_id' => $order->id, 'event_id' => $event->id, 'ticket_name' => $event->name.' - GA', 'ticket_price' => $amount,
            'quantity' => 1, 'line_total' => $amount, 'holder_name' => 'H', 'holder_email' => 'h@example.test', 'holder_gender' => 'male']);

        return $order;
    }

    public function test_dashboard_opens_on_the_active_events_and_steps_back(): void
    {
        $this->staff(['dashboard.view', 'orders.view']);
        $this->paidOrder('1001', $this->current, 100);
        $this->paidOrder('1002', $this->old, 5000);

        $default = $this->get(route('admin.dashboard', ['range' => 'all']))->assertOk();
        $this->assertEquals(100.0, $default->viewData('grossRevenue'));
        $this->assertEquals(1, $default->viewData('totalPaidOrders'));
        $default->assertSee('<option value="">Active events</option>', false)->assertSee('<option value="all"', false);

        $all = $this->get(route('admin.dashboard', ['range' => 'all', 'event_id' => 'all']))->assertOk();
        $this->assertEquals(5100.0, $all->viewData('grossRevenue'));
        $this->assertEquals(2, $all->viewData('totalPaidOrders'));

        $one = $this->get(route('admin.dashboard', ['range' => 'all', 'event_id' => $this->old->id]))->assertOk();
        $this->assertEquals(5000.0, $one->viewData('grossRevenue'));
    }

    public function test_dashboard_with_no_active_event_is_not_narrowed(): void
    {
        $this->staff(['dashboard.view', 'orders.view']);
        $this->current->update(['status' => Event::ENDED]);
        $this->paidOrder('1001', $this->current, 100);
        $this->paidOrder('1002', $this->old, 5000);

        $this->assertEquals(5100.0, $this->get(route('admin.dashboard', ['range' => 'all']))->viewData('grossRevenue'));
    }

    public function test_an_assigned_agent_dashboard_is_narrowed_but_never_widened(): void
    {
        SystemSettings::set('order_assignment_enabled', true);
        $agent = $this->staff(['dashboard.view', 'orders.view', 'orders.view_assigned']);
        $other = User::factory()->create();
        $theirs = $this->event('Colleagues Gala', 'active');

        $this->paidOrder('1001', $this->current, 100, $agent);
        $this->paidOrder('1002', $this->old, 700, $agent);
        $this->paidOrder('1003', $theirs, 9000, $other);

        $page = $this->get(route('admin.dashboard', ['range' => 'all']))->assertOk()->assertDontSee('Colleagues Gala');
        $this->assertEquals(100.0, $page->viewData('grossRevenue'));

        $this->assertEquals(800.0, $this->get(route('admin.dashboard', ['range' => 'all', 'event_id' => 'all']))->viewData('grossRevenue'));
    }

    public function test_reports_open_on_the_active_events_and_step_back(): void
    {
        $this->staff(['reports.view', 'orders.view']);
        $this->paidOrder('1001', $this->current, 100);
        $this->paidOrder('1002', $this->old, 5000);

        $default = $this->get(route('admin.reports.index', ['range' => 'all']))->assertOk()->assertSee('active events (Current Night)')->assertSee('<option value="">Active events</option>', false);
        $this->assertEquals(100.0, $default->viewData('totalRevenue'));
        $this->assertSame(['Current Night'], $default->viewData('eventReports')->pluck('event_name')->all());
        $this->assertContains('Old Night', $default->viewData('eventOptions')->all());   // the way back is always offered

        $all = $this->get(route('admin.reports.index', ['range' => 'all', 'event' => '__all__']))->assertOk();
        $this->assertEquals(5100.0, $all->viewData('totalRevenue'));

        $one = $this->get(route('admin.reports.index', ['range' => 'all', 'event' => 'Old Night']))->assertOk();
        $this->assertEquals(5000.0, $one->viewData('totalRevenue'));
    }

    public function test_agents_report_follows_the_same_default(): void
    {
        SystemSettings::set('order_assignment_enabled', true);
        $agent = $this->staff(['orders.view', 'orders.view_assigned']);
        $this->paidOrder('1001', $this->current, 100, $agent);
        $this->paidOrder('1002', $this->old, 5000, $agent);
        $q = ['from' => now()->subDay()->toDateString(), 'to' => now()->addDay()->toDateString()];

        $default = $this->get(route('admin.reports.agents', $q))->assertOk();
        $this->assertEquals(100.0, $default->viewData('eventRows')->sum('collected'));
        $default->assertSee('<option value="">Active events</option>', false);

        $this->assertEquals(5100.0, $this->get(route('admin.reports.agents', $q + ['event_id' => 'all']))->viewData('eventRows')->sum('collected'));
        $this->assertEquals(5000.0, $this->get(route('admin.reports.agents', $q + ['event_id' => $this->old->id]))->viewData('eventRows')->sum('collected'));
    }
}
