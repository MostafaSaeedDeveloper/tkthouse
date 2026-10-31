<?php

namespace Tests\Feature;

use App\Http\Middleware\EnsureAdminPanelAccess;
use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\User;
use App\Services\Admin\DashboardMetrics;
use App\Support\SystemSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\Support\BuildsOrders;
use Tests\TestCase;

class DashboardTicketTypeSalesTest extends TestCase
{
    use BuildsOrders, RefreshDatabase;

    private Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(EnsureAdminPanelAccess::class);
        $this->customer = Customer::create(['first_name' => 'Cus', 'last_name' => 'Tomer', 'email' => 'c@example.test', 'phone' => '0100']);
    }

    private function sale($event, ?int $typeId, string $text, int $qty, float $unit, ?User $assignee = null, string $status = 'paid', bool $excluded = false): void
    {
        $order = Order::create([
            'customer_id' => $this->customer->id, 'order_number' => (string) random_int(1000000000, 9999999999), 'status' => $status,
            'payment_method' => 'cash', 'total_amount' => $qty * $unit, 'assigned_user_id' => $assignee?->id, 'paid_at' => $status === 'paid' ? now() : null,
        ]);
        if ($excluded) {
            $order->forceFill(['exclude_from_statistics' => true])->saveQuietly();
        }
        OrderItem::create(['order_id' => $order->id, 'event_id' => $event->id, 'event_ticket_id' => $typeId, 'ticket_name' => $text, 'ticket_price' => $unit,
            'quantity' => $qty, 'line_total' => $qty * $unit, 'holder_name' => 'H', 'holder_email' => 'h@example.test']);
    }

    private function agent(): User
    {
        $u = User::factory()->create();
        $u->forceFill(['can_receive_orders' => true])->save();
        Permission::findOrCreate('orders.view', 'web');
        Permission::findOrCreate('orders.view_assigned', 'web');
        Permission::findOrCreate('dashboard.view', 'web');
        $u->givePermissionTo(['orders.view', 'orders.view_assigned', 'dashboard.view']);

        return $u->fresh();
    }

    public function test_it_counts_tickets_sold_per_type_for_active_events_only(): void
    {
        $event = $this->makeEvent(['name' => 'Fracture']);
        $ga = $this->makeTicket($event, ['name' => 'GA']);
        $vip = $this->makeTicket($event, ['name' => 'VIP']);
        $this->sale($event, $ga->id, 'Fracture - GA', 2, 100);
        $this->sale($event, $ga->id, 'Fracture - GA', 3, 100);
        $this->sale($event, $vip->id, 'Fracture - VIP', 1, 500);
        $this->sale($event, null, 'Fracture - GA', 1, 100); // legacy row without event_ticket_id: matched by its text
        $this->sale($event, $vip->id, 'Fracture - VIP', 9, 500, null, 'pending_payment'); // unpaid: never counted
        $this->sale($event, $vip->id, 'Fracture - VIP', 9, 500, null, 'paid', true); // excluded from statistics
        $old = $this->makeEvent(['name' => 'Old', 'status' => 'inactive']);
        $this->sale($old, null, 'Old - GA', 4, 10);

        $rows = (new DashboardMetrics)->ticketTypeSales();

        $this->assertCount(1, $rows);
        $this->assertSame('Fracture', $rows[0]['event']->name);
        $this->assertSame(7, $rows[0]['total']);
        $this->assertSame(['GA' => 6, 'VIP' => 1], $rows[0]['types']->pluck('sold', 'name')->all());
        $this->assertSame(1100.0, $rows[0]['revenue']);
    }

    public function test_an_assigned_user_only_sees_the_sales_of_their_own_orders(): void
    {
        SystemSettings::set('order_assignment_enabled', true);
        $event = $this->makeEvent(['name' => 'Fracture']);
        $ga = $this->makeTicket($event, ['name' => 'GA']);
        $vip = $this->makeTicket($event, ['name' => 'VIP']);
        $a = $this->agent();
        $b = $this->agent();
        $this->sale($event, $ga->id, 'Fracture - GA', 2, 100, $a);
        $this->sale($event, $vip->id, 'Fracture - VIP', 5, 500, $b);

        $mine = (new DashboardMetrics([], null, $a, [$event->id]))->ticketTypeSales();
        $this->assertSame(['GA' => 2], $mine[0]['types']->pluck('sold', 'name')->all());

        $page = $this->actingAs($a)->get(route('admin.dashboard'))->assertOk();
        $page->assertSee('Tickets sold by type')->assertSee('GA')->assertDontSee('VIP');
    }

    public function test_the_card_is_hidden_when_no_event_is_on_sale(): void
    {
        $this->makeEvent(['name' => 'Done', 'status' => 'inactive']);
        Permission::findOrCreate('dashboard.view', 'web');
        $admin = tap(User::factory()->create())->givePermissionTo('dashboard.view');

        $this->actingAs($admin)->get(route('admin.dashboard'))->assertOk()->assertDontSee('Tickets sold by type');
    }
}
