<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Order;
use App\Services\PendingPaymentExpiryService;
use App\Support\SystemSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class PendingPaymentExpiryServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_expire_due_orders_falls_back_to_created_at_when_timeout_start_is_missing(): void
    {
        Mail::fake();

        $customer = Customer::create([
            'first_name' => 'Timer',
            'last_name' => 'Expired',
            'email' => 'timer-expired@example.com',
            'phone' => '01000000000',
        ]);

        $order = Order::create([
            'customer_id' => $customer->id,
            'order_number' => '900001',
            'status' => 'pending_payment',
            'payment_method' => 'cash',
            'total_amount' => 120,
            'created_at' => now()->subMinutes(90),
            'updated_at' => now()->subMinutes(90),
            'payment_timeout_started_at' => null,
        ]);

        $expired = app(PendingPaymentExpiryService::class)->expireDueOrders(60);

        $order->refresh();

        $this->assertTrue($expired->contains(fn (Order $expiredOrder) => $expiredOrder->id === $order->id));
        $this->assertSame('canceled', $order->status);
    }

    public function test_existing_pending_payment_order_keeps_its_original_timeout_minutes(): void
    {
        Mail::fake();

        SystemSettings::set('pending_payment_timeout_minutes', 2);

        $customer = Customer::create([
            'first_name' => 'Timeout',
            'last_name' => 'Snapshot',
            'email' => 'timeout-snapshot@example.com',
            'phone' => '01000000000',
        ]);

        $order = Order::create([
            'customer_id' => $customer->id,
            'order_number' => '900002',
            'status' => 'pending_payment',
            'payment_method' => 'cash',
            'total_amount' => 120,
            'payment_timeout_started_at' => now()->subMinutes(3),
            'payment_timeout_minutes' => 5,
        ]);

        $expired = app(PendingPaymentExpiryService::class)->expireDueOrders();

        $order->refresh();

        $this->assertFalse($expired->contains(fn (Order $expiredOrder) => $expiredOrder->id === $order->id));
        $this->assertSame('pending_payment', $order->status);
    }
}
