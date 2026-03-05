<?php

namespace Tests\Feature;

use App\Http\Middleware\EnsureAdminPanelAccess;
use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class AdminHiddenOrdersTest extends TestCase
{
    use RefreshDatabase;

    public function test_excluded_orders_are_not_counted_in_dashboard_or_reports(): void
    {
        $this->withoutMiddleware(EnsureAdminPanelAccess::class);

        $admin = User::factory()->create();
        Permission::findOrCreate('dashboard.view', 'web');
        Permission::findOrCreate('reports.view', 'web');
        $admin->givePermissionTo(['dashboard.view', 'reports.view']);

        $customer = Customer::create([
            'first_name' => 'Hidden',
            'last_name' => 'Order',
            'email' => 'hidden-order@example.com',
            'phone' => '01000000099',
        ]);

        $includedOrder = Order::create([
            'customer_id' => $customer->id,
            'user_id' => $admin->id,
            'order_number' => '900001',
            'status' => 'paid',
            'payment_method' => 'cash',
            'total_amount' => 100,
            'exclude_from_statistics' => false,
            'paid_at' => now(),
        ]);

        $excludedOrder = Order::create([
            'customer_id' => $customer->id,
            'user_id' => $admin->id,
            'order_number' => '900002',
            'status' => 'paid',
            'payment_method' => 'cash',
            'total_amount' => 200,
            'exclude_from_statistics' => true,
            'paid_at' => now(),
        ]);


        OrderItem::create([
            'order_id' => $includedOrder->id,
            'ticket_name' => 'Visible Event - VIP',
            'ticket_price' => 100,
            'quantity' => 1,
            'line_total' => 100,
            'holder_name' => 'Visible Holder',
            'holder_email' => 'visible-holder@example.com',
            'holder_phone' => '01010000001',
        ]);

        OrderItem::create([
            'order_id' => $excludedOrder->id,
            'ticket_name' => 'Hidden Event - VIP',
            'ticket_price' => 200,
            'quantity' => 1,
            'line_total' => 200,
            'holder_name' => 'Hidden Holder',
            'holder_email' => 'hidden-holder@example.com',
            'holder_phone' => '01010000002',
        ]);

        $this->actingAs($admin)
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertViewHas('totalOrders', 1)
            ->assertViewHas('grossRevenue', 100.0);

        $this->actingAs($admin)
            ->get(route('admin.reports.index'))
            ->assertOk()
            ->assertViewHas('totalOrders', 1)
            ->assertViewHas('totalRevenue', 100.0);
    }

    public function test_hidden_order_requires_showing_orders_permission_to_access_directly(): void
    {
        $this->withoutMiddleware(EnsureAdminPanelAccess::class);

        $admin = User::factory()->create();
        Permission::findOrCreate('orders.view', 'web');
        Permission::findOrCreate('showing_orders', 'web');
        $admin->givePermissionTo(['orders.view']);

        $customer = Customer::create([
            'first_name' => 'Direct',
            'last_name' => 'Access',
            'email' => 'direct-access@example.com',
            'phone' => '01000000088',
        ]);

        $hiddenOrder = Order::create([
            'customer_id' => $customer->id,
            'user_id' => $admin->id,
            'order_number' => '900003',
            'status' => 'pending_payment',
            'payment_method' => 'cash',
            'total_amount' => 150,
            'exclude_from_statistics' => true,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.orders.show', $hiddenOrder))
            ->assertNotFound();

        $admin->givePermissionTo('showing_orders');

        $this->actingAs($admin)
            ->get(route('admin.orders.show', $hiddenOrder))
            ->assertOk();
    }
}
