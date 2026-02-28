<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminReportsTest extends TestCase
{
    use RefreshDatabase;

    public function test_reports_page_groups_paid_completed_order_items_by_event(): void
    {
        $admin = User::factory()->create();
        $customer = Customer::create([
            'first_name' => 'Ahmad',
            'last_name' => 'Ali',
            'email' => 'ahmad@example.com',
            'phone' => '01000000001',
        ]);

        $paidOrder = Order::create([
            'customer_id' => $customer->id,
            'user_id' => $admin->id,
            'order_number' => '300001',
            'status' => 'paid',
            'requires_approval' => false,
            'payment_method' => 'visa',
            'total_amount' => 450,
        ]);

        OrderItem::create([
            'order_id' => $paidOrder->id,
            'ticket_name' => 'Halloween Event - VIP',
            'ticket_price' => 200,
            'quantity' => 2,
            'line_total' => 400,
            'holder_name' => 'Male Holder',
            'holder_email' => 'male@example.com',
            'holder_phone' => '01111111111',
            'holder_gender' => 'male',
        ]);

        OrderItem::create([
            'order_id' => $paidOrder->id,
            'ticket_name' => 'Halloween Event - Regular',
            'ticket_price' => 50,
            'quantity' => 1,
            'line_total' => 50,
            'holder_name' => 'Female Holder',
            'holder_email' => 'female@example.com',
            'holder_phone' => '01222222222',
            'holder_gender' => 'female',
        ]);

        $unpaidOrder = Order::create([
            'customer_id' => $customer->id,
            'user_id' => $admin->id,
            'order_number' => '300002',
            'status' => 'pending_payment',
            'requires_approval' => false,
            'payment_method' => 'visa',
            'total_amount' => 999,
        ]);

        OrderItem::create([
            'order_id' => $unpaidOrder->id,
            'ticket_name' => 'Ignored Event - VIP',
            'ticket_price' => 999,
            'quantity' => 1,
            'line_total' => 999,
            'holder_name' => 'Ignored',
            'holder_email' => 'ignored@example.com',
            'holder_phone' => '01333333333',
            'holder_gender' => 'male',
        ]);

        $response = $this->actingAs($admin)->get(route('admin.reports.index'));

        $response
            ->assertOk()
            ->assertSee('Halloween Event')
            ->assertSee('3 sold')
            ->assertSee('450.00 EGP')
            ->assertDontSee('Ignored Event');
    }
}
