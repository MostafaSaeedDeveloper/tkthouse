<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\User;
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
}
