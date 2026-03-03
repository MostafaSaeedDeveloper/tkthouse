<?php

namespace Tests\Feature;

use App\Http\Middleware\EnsureAdminPanelAccess;
use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminDashboardRevenueTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_revenue_uses_only_paid_and_completed_orders(): void
    {
        $this->withoutMiddleware(EnsureAdminPanelAccess::class);

        $admin = User::factory()->create();
        $customer = Customer::create([
            'first_name' => 'Revenue',
            'last_name' => 'Customer',
            'email' => 'revenue@example.com',
            'phone' => '01012345678',
        ]);

        $paidOrder = Order::create([
            'customer_id' => $customer->id,
            'user_id' => $admin->id,
            'order_number' => '500001',
            'status' => 'paid',
            'requires_approval' => false,
            'payment_method' => 'visa',
            'total_amount' => 100,
        ]);

        OrderItem::create([
            'order_id' => $paidOrder->id,
            'ticket_name' => 'Paid Event - VIP',
            'ticket_price' => 100,
            'quantity' => 1,
            'line_total' => 100,
            'holder_name' => 'Paid Holder',
            'holder_email' => 'paid-holder@example.com',
            'holder_phone' => '01011111111',
        ]);

        $completedOrder = Order::create([
            'customer_id' => $customer->id,
            'user_id' => $admin->id,
            'order_number' => '500002',
            'status' => 'completed',
            'requires_approval' => false,
            'payment_method' => 'visa',
            'total_amount' => 200,
        ]);

        OrderItem::create([
            'order_id' => $completedOrder->id,
            'ticket_name' => 'Completed Event - VIP',
            'ticket_price' => 200,
            'quantity' => 1,
            'line_total' => 200,
            'holder_name' => 'Completed Holder',
            'holder_email' => 'completed-holder@example.com',
            'holder_phone' => '01022222222',
        ]);

        $pendingOrder = Order::create([
            'customer_id' => $customer->id,
            'user_id' => $admin->id,
            'order_number' => '500003',
            'status' => 'pending_payment',
            'requires_approval' => false,
            'payment_method' => 'visa',
            'total_amount' => 999,
        ]);

        OrderItem::create([
            'order_id' => $pendingOrder->id,
            'ticket_name' => 'Pending Event - VIP',
            'ticket_price' => 999,
            'quantity' => 1,
            'line_total' => 999,
            'holder_name' => 'Pending Holder',
            'holder_email' => 'pending-holder@example.com',
            'holder_phone' => '01033333333',
        ]);

        $response = $this->actingAs($admin)->get(route('admin.dashboard'));

        $response
            ->assertOk()
            ->assertViewHas('totalRevenue', 300.0)
            ->assertViewHas('revenueData', fn (array $revenueData) => array_sum($revenueData) === 300.0);
    }
}
