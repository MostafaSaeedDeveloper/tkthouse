<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\EventTicket;
use App\Services\Checkout\CheckoutSelection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TicketDiscountTest extends TestCase
{
    use RefreshDatabase;

    private function ticket(array $attrs = []): EventTicket
    {
        $event = Event::create([
            'name' => 'Disc Night', 'event_date' => now()->addDays(7)->toDateString(), 'event_time' => '20:00:00',
            'location' => 'Cairo', 'description' => '', 'status' => 'active', 'requires_booking_approval' => false,
        ]);

        return EventTicket::create($attrs + ['event_id' => $event->id, 'name' => 'Zalouta', 'price' => 1000, 'status' => 'active', 'max_per_order' => 10]);
    }

    public function test_percent_discount_applies_until_it_ends(): void
    {
        $t = $this->ticket(['discount_type' => 'percent', 'discount_value' => 10, 'discount_ends_at' => now()->addHours(12)]);

        $this->assertTrue($t->hasActiveDiscount());
        $this->assertSame(900.0, $t->currentPrice());
        $this->assertSame('-10%', $t->discountPercentLabel());

        $this->travel(13)->hours();
        $this->assertFalse($t->fresh()->hasActiveDiscount());
        $this->assertSame(1000.0, $t->fresh()->currentPrice());
    }

    public function test_fixed_discount_and_not_started_yet(): void
    {
        $t = $this->ticket(['discount_type' => 'fixed', 'discount_value' => 250, 'discount_starts_at' => now()->addHour(), 'discount_ends_at' => now()->addDay()]);
        $this->assertSame(1000.0, $t->currentPrice());

        $this->travel(2)->hours();
        $this->assertSame(750.0, $t->fresh()->currentPrice());
    }

    public function test_checkout_selection_charges_the_discounted_price(): void
    {
        $t = $this->ticket(['discount_type' => 'percent', 'discount_value' => 10, 'discount_ends_at' => now()->addHours(12)]);
        $sel = app(CheckoutSelection::class)->build($t->event, collect([['ticket_id' => $t->id, 'qty' => 2]]));

        $this->assertSame(900.0, $sel['units'][0]['ticket_price']);
    }

    public function test_event_page_shows_old_price_struck_through_and_countdown(): void
    {
        $t = $this->ticket(['discount_type' => 'percent', 'discount_value' => 10, 'discount_ends_at' => now()->addHours(12)]);

        $this->get(route('front.events.show', $t->event))
            ->assertOk()
            ->assertSee('tkt-ticket__was', false)
            ->assertSee('data-countdown-end', false)
            ->assertSee('-10%');
    }

    public function test_offer_times_use_egypt_time(): void
    {
        $this->assertSame('Africa/Cairo', config('app.timezone'));

        // 2026-11-27 is winter time in Egypt (UTC+2); the countdown deadline sent to the browser carries that offset.
        $this->travelTo(\Illuminate\Support\Carbon::parse('2026-11-27 10:00:00', 'Africa/Cairo'));
        $t = $this->ticket(['discount_type' => 'percent', 'discount_value' => 10, 'discount_ends_at' => '2026-11-27 22:00:00']);

        $this->assertSame('2026-11-27T22:00:00+02:00', $t->fresh()->discount_ends_at->toIso8601String());
        $this->assertTrue($t->fresh()->hasActiveDiscount());

        $this->travelTo(\Illuminate\Support\Carbon::parse('2026-11-27 22:00:01', 'Africa/Cairo'));
        $this->assertFalse($t->fresh()->hasActiveDiscount());
    }

    public function test_fixed_discount_shows_no_percentage_badge(): void
    {
        $t = $this->ticket(['discount_type' => 'fixed', 'discount_value' => 500, 'discount_ends_at' => now()->addHours(12)]);

        $this->get(route('front.events.show', $t->event))
            ->assertOk()
            ->assertSee('tkt-ticket__was', false)
            ->assertSee('data-countdown-end', false)
            ->assertDontSee('-50%')
            ->assertDontSee('-50.0%');
    }
}
