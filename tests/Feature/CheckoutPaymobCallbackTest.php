<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CheckoutPaymobCallbackTest extends TestCase
{
    use RefreshDatabase;

    public function test_paymob_callback_maps_suffixed_merchant_order_id_to_order(): void
    {
        $user = User::factory()->create();
        $customer = Customer::create([
            'first_name' => 'Paymob',
            'last_name' => 'Customer',
            'email' => 'paymob-customer@example.com',
            'phone' => '01000000000',
        ]);

        $order = Order::create([
            'customer_id' => $customer->id,
            'user_id' => $user->id,
            'order_number' => '2602279999',
            'status' => 'pending_payment',
            'requires_approval' => false,
            'payment_method' => 'paymob_card',
            'total_amount' => 150,
        ]);

        $response = $this->postJson(route('front.paymob.callback'), [
            'obj' => [
                'order' => [
                    'merchant_order_id' => $order->order_number.'-retryabcd',
                ],
                'success' => true,
            ],
        ]);

        $response->assertOk()->assertJson([
            'received' => true,
            'updated' => true,
        ]);

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'status' => 'paid',
        ]);
    }
}
