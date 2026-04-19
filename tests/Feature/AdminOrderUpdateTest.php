<?php

namespace Tests\Feature;

use App\Mail\OrderRejectedMail;
use App\Mail\OrderStatusChangedMail;
use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Mail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminOrderUpdateTest extends TestCase
{
    use RefreshDatabase;

    public function test_update_keeps_total_amount_when_items_payload_is_not_sent(): void
    {
        $admin = User::factory()->create();
        $customer = Customer::create([
            'first_name' => 'Test',
            'last_name' => 'Customer',
            'email' => 'customer@example.com',
            'phone' => '01000000000',
        ]);

        $order = Order::create([
            'customer_id' => $customer->id,
            'user_id' => $admin->id,
            'order_number' => '700001',
            'status' => 'pending_payment',
            'requires_approval' => false,
            'payment_method' => 'cash',
            'total_amount' => 300,
        ]);

        OrderItem::create([
            'order_id' => $order->id,
            'ticket_name' => 'General',
            'ticket_price' => 150,
            'quantity' => 2,
            'line_total' => 300,
            'holder_name' => 'Holder Name',
            'holder_email' => 'holder@example.com',
            'holder_phone' => '01111111111',
        ]);

        $response = $this->actingAs($admin)->put(route('admin.orders.update', $order), [
            'status' => 'pending_payment',
            'payment_method' => 'cash',
            'requires_approval' => 0,
        ]);

        $response->assertRedirect(route('admin.orders.show', $order));

        $order->refresh();
        $this->assertSame('300.00', number_format((float) $order->total_amount, 2, '.', ''));
    }

    public function test_update_sends_status_email_when_order_status_changes(): void
    {
        Mail::fake();

        $admin = User::factory()->create();
        $customer = Customer::create([
            'first_name' => 'Status',
            'last_name' => 'Customer',
            'email' => 'status@example.com',
            'phone' => '01000000000',
        ]);

        $order = Order::create([
            'customer_id' => $customer->id,
            'user_id' => $admin->id,
            'order_number' => '700004',
            'status' => 'pending_payment',
            'requires_approval' => false,
            'payment_method' => 'cash',
            'total_amount' => 300,
        ]);

        $response = $this->actingAs($admin)->put(route('admin.orders.update', $order), [
            'status' => 'canceled',
            'payment_method' => 'cash',
            'requires_approval' => 0,
        ]);

        $response->assertRedirect(route('admin.orders.show', $order));

        Mail::assertSent(OrderStatusChangedMail::class, function (OrderStatusChangedMail $mail) use ($customer, $order) {
            return $mail->hasTo($customer->email)
                && $mail->order->is($order)
                && $mail->oldStatus === 'pending_payment'
                && $mail->newStatus === 'canceled';
        });
    }


    public function test_update_sends_rejected_email_when_status_changes_to_rejected(): void
    {
        Mail::fake();

        $admin = User::factory()->create();
        $customer = Customer::create([
            'first_name' => 'Rejected',
            'last_name' => 'Customer',
            'email' => 'rejected-update@example.com',
            'phone' => '01000000000',
        ]);

        $order = Order::create([
            'customer_id' => $customer->id,
            'user_id' => $admin->id,
            'order_number' => '700005',
            'status' => 'pending_payment',
            'requires_approval' => false,
            'payment_method' => 'cash',
            'total_amount' => 300,
        ]);

        $response = $this->actingAs($admin)->put(route('admin.orders.update', $order), [
            'status' => 'rejected',
            'payment_method' => 'cash',
            'requires_approval' => 0,
        ]);

        $response->assertRedirect(route('admin.orders.show', $order));

        Mail::assertSent(OrderRejectedMail::class, function (OrderRejectedMail $mail) use ($customer, $order) {
            return $mail->hasTo($customer->email)
                && $mail->order->is($order);
        });

        Mail::assertNotSent(OrderStatusChangedMail::class);
    }

    public function test_update_status_to_pending_payment_restarts_payment_timeout_counter(): void
    {
        $admin = User::factory()->create();
        $customer = Customer::create([
            'first_name' => 'Timeout',
            'last_name' => 'Reset',
            'email' => 'timeout-reset@example.com',
            'phone' => '01000000000',
        ]);

        $oldTimeoutStart = Carbon::now()->subHours(3);

        $order = Order::create([
            'customer_id' => $customer->id,
            'user_id' => $admin->id,
            'order_number' => '700006',
            'status' => 'canceled',
            'requires_approval' => false,
            'payment_method' => 'cash',
            'total_amount' => 300,
            'payment_timeout_started_at' => $oldTimeoutStart,
        ]);

        $this->actingAs($admin)->put(route('admin.orders.update', $order), [
            'status' => 'pending_payment',
            'payment_method' => 'cash',
            'requires_approval' => 0,
        ])->assertRedirect(route('admin.orders.show', $order));

        $order->refresh();

        $this->assertSame('pending_payment', $order->status);
        $this->assertNotNull($order->payment_timeout_started_at);
        $this->assertTrue($order->payment_timeout_started_at->gt($oldTimeoutStart));
    }

}
