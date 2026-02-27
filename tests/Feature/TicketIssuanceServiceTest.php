<?php

namespace Tests\Feature;

use App\Mail\OrderTicketsIssuedMail;
use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\User;
use App\Services\TicketIssuanceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class TicketIssuanceServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_generates_tickets_and_sends_email_for_paid_order(): void
    {
        Mail::fake();

        $user = User::factory()->create();
        $customer = Customer::create([
            'first_name' => 'Test',
            'last_name' => 'Customer',
            'email' => 'customer@example.com',
            'phone' => '01000000000',
        ]);

        $order = Order::create([
            'customer_id' => $customer->id,
            'user_id' => $user->id,
            'order_number' => '2602270001',
            'status' => 'paid',
            'requires_approval' => false,
            'payment_method' => 'visa',
            'payment_status' => 'paid',
            'total_amount' => 200,
        ]);

        OrderItem::create([
            'order_id' => $order->id,
            'ticket_name' => 'VIP',
            'ticket_price' => 100,
            'quantity' => 2,
            'line_total' => 200,
            'holder_name' => 'Holder One',
            'holder_email' => 'holder@example.com',
            'holder_phone' => '01111111111',
        ]);

        app(TicketIssuanceService::class)->issueIfPaid($order);

        $this->assertDatabaseCount('issued_tickets', 2);
        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'status' => 'paid',
        ]);

        Mail::assertSent(OrderTicketsIssuedMail::class, 1);
    }

    public function test_it_generates_tickets_when_payment_status_becomes_paid_even_if_status_not_paid(): void
    {
        Mail::fake();

        $user = User::factory()->create();
        $customer = Customer::create([
            'first_name' => 'Another',
            'last_name' => 'Customer',
            'email' => 'another@example.com',
            'phone' => '01000000001',
        ]);

        $order = Order::create([
            'customer_id' => $customer->id,
            'user_id' => $user->id,
            'order_number' => '2602270002',
            'status' => 'approved_pending_payment',
            'requires_approval' => true,
            'payment_method' => 'visa',
            'payment_status' => 'unpaid',
            'total_amount' => 150,
        ]);

        OrderItem::create([
            'order_id' => $order->id,
            'ticket_name' => 'Regular',
            'ticket_price' => 150,
            'quantity' => 1,
            'line_total' => 150,
            'holder_name' => 'Holder Two',
            'holder_email' => 'holder2@example.com',
            'holder_phone' => '01111111112',
        ]);

        $order->update([
            'payment_status' => 'paid',
        ]);

        $this->assertDatabaseCount('issued_tickets', 1);

        Mail::assertSent(OrderTicketsIssuedMail::class, 1);
    }
}
