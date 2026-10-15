<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\EventFee;
use App\Models\EventTicket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CheckoutTicketRulesTest extends TestCase
{
    use RefreshDatabase;

    private function event(array $o = []): Event
    {
        return Event::create(array_merge([
            'name' => 'Rules Night', 'event_date' => now()->addDays(5)->toDateString(), 'event_time' => '21:00:00',
            'location' => 'Cairo', 'description' => '', 'status' => 'active',
        ], $o));
    }

    private function ticket(Event $e, array $o = []): EventTicket
    {
        return EventTicket::create(array_merge(['event_id' => $e->id, 'name' => 'GA', 'price' => 100, 'status' => 'active', 'max_per_order' => 4], $o));
    }

    private function cart(array $rows): string
    {
        return rtrim(strtr(base64_encode(json_encode($rows)), '+/', '-_'), '=');
    }

    public function test_valid_selection_is_stored_with_units_and_fees_matching_the_event_page_formula(): void
    {
        $user = User::factory()->create();
        $event = $this->event();
        $ga = $this->ticket($event);
        $couple = $this->ticket($event, ['name' => 'Couple', 'price' => 300, 'is_couple' => true]);
        EventFee::create(['event_id' => $event->id, 'name' => 'Service', 'fee_type' => 'percentage', 'value' => 10]);
        EventFee::create(['event_id' => $event->id, 'name' => 'Booking', 'fee_type' => 'fixed', 'value' => 20]);

        $this->actingAs($user)
            ->get(route('front.checkout', ['event' => $event->id, 'cart' => $this->cart([['ticket_id' => $ga->id, 'qty' => 2], ['ticket_id' => $couple->id, 'qty' => 2]])]))
            ->assertOk();

        $selection = session('checkout.event_selection');
        $this->assertCount(4, $selection['units']);          // quantity = number of people/tickets
        $this->assertEqualsWithDelta(800 * 0.10 + 20, $selection['fees_total'], 0.001); // subtotal 800 → 80 + 20
    }

    public function test_couple_tickets_must_be_booked_as_two(): void
    {
        $user = User::factory()->create();
        $event = $this->event();
        $couple = $this->ticket($event, ['is_couple' => true]);

        foreach ([1, 3] as $qty) {
            $this->actingAs($user)
                ->get(route('front.checkout', ['event' => $event->id, 'cart' => $this->cart([['ticket_id' => $couple->id, 'qty' => $qty]])]))
                ->assertSessionHasErrors('cart');
        }
    }

    public function test_max_per_order_is_enforced_and_cannot_be_bypassed_by_repeating_rows(): void
    {
        $user = User::factory()->create();
        $event = $this->event();
        $ga = $this->ticket($event, ['max_per_order' => 4]);

        $this->actingAs($user)
            ->get(route('front.checkout', ['event' => $event->id, 'cart' => $this->cart([['ticket_id' => $ga->id, 'qty' => 5]])]))
            ->assertSessionHasErrors('cart');

        $this->actingAs($user)
            ->get(route('front.checkout', ['event' => $event->id, 'cart' => $this->cart([['ticket_id' => $ga->id, 'qty' => 3], ['ticket_id' => $ga->id, 'qty' => 3]])]))
            ->assertSessionHasErrors('cart');

        $this->actingAs($user)
            ->get(route('front.checkout', ['event' => $event->id, 'cart' => $this->cart([['ticket_id' => $ga->id, 'qty' => 2], ['ticket_id' => $ga->id, 'qty' => 2]])]))
            ->assertOk();
        $this->assertCount(4, session('checkout.event_selection.units'));
    }

    public function test_finished_or_sold_out_events_cannot_enter_checkout(): void
    {
        $user = User::factory()->create();

        $past = $this->event(['name' => 'Past One', 'event_date' => now()->subDay()->toDateString()]);
        $pastTicket = $this->ticket($past);
        $this->actingAs($user)
            ->get(route('front.checkout', ['event' => $past->id, 'cart' => $this->cart([['ticket_id' => $pastTicket->id, 'qty' => 1]])]))
            ->assertRedirect(route('front.events.show', $past));
        $this->assertNull(session('checkout.event_selection'));

        $sold = $this->event(['name' => 'Sold One', 'status' => 'sold_out']);
        $soldTicket = $this->ticket($sold);
        $this->actingAs($user)
            ->get(route('front.checkout', ['event' => $sold->id, 'cart' => $this->cart([['ticket_id' => $soldTicket->id, 'qty' => 1]])]))
            ->assertRedirect(route('front.events.show', $sold));
    }

    public function test_sold_out_tickets_and_garbage_carts_are_rejected(): void
    {
        $user = User::factory()->create();
        $event = $this->event();
        $sold = $this->ticket($event, ['status' => 'sold_out']);

        $this->actingAs($user)
            ->get(route('front.checkout', ['event' => $event->id, 'cart' => $this->cart([['ticket_id' => $sold->id, 'qty' => 1]])]))
            ->assertSessionHasErrors('cart');

        $this->actingAs($user)
            ->get(route('front.checkout', ['event' => $event->id, 'cart' => '%%%not-base64%%%']))
            ->assertSessionHasErrors('cart');
    }

    public function test_placing_the_order_rechecks_that_the_selection_is_still_bookable(): void
    {
        $user = User::factory()->create();
        $event = $this->event();
        $ga = $this->ticket($event);

        $this->actingAs($user)
            ->get(route('front.checkout', ['event' => $event->id, 'cart' => $this->cart([['ticket_id' => $ga->id, 'qty' => 1]])]))
            ->assertOk();

        $ga->update(['status' => 'sold_out']);

        $this->actingAs($user)
            ->post(route('front.checkout.store'), [
                'first_name' => 'A', 'last_name' => 'B', 'email' => 'a@example.com',
                'attendees' => [['name' => 'A B', 'phone' => '0100', 'email' => 'a@example.com', 'gender' => 'male', 'social_profile' => 'x']],
            ])
            ->assertSessionHasErrors('tickets');

        $this->assertDatabaseCount('orders', 0);
    }
}
