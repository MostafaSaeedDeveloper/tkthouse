<?php

namespace Tests\Feature;

use App\Mail\OrderApprovedMail;
use App\Mail\OrderRejectedMail;
use App\Models\Customer;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class AdminOrderDecisionEmailsTest extends TestCase
{
    use RefreshDatabase;

    public function test_approve_sends_payment_link_email_to_customer(): void
    {
        Mail::fake();

        $admin = User::factory()->create();
        $customer = Customer::create([
            'first_name' => 'Mail',
            'last_name' => 'Customer',
            'email' => 'customer@example.com',
            'phone' => '01000000000',
        ]);

        $order = Order::create([
            'customer_id' => $customer->id,
            'user_id' => $admin->id,
            'order_number' => '700002',
            'status' => 'pending_approval',
            'requires_approval' => true,
            'payment_method' => 'cash',
            'total_amount' => 200,
        ]);

        $response = $this->actingAs($admin)
            ->post(route('admin.orders.approve', $order));

        $response->assertRedirect();

        $order->refresh();
        $this->assertSame('pending_payment', $order->status);
        $this->assertNotNull($order->payment_link_token);

        Mail::assertSent(OrderApprovedMail::class, function (OrderApprovedMail $mail) use ($customer, $order) {
            return $mail->hasTo($customer->email)
                && $mail->order->is($order)
                && str_contains($mail->paymentLink, $order->payment_link_token);
        });
    }

    public function test_reject_sends_rejection_email_to_customer(): void
    {
        Mail::fake();

        $admin = User::factory()->create();
        $customer = Customer::create([
            'first_name' => 'Mail',
            'last_name' => 'Customer',
            'email' => 'rejected@example.com',
            'phone' => '01000000000',
        ]);

        $order = Order::create([
            'customer_id' => $customer->id,
            'user_id' => $admin->id,
            'order_number' => '700003',
            'status' => 'pending_approval',
            'requires_approval' => true,
            'payment_method' => 'cash',
            'total_amount' => 150,
        ]);

        $response = $this->actingAs($admin)
            ->post(route('admin.orders.reject', $order));

        $response->assertRedirect();

        $order->refresh();
        $this->assertSame('rejected', $order->status);

        Mail::assertSent(OrderRejectedMail::class, function (OrderRejectedMail $mail) use ($customer, $order) {
            return $mail->hasTo($customer->email)
                && $mail->order->is($order);
        });
    }
}
