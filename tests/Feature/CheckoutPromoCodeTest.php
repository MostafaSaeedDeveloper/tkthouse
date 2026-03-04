<?php

namespace Tests\Feature;

use App\Models\PaymentMethod;
use App\Models\PromoCode;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CheckoutPromoCodeTest extends TestCase
{
    use RefreshDatabase;

    public function test_checkout_applies_percent_promo_code_discount(): void
    {
        $user = User::factory()->create();
        $ticket = Ticket::create([
            'name' => 'VIP',
            'price' => 100,
            'status' => 'active',
        ]);

        PaymentMethod::create([
            'name' => 'Cash',
            'code' => 'cash',
            'provider' => 'manual',
            'is_active' => true,
            'config' => [],
        ]);

        PromoCode::create([
            'code' => 'SAVE10',
            'discount_type' => 'percent',
            'discount_value' => 10,
            'is_active' => true,
        ]);

        $response = $this->actingAs($user)->post(route('front.checkout.store'), [
            'first_name' => 'Test',
            'last_name' => 'User',
            'email' => 'promo@example.com',
            'items' => [
                [
                    'ticket_key' => 'legacy:'.$ticket->id,
                    'quantity' => 2,
                    'holder_name' => 'Holder',
                    'holder_email' => 'holder@example.com',
                    'holder_phone' => '0100000',
                ],
            ],
            'payment_method' => 'cash',
            'promo_code' => 'SAVE10',
        ]);

        $response->assertRedirect();

        $this->assertDatabaseHas('orders', [
            'promo_code' => 'SAVE10',
            'subtotal_amount' => 200,
            'discount_amount' => 20,
            'total_amount' => 180,
        ]);

        $this->assertDatabaseHas('promo_codes', [
            'code' => 'SAVE10',
            'used_count' => 1,
        ]);
    }

    public function test_checkout_rejects_invalid_promo_code(): void
    {
        $user = User::factory()->create();
        $ticket = Ticket::create([
            'name' => 'VIP',
            'price' => 100,
            'status' => 'active',
        ]);

        PaymentMethod::create([
            'name' => 'Cash',
            'code' => 'cash',
            'provider' => 'manual',
            'is_active' => true,
            'config' => [],
        ]);

        $response = $this->from(route('front.checkout'))->actingAs($user)->post(route('front.checkout.store'), [
            'first_name' => 'Test',
            'last_name' => 'User',
            'email' => 'promo@example.com',
            'items' => [[
                'ticket_key' => 'legacy:'.$ticket->id,
                'quantity' => 1,
                'holder_name' => 'Holder',
                'holder_email' => 'holder@example.com',
            ]],
            'payment_method' => 'cash',
            'promo_code' => 'BAD',
        ]);

        $response->assertRedirect(route('front.checkout'));
        $response->assertSessionHasErrors('promo_code');
    }
}
