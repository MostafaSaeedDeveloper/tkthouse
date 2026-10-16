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

    /** Starts checkout exactly like the event page's Continue button: cart in the URL, then the clean details URL. */
    private function begin(User $user, Event $event, array $rows)
    {
        $this->actingAs($user)
            ->get(route('front.checkout', ['event' => $event->id, 'cart' => $this->cart($rows)]))
            ->assertRedirect(route('front.checkout'));

        return $this->actingAs($user)->get(route('front.checkout'));
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

        $this->begin($user, $event, [['ticket_id' => $ga->id, 'qty' => 2], ['ticket_id' => $couple->id, 'qty' => 2]])->assertOk();

        $selection = session('checkout.event_selection');
        $this->assertCount(4, $selection['units']);          // quantity = number of people/tickets
        $quote = app(\App\Services\Checkout\CheckoutPricing::class)->quote($event, collect($selection['units']));
        $this->assertEqualsWithDelta(800.0, $quote->subtotal, 0.001);
        $this->assertEqualsWithDelta(800 * 0.10 + 20, $quote->feesTotal, 0.001); // subtotal 800 → 80 + 20
        $this->assertEqualsWithDelta(900.0, $quote->total, 0.001);
    }

    public function test_couple_tickets_must_be_booked_as_two(): void
    {
        $user = User::factory()->create();
        $event = $this->event();
        $couple = $this->ticket($event, ['is_couple' => true]);

        foreach ([1, 3] as $qty) {
            $this->actingAs($user)
                ->get(route('front.checkout', ['event' => $event->id, 'cart' => $this->cart([['ticket_id' => $couple->id, 'qty' => $qty]])]))
                ->assertRedirect(route('front.events.show', $event))
                ->assertSessionHas('error');
            $this->assertNull(session('checkout.event_selection'));
        }
    }

    public function test_max_per_order_is_enforced_and_cannot_be_bypassed_by_repeating_rows(): void
    {
        $user = User::factory()->create();
        $event = $this->event();
        $ga = $this->ticket($event, ['max_per_order' => 4]);

        $this->actingAs($user)
            ->get(route('front.checkout', ['event' => $event->id, 'cart' => $this->cart([['ticket_id' => $ga->id, 'qty' => 5]])]))
            ->assertRedirect(route('front.events.show', $event))->assertSessionHas('error');

        $this->actingAs($user)
            ->get(route('front.checkout', ['event' => $event->id, 'cart' => $this->cart([['ticket_id' => $ga->id, 'qty' => 3], ['ticket_id' => $ga->id, 'qty' => 3]])]))
            ->assertRedirect(route('front.events.show', $event))->assertSessionHas('error');

        $this->begin($user, $event, [['ticket_id' => $ga->id, 'qty' => 2], ['ticket_id' => $ga->id, 'qty' => 2]])->assertOk();
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
            ->assertRedirect(route('front.events.show', $event))->assertSessionHas('error');

        $this->actingAs($user)
            ->get(route('front.checkout', ['event' => $event->id, 'cart' => '%%%not-base64%%%']))
            ->assertRedirect(route('front.events.show', $event))->assertSessionHas('error');
    }

    public function test_placing_the_order_rechecks_that_the_selection_is_still_bookable(): void
    {
        $user = User::factory()->create();
        $event = $this->event();
        $ga = $this->ticket($event);

        $this->begin($user, $event, [['ticket_id' => $ga->id, 'qty' => 1]])->assertOk();

        $ga->update(['status' => 'sold_out']);

        $this->actingAs($user)
            ->post(route('front.checkout.store'), [
                'first_name' => 'A', 'last_name' => 'B', 'email' => 'a@example.com',
                'attendees' => [['name' => 'A B', 'phone' => '0100', 'email' => 'a@example.com', 'gender' => 'male', 'social_profile' => 'x']],
            ])
            ->assertRedirect(route('front.events.show', $event))
            ->assertSessionHas('error');

        $this->assertDatabaseCount('orders', 0);
    }

    public function test_a_partly_unavailable_cart_is_rejected_instead_of_silently_dropping_tickets(): void
    {
        $user = User::factory()->create();
        $event = $this->event();
        $ok = $this->ticket($event);
        $sold = $this->ticket($event, ['name' => 'Gone', 'status' => 'sold_out']);

        $this->actingAs($user)
            ->get(route('front.checkout', ['event' => $event->id, 'cart' => $this->cart([['ticket_id' => $ok->id, 'qty' => 1], ['ticket_id' => $sold->id, 'qty' => 1]])]))
            ->assertRedirect(route('front.events.show', $event))->assertSessionHas('error');
        $this->assertNull(session('checkout.event_selection'));
    }

    public function test_a_ticket_from_another_event_cannot_be_smuggled_into_the_cart(): void
    {
        $user = User::factory()->create();
        $event = $this->event();
        $other = $this->event(['name' => 'Other Night']);
        $foreign = $this->ticket($other);

        $this->actingAs($user)
            ->get(route('front.checkout', ['event' => $event->id, 'cart' => $this->cart([['ticket_id' => $foreign->id, 'qty' => 1]])]))
            ->assertRedirect(route('front.events.show', $event))->assertSessionHas('error');
    }

    public function test_open_checkout_no_longer_exists(): void
    {
        $user = User::factory()->create();
        $event = $this->event();
        $this->ticket($event);

        // No event / cart: sent back to the events list, nothing to buy.
        $this->actingAs($user)->get(route('front.checkout'))->assertRedirect(route('front.events'))->assertSessionHas('error');
        $this->actingAs($user)->get(route('front.checkout', ['event' => $event->id]))->assertRedirect(route('front.events'));
        $this->actingAs($user)->get(route('front.checkout', ['cart' => $this->cart([['ticket_id' => 1, 'qty' => 1]])]))->assertRedirect(route('front.events'));

        // Posting the old "open" payload without a valid selection creates nothing.
        $this->actingAs($user)->post(route('front.checkout.store'), [
            'first_name' => 'A', 'last_name' => 'B', 'email' => 'a@example.com',
            'items' => [['ticket_key' => 'event:1', 'quantity' => 1, 'holder_name' => 'A', 'holder_email' => 'a@example.com']],
            'payment_method' => 'cash',
        ])->assertRedirect(route('front.events'));

        $this->assertDatabaseCount('orders', 0);
    }

    public function test_guests_still_have_to_log_in_for_checkout(): void
    {
        $event = $this->event();
        $ga = $this->ticket($event);

        $this->get(route('front.checkout', ['event' => $event->id, 'cart' => $this->cart([['ticket_id' => $ga->id, 'qty' => 1]])]))
            ->assertRedirect();
        $this->post(route('front.checkout.store'), [])->assertRedirect();
    }

    public function test_a_stored_selection_that_became_invalid_sends_the_user_back_to_the_event(): void
    {
        $user = User::factory()->create();
        $event = $this->event();
        $ga = $this->ticket($event);

        $this->begin($user, $event, [['ticket_id' => $ga->id, 'qty' => 1]])->assertOk();

        $ga->update(['max_per_order' => 1, 'status' => 'sold_out']);

        $this->actingAs($user)->get(route('front.checkout'))
            ->assertRedirect(route('front.events.show', $event))
            ->assertSessionHas('error');
        $this->assertNull(session('checkout.event_selection'));
    }

    public function test_order_is_built_from_the_server_selection_with_fees_and_one_item_per_person(): void
    {
        $user = User::factory()->create();
        $event = $this->event(['requires_booking_approval' => true]);
        $couple = $this->ticket($event, ['name' => 'Couple', 'price' => 300, 'is_couple' => true]);
        EventFee::create(['event_id' => $event->id, 'name' => 'Service', 'fee_type' => 'percentage', 'value' => 10]);

        $this->begin($user, $event, [['ticket_id' => $couple->id, 'qty' => 2]])->assertOk();

        $attendee = ['name' => 'P', 'phone' => '0100', 'email' => 'p@example.com', 'gender' => 'female', 'social_profile' => 'p'];
        // Tampering: a single attendee for a couple pass is refused.
        $this->actingAs($user)->post(route('front.checkout.store'), ['first_name' => 'A', 'last_name' => 'B', 'email' => 'a@example.com', 'attendees' => [$attendee]])
            ->assertSessionHasErrors('attendees');
        $this->assertDatabaseCount('orders', 0);

        $this->actingAs($user)->post(route('front.checkout.store'), ['first_name' => 'A', 'last_name' => 'B', 'email' => 'a@example.com', 'attendees' => [$attendee, $attendee]])
            ->assertRedirect();

        $this->assertDatabaseHas('orders', ['subtotal_amount' => 600, 'total_amount' => 660]);
        $this->assertDatabaseCount('order_items', 2);
    }
}
