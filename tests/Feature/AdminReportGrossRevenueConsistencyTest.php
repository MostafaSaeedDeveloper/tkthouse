<?php

namespace Tests\Feature;

use App\Http\Middleware\EnsureAdminPanelAccess;
use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminReportGrossRevenueConsistencyTest extends TestCase
{
    use RefreshDatabase;

    public function test_event_report_gross_revenue_matches_dashboard_for_single_event(): void
    {
        $this->withoutMiddleware(EnsureAdminPanelAccess::class);

        $admin = User::factory()->create();
        $customer = Customer::create([
            'first_name' => 'Report',
            'last_name' => 'Customer',
            'email' => 'report-customer@example.com',
            'phone' => '01000000000',
        ]);

        $order = Order::create([
            'customer_id' => $customer->id,
            'user_id' => $admin->id,
            'order_number' => '700001',
            'status' => 'paid',
            'requires_approval' => false,
            'payment_method' => 'visa',
            'payment_status' => 'paid',
            'total_amount' => 127470,
            'created_at' => now()->subDays(2),
            'updated_at' => now()->subDays(2),
        ]);

        OrderItem::create([
            'order_id' => $order->id,
            'ticket_name' => 'Fracture Wa2fa night : Goom Gum & S.A.B.R.I & RIKAYA, Oscar L - Party Animal',
            'ticket_price' => 10500,
            'quantity' => 10,
            'line_total' => 105000,
            'holder_name' => 'Holder One',
            'holder_email' => 'holder-one@example.com',
            'holder_phone' => '01011111111',
            'holder_gender' => 'male',
        ]);

        OrderItem::create([
            'order_id' => $order->id,
            'ticket_name' => 'Fracture Wa2fa night : Goom Gum & S.A.B.R.I & RIKAYA, Oscar L - VIP',
            'ticket_price' => 9250,
            'quantity' => 2,
            'line_total' => 18500,
            'holder_name' => 'Holder Two',
            'holder_email' => 'holder-two@example.com',
            'holder_phone' => '01022222222',
            'holder_gender' => 'female',
        ]);

        $dashboardResponse = $this->actingAs($admin)->get(route('admin.dashboard', ['range' => 'last30']));
        $dashboardResponse->assertOk()->assertViewHas('grossRevenue', 127470.0);

        $reportResponse = $this->actingAs($admin)->get(route('admin.reports.index', [
            'range' => 'last30',
            'event' => 'Fracture Wa2fa night : Goom Gum & S.A.B.R.I & RIKAYA, Oscar L',
        ]));

        $reportResponse
            ->assertOk()
            ->assertViewHas('totalRevenue', 127470.0)
            ->assertViewHas('eventReports', function ($eventReports) {
                if ($eventReports->count() !== 1) {
                    return false;
                }

                $report = $eventReports->first();

                return (float) $report['gross_revenue'] === 127470.0;
            });
    }
}
