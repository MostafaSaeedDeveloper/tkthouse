<?php

namespace Tests\Unit;

use App\Mail\OrderStatusChangedMail;
use App\Models\Customer;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderStatusChangedMailTest extends TestCase
{
    use RefreshDatabase;

    public function test_uses_canceled_template_when_new_status_is_canceled(): void
    {
        $user = User::factory()->create();
        $customer = Customer::create([
            'first_name' => 'Cancel',
            'last_name' => 'User',
            'email' => 'cancel@example.com',
            'phone' => '01000000000',
        ]);

        $order = Order::create([
            'customer_id' => $customer->id,
            'user_id' => $user->id,
            'order_number' => '700100',
            'status' => 'canceled',
            'requires_approval' => false,
            'payment_method' => 'cash',
            'total_amount' => 120,
        ]);

        $mail = new OrderStatusChangedMail($order, 'pending_payment', 'canceled');

        $this->assertSame('emails.orders.canceled', $mail->content()->view);
    }

    public function test_uses_default_status_changed_template_for_non_canceled_statuses(): void
    {
        $user = User::factory()->create();
        $customer = Customer::create([
            'first_name' => 'Status',
            'last_name' => 'User',
            'email' => 'status-user@example.com',
            'phone' => '01000000000',
        ]);

        $order = Order::create([
            'customer_id' => $customer->id,
            'user_id' => $user->id,
            'order_number' => '700101',
            'status' => 'on_hold',
            'requires_approval' => false,
            'payment_method' => 'cash',
            'total_amount' => 180,
        ]);

        $mail = new OrderStatusChangedMail($order, 'pending_payment', 'on_hold');

        $this->assertSame('emails.orders.status-changed', $mail->content()->view);
    }
}
