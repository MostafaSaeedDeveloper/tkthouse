<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\EventTicket;
use App\Models\PaymentMethod;
use App\Models\PromoCode;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CheckoutPromoCodeTest extends TestCase
{
    use RefreshDatabase;

    /** Starts a real checkout the way the event page does (event + cart) and returns [user, event]. */
    private function startCheckout(int $quantity = 2): array
    {
        $user = User::factory()->create();
        $event = Event::create([
            'name' => 'Promo Night', 'event_date' => now()->addDays(7)->toDateString(), 'event_time' => '20:00:00',
            'location' => 'Cairo', 'description' => '', 'status' => 'active', 'requires_booking_approval' => false,
        ]);
        $ticket = EventTicket::create(['event_id' => $event->id, 'name' => 'VIP', 'price' => 100, 'status' => 'active', 'max_per_order' => 10]);

        // A manual (staff-confirmed) method: no gateway involved.
        PaymentMethod::updateOrCreate(['code' => 'cash'], ['name' => 'Cash', 'provider' => 'manual', 'is_active' => true, 'config' => []]);

        $cart = rtrim(strtr(base64_encode(json_encode([['ticket_id' => $ticket->id, 'qty' => $quantity]])), '+/', '-_'), '=');
        $this->actingAs($user)->get(route('front.checkout', ['event' => $event->id, 'cart' => $cart]))->assertRedirect(route('front.checkout'));
        $this->actingAs($user)->get(route('front.checkout'))->assertOk();

        return [$user, $event];
    }

    private function payload(int $quantity, array $extra = []): array
    {
        return array_merge([
            'first_name' => 'Test', 'last_name' => 'User', 'email' => 'promo@example.com',
            'attendees' => array_fill(0, $quantity, ['name' => 'Holder', 'phone' => '0100000', 'email' => 'holder@example.com', 'gender' => 'male', 'social_profile' => 'holder']),
            'payment_method' => 'cash',
        ], $extra);
    }

    public function test_checkout_applies_percent_promo_code_discount(): void
    {
        [$user] = $this->startCheckout(2);

        PromoCode::create(['code' => 'SAVE10', 'discount_type' => 'percent', 'discount_value' => 10, 'is_active' => true]);

        $this->actingAs($user)->post(route('front.checkout.store'), $this->payload(2, ['promo_code' => 'SAVE10']))->assertRedirect();

        $this->assertDatabaseHas('orders', [
            'promo_code' => 'SAVE10',
            'subtotal_amount' => 200,
            'discount_amount' => 20,
            'total_amount' => 180,
        ]);

        $this->assertDatabaseHas('promo_codes', ['code' => 'SAVE10', 'used_count' => 1]);
    }

    public function test_checkout_rejects_invalid_promo_code(): void
    {
        [$user] = $this->startCheckout(1);

        $response = $this->actingAs($user)->post(route('front.checkout.store'), $this->payload(1, ['promo_code' => 'BAD']));

        $response->assertSessionHasErrors('promo_code');
        $this->assertDatabaseCount('orders', 0);
    }
}
