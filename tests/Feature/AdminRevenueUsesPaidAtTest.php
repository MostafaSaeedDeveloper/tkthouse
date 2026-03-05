<?php

namespace Tests\Feature;

use App\Http\Middleware\EnsureAdminPanelAccess;
use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class AdminRevenueUsesPaidAtTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_and_report_filter_paid_revenue_by_paid_at(): void
    {
        $this->withoutMiddleware(EnsureAdminPanelAccess::class);

        Carbon::setTestNow(Carbon::parse('2026-03-06 12:00:00'));

        try {
            $admin = User::factory()->create();
            $customer = Customer::create([
                'first_name' => 'PaidAt',
                'last_name' => 'Customer',
                'email' => 'paidat@example.com',
                'phone' => '01077777777',
            ]);

            $order = Order::create([
                'customer_id' => $customer->id,
                'user_id' => $admin->id,
                'order_number' => '880001',
                'status' => 'paid',
                'requires_approval' => false,
                'payment_method' => 'visa',
                'payment_status' => 'paid',
                'total_amount' => 2500,
                'created_at' => Carbon::now()->subDays(2),
                'updated_at' => Carbon::now(),
                'paid_at' => Carbon::now(),
            ]);

            OrderItem::create([
                'order_id' => $order->id,
                'ticket_name' => 'Single Event - Party Animal',
                'ticket_price' => 2500,
                'quantity' => 1,
                'line_total' => 2500,
                'holder_name' => 'Holder',
                'holder_email' => 'holder@example.com',
                'holder_phone' => '01088888888',
                'holder_gender' => 'male',
            ]);

            $dashboardToday = $this->actingAs($admin)->get(route('admin.dashboard', ['range' => 'today']));
            $dashboardToday->assertOk()->assertViewHas('grossRevenue', 2500.0);

            $reportToday = $this->actingAs($admin)->get(route('admin.reports.index', ['range' => 'today']));
            $reportToday->assertOk()->assertViewHas('totalRevenue', 2500.0);
        } finally {
            Carbon::setTestNow();
        }
    }
}
