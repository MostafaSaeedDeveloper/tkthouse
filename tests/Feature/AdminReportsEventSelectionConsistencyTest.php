<?php

namespace Tests\Feature;

use App\Http\Controllers\Admin\ReportController;
use App\Models\Customer;
use App\Models\Event;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

class AdminReportsEventSelectionConsistencyTest extends TestCase
{
    use RefreshDatabase;

    public function test_selected_event_report_counts_hyphenated_event_ticket_names(): void
    {
        $admin = User::factory()->create();
        $customer = Customer::create([
            'first_name' => 'Selected',
            'last_name' => 'Event',
            'email' => 'selected-event@example.com',
            'phone' => '01012345678',
        ]);

        $event = Event::create([
            'name' => 'Mega Event - Friday Edition',
            'event_date' => now()->toDateString(),
            'event_time' => '21:00:00',
            'location' => 'Cairo',
            'description' => 'Test event for report matching.',
            'status' => 'active',
        ]);

        $order = Order::create([
            'customer_id' => $customer->id,
            'user_id' => $admin->id,
            'order_number' => '710001',
            'status' => 'paid',
            'requires_approval' => false,
            'payment_method' => 'visa',
            'payment_status' => 'paid',
            'total_amount' => 600,
            'paid_at' => now(),
        ]);

        OrderItem::create([
            'order_id' => $order->id,
            'ticket_name' => 'Mega Event - Friday Edition - VIP',
            'ticket_price' => 200,
            'quantity' => 2,
            'line_total' => 400,
            'holder_name' => 'VIP Holder',
            'holder_email' => 'vip-holder@example.com',
            'holder_phone' => '01055555551',
        ]);

        OrderItem::create([
            'order_id' => $order->id,
            'ticket_name' => 'Mega Event - Friday Edition - Regular',
            'ticket_price' => 100,
            'quantity' => 2,
            'line_total' => 200,
            'holder_name' => 'Regular Holder',
            'holder_email' => 'regular-holder@example.com',
            'holder_phone' => '01055555552',
        ]);

        $request = Request::create('/dashboard/events/'.$event->id.'/report', 'GET', [
            'range' => 'last30',
        ]);
        $request->setUserResolver(fn () => $admin);

        $view = app(ReportController::class)->index($request, $event);
        $data = $view->getData();

        $this->assertSame(4, $data['totalTickets']);
        $this->assertCount(1, $data['eventReports']);
        $this->assertSame($event->name, $data['eventReports']->first()['event_name']);
        $this->assertSame(4, $data['eventReports']->first()['tickets_sold']);
    }
}
