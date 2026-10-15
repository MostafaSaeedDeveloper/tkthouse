<?php

namespace Tests\Feature;

use App\Mail\HolderTicketsIssuedMail;
use App\Mail\OrderTicketsIssuedMail;
use App\Models\Event;
use App\Models\EventFee;
use App\Models\IssuedTicket;
use App\Models\Order;
use App\Models\PaymentAttempt;
use App\Models\PromoCode;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\Support\BuildsOrders;
use Tests\TestCase;

/**
 * The whole booking journey (details → review → pay → confirmation) against the fake gateway.
 * No real gateway, mail, WhatsApp or money is involved.
 */
class CheckoutFlowTest extends TestCase
{
    use BuildsOrders, RefreshDatabase;

    private User $user;
    private Event $event;

    protected function setUp(): void
    {
        parent::setUp();
        config(['payments.fake.enabled' => true]);
        Mail::fake();
        Http::preventStrayRequests();

        $this->user = User::factory()->create(['name' => 'Mona Ali', 'email' => 'mona@example.test']);
        $this->event = $this->makeEvent();
        $this->makeMethod('fake', 'fake_card');
    }

    private function cart(array $rows): string
    {
        return rtrim(strtr(base64_encode(json_encode($rows)), '+/', '-_'), '=');
    }

    private function begin(array $rows, ?Event $event = null)
    {
        $event ??= $this->event;
        $this->actingAs($this->user)->get(route('front.checkout', ['event' => $event->id, 'cart' => $this->cart($rows)]))
            ->assertRedirect(route('front.checkout'));
    }

    private function attendee(int $i = 1, array $o = []): array
    {
        return array_merge([
            'name' => "Guest $i", 'email' => "guest$i@example.test", 'phone' => "+2010000000$i", 'gender' => $i % 2 ? 'male' : 'female', 'social_profile' => "guest$i",
        ], $o);
    }

    private function details(int $people, array $buyer = [], array $attendeeOverrides = []): array
    {
        return array_merge([
            'full_name' => 'Mona Ali', 'email' => 'mona@example.test', 'phone' => '+201000000000',
            'attendees' => collect(range(1, $people))->map(fn ($i) => $this->attendee($i, $attendeeOverrides[$i - 1] ?? []))->all(),
        ], $buyer);
    }

    private function saveDetails(int $people, array $buyer = [])
    {
        return $this->actingAs($this->user)->post(route('front.checkout.details'), $this->details($people, $buyer));
    }

    private function place(array $extra = [])
    {
        return $this->actingAs($this->user)->post(route('front.checkout.store'), $extra + ['payment_method' => 'fake_card']);
    }

    /* ------------------------------------------------------------- journeys */

    public function test_single_ticket_full_journey_to_issued_ticket(): void
    {
        $ga = $this->makeTicket($this->event, ['price' => 450]);
        $this->begin([['ticket_id' => $ga->id, 'qty' => 1]]);

        $this->actingAs($this->user)->get(route('front.checkout'))
            ->assertOk()->assertSee('Your details')->assertSee('Buyer information')->assertSee('Attendee information')
            ->assertSee('value="Mona Ali"', false)->assertSee('mona@example.test')->assertSee('Same as buyer');

        $this->saveDetails(1)->assertRedirect(route('front.checkout.review'));

        $this->actingAs($this->user)->get(route('front.checkout.review'))
            ->assertOk()->assertSee('Review &amp; payment', false)->assertSee('450.00 EGP')->assertSee('Fake')->assertSee('Guest 1');

        $response = $this->place();
        $order = Order::latest('id')->firstOrFail();
        $response->assertRedirect(route('front.orders.payment.gateway', ['order' => $order, 'token' => $order->payment_link_token, 'method' => 'fake_card']));
        $this->assertSame('pending_payment', $order->status);
        $this->assertEquals(450, $order->total_amount);

        // Gateway hand-off → customer "pays" on the simulator → confirmation.
        $this->actingAs($this->user)->get($response->headers->get('Location'))->assertRedirect();
        $attempt = PaymentAttempt::firstOrFail();
        $this->actingAs($this->user)->post(route('front.payments.fake.complete', $attempt), ['outcome' => 'paid'])
            ->assertRedirect(route('front.checkout.confirmation', $order->order_number));

        $this->actingAs($this->user)->get(route('front.checkout.confirmation', $order->order_number))
            ->assertOk()->assertSee("You're in!", false)->assertSee($order->order_number)->assertSee('Guest 1')->assertSee('View my tickets');

        $this->assertSame('paid', $order->fresh()->status);
        $this->assertSame(1, IssuedTicket::where('order_id', $order->id)->count());
        Mail::assertSent(HolderTicketsIssuedMail::class, 1);
        Mail::assertSent(OrderTicketsIssuedMail::class, 1);
    }

    public function test_multiple_mixed_tickets_including_a_couple_pass(): void
    {
        $ga = $this->makeTicket($this->event, ['name' => 'GA', 'price' => 100]);
        $vip = $this->makeTicket($this->event, ['name' => 'VIP', 'price' => 300]);
        $couple = $this->makeTicket($this->event, ['name' => 'Couple', 'price' => 250, 'is_couple' => true]);
        EventFee::create(['event_id' => $this->event->id, 'name' => 'Service fee', 'fee_type' => 'percentage', 'value' => 10]);
        EventFee::create(['event_id' => $this->event->id, 'name' => 'Booking fee', 'fee_type' => 'fixed', 'value' => 15]);

        $this->begin([['ticket_id' => $ga->id, 'qty' => 2], ['ticket_id' => $vip->id, 'qty' => 1], ['ticket_id' => $couple->id, 'qty' => 2]]);

        $page = $this->actingAs($this->user)->get(route('front.checkout'))->assertOk();
        $page->assertSee('Couple pass')->assertSee('Person 1 of 2')->assertSee('Person 2 of 2')->assertSee('Ticket 2 of 2')
            ->assertSee('attendees[4][name]', false)->assertDontSee('attendees[5][name]', false);

        $this->saveDetails(5)->assertRedirect(route('front.checkout.review'));

        // subtotal 200 + 300 + 500 = 1000 ; fees 100 + 15 ; total 1115
        $this->actingAs($this->user)->get(route('front.checkout.review'))
            ->assertOk()->assertSee('1,000.00 EGP')->assertSee('Service fee')->assertSee('100.00 EGP')->assertSee('Booking fee')->assertSee('15.00 EGP')->assertSee('1,115.00 EGP');

        $this->place();
        $order = Order::firstOrFail();
        $this->assertEquals(1115, $order->total_amount);
        $this->assertEquals(1000, $order->subtotal_amount);
        $this->assertSame(5, $order->items()->count());
        $this->assertSame(['Guest 1', 'Guest 2', 'Guest 3', 'Guest 4', 'Guest 5'], $order->items()->orderBy('id')->pluck('holder_name')->all());
    }

    public function test_events_requiring_approval_skip_payment_and_end_in_a_review_state(): void
    {
        $event = $this->makeEvent(['name' => 'Approval Night', 'requires_booking_approval' => true]);
        $t = $this->makeTicket($event);
        $this->begin([['ticket_id' => $t->id, 'qty' => 1]], $event);
        $this->saveDetails(1);

        $this->actingAs($this->user)->get(route('front.checkout.review'))->assertOk()->assertSee('You are not charged today')->assertDontSee('name="payment_method"', false);

        $response = $this->actingAs($this->user)->post(route('front.checkout.store'), []);
        $order = Order::firstOrFail();
        $response->assertRedirect(route('front.checkout.confirmation', $order->order_number));
        $this->assertSame('pending_approval', $order->status);
        $this->assertNull($order->payment_link_token);

        $this->actingAs($this->user)->get(route('front.checkout.confirmation', $order->order_number))->assertOk()->assertSee('Request received');
        $this->assertSame(0, PaymentAttempt::count());
    }

    /* ------------------------------------------------------------- details step */

    public function test_details_validation_errors_keep_what_was_typed(): void
    {
        $ga = $this->makeTicket($this->event);
        $this->begin([['ticket_id' => $ga->id, 'qty' => 2]]);

        $bad = $this->details(2, ['full_name' => 'Typed Name'], [1 => ['email' => 'not-an-email', 'gender' => '']]);
        $this->actingAs($this->user)->from(route('front.checkout'))->post(route('front.checkout.details'), $bad)
            ->assertRedirect(route('front.checkout'))
            ->assertSessionHasErrors(['attendees.1.email', 'attendees.1.gender']);

        $page = $this->actingAs($this->user)->followingRedirects()->from(route('front.checkout'))->post(route('front.checkout.details'), $bad);
        $page->assertSee('Please check the highlighted fields')->assertSee('Valid email is required.')->assertSee('Gender is required.')
            ->assertSee('value="Typed Name"', false)->assertSee('value="Guest 1"', false)->assertSee('value="not-an-email"', false);
    }

    public function test_every_attendee_field_the_system_requires_is_enforced(): void
    {
        $ga = $this->makeTicket($this->event);
        $this->begin([['ticket_id' => $ga->id, 'qty' => 1]]);

        $this->actingAs($this->user)->post(route('front.checkout.details'), ['full_name' => '', 'email' => '', 'attendees' => [['name' => '', 'phone' => '', 'email' => '', 'gender' => '', 'social_profile' => '']]])
            ->assertSessionHasErrors(['first_name', 'email', 'attendees.0.name', 'attendees.0.phone', 'attendees.0.email', 'attendees.0.gender', 'attendees.0.social_profile']);

        // The buyer's phone is optional.
        $this->saveDetails(1, ['phone' => ''])->assertRedirect(route('front.checkout.review'));

        // Wrong number of attendee forms (tampering) is refused.
        $this->actingAs($this->user)->post(route('front.checkout.details'), $this->details(3))->assertSessionHasErrors('attendees');
    }

    public function test_details_survive_back_navigation_and_review_requires_them(): void
    {
        $ga = $this->makeTicket($this->event);
        $this->begin([['ticket_id' => $ga->id, 'qty' => 1]]);

        $this->actingAs($this->user)->get(route('front.checkout.review'))->assertRedirect(route('front.checkout'));

        $this->saveDetails(1, ['full_name' => 'Back Nav']);
        $this->actingAs($this->user)->get(route('front.checkout.review'))->assertOk();

        // "Back to details" shows everything that had been entered.
        $this->actingAs($this->user)->get(route('front.checkout'))
            ->assertOk()->assertSee('value="Back Nav"', false)->assertSee('value="Guest 1"', false)->assertSee('value="guest1@example.test"', false);

        // Refreshing the clean URL keeps working (no cart in the URL to lose).
        $this->actingAs($this->user)->get(route('front.checkout'))->assertOk();
    }

    public function test_buyer_details_are_prefilled_from_the_account(): void
    {
        $ga = $this->makeTicket($this->event);
        $this->begin([['ticket_id' => $ga->id, 'qty' => 1]]);

        $this->actingAs($this->user)->get(route('front.checkout'))->assertSee('value="Mona Ali"', false)->assertSee('value="mona@example.test"', false);
    }

    /* ------------------------------------------------------------- review: promo & fees */

    public function test_promo_preview_is_computed_by_the_server(): void
    {
        $ga = $this->makeTicket($this->event, ['price' => 200]);
        EventFee::create(['event_id' => $this->event->id, 'name' => 'Service fee', 'fee_type' => 'percentage', 'value' => 10]);
        PromoCode::create(['code' => 'SAVE10', 'discount_type' => 'percent', 'discount_value' => 10, 'is_active' => true]);
        PromoCode::create(['code' => 'OLD', 'discount_type' => 'fixed', 'discount_value' => 5, 'is_active' => true, 'ends_at' => now()->subDay()]);
        $this->begin([['ticket_id' => $ga->id, 'qty' => 2]]);
        $this->saveDetails(2);

        // subtotal 400, discount 40, fees 10% of the UNdiscounted subtotal = 40 → total 400
        $this->actingAs($this->user)->postJson(route('front.checkout.promo'), ['promo_code' => 'save10'])
            ->assertOk()->assertJson(['ok' => true, 'quote' => ['subtotal' => 400, 'discount' => 40, 'fees_total' => 40, 'total' => 400, 'promo_code' => 'SAVE10']]);

        $this->actingAs($this->user)->get(route('front.checkout.review'))->assertSee('SAVE10')->assertSee('−40.00 EGP', false)->assertSee('400.00 EGP');

        $this->actingAs($this->user)->postJson(route('front.checkout.promo'), ['promo_code' => 'NOPE'])->assertStatus(422)->assertJson(['ok' => false, 'message' => 'Promo code is invalid.']);
        $this->actingAs($this->user)->postJson(route('front.checkout.promo'), ['promo_code' => 'OLD'])->assertStatus(422)->assertJson(['message' => 'Promo code has expired.']);

        $this->actingAs($this->user)->postJson(route('front.checkout.promo'), ['remove' => true])->assertOk()->assertJson(['quote' => ['discount' => 0, 'total' => 440]]);

        // Preview never consumes a use.
        $this->assertSame(0, (int) PromoCode::where('code', 'SAVE10')->value('used_count'));
    }

    public function test_promo_discount_and_fixed_and_percentage_fees_are_stored_on_the_order(): void
    {
        $ga = $this->makeTicket($this->event, ['price' => 200]);
        EventFee::create(['event_id' => $this->event->id, 'name' => 'Service fee', 'fee_type' => 'percentage', 'value' => 10]);
        EventFee::create(['event_id' => $this->event->id, 'name' => 'Booking fee', 'fee_type' => 'fixed', 'value' => 25]);
        PromoCode::create(['code' => 'FIXED50', 'discount_type' => 'fixed', 'discount_value' => 50, 'is_active' => true, 'usage_limit' => 1]);
        $this->begin([['ticket_id' => $ga->id, 'qty' => 2]]);
        $this->saveDetails(2);
        $this->actingAs($this->user)->postJson(route('front.checkout.promo'), ['promo_code' => 'FIXED50'])->assertOk();

        $this->place();

        $order = Order::firstOrFail();
        // 400 - 50 + (40 + 25) = 415
        $this->assertEquals(400, $order->subtotal_amount);
        $this->assertEquals(50, $order->discount_amount);
        $this->assertEquals(415, $order->total_amount);
        $this->assertSame('FIXED50', $order->promo_code);
        $this->assertSame(1, (int) PromoCode::where('code', 'FIXED50')->value('used_count'));
    }

    public function test_a_promo_that_became_invalid_is_dropped_with_a_notice(): void
    {
        $ga = $this->makeTicket($this->event, ['price' => 200]);
        $promo = PromoCode::create(['code' => 'ONCE', 'discount_type' => 'percent', 'discount_value' => 50, 'is_active' => true]);
        $this->begin([['ticket_id' => $ga->id, 'qty' => 1]]);
        $this->saveDetails(1);
        $this->actingAs($this->user)->postJson(route('front.checkout.promo'), ['promo_code' => 'ONCE'])->assertOk();

        $promo->update(['is_active' => false]);

        $this->actingAs($this->user)->get(route('front.checkout.review'))->assertOk()->assertSee('Promo code is invalid.')->assertDontSee('−100.00 EGP', false);
    }

    /* ------------------------------------------------------------- placing the order */

    public function test_double_submission_creates_exactly_one_order(): void
    {
        $ga = $this->makeTicket($this->event, ['price' => 100]);
        $this->begin([['ticket_id' => $ga->id, 'qty' => 1]]);
        $this->saveDetails(1);

        $first = $this->place();
        $order = Order::firstOrFail();
        $second = $this->place();   // double click / second tab / refresh of the POST

        $this->assertSame(1, Order::count());
        $this->assertSame($first->headers->get('Location'), $second->headers->get('Location'));
        $this->assertSame(1, $order->items()->count());
    }

    public function test_resubmitting_after_the_session_was_cleared_does_not_duplicate(): void
    {
        $ga = $this->makeTicket($this->event, ['price' => 100]);
        $this->begin([['ticket_id' => $ga->id, 'qty' => 1]]);
        $this->saveDetails(1);
        $this->place();

        // Back button / resubmit after success: nothing left to place, the customer is taken to the order they just made.
        $order = Order::firstOrFail();
        $this->actingAs($this->user)->post(route('front.checkout.store'), ['payment_method' => 'fake_card'])
            ->assertRedirect(route('front.orders.payment.gateway', ['order' => $order, 'token' => $order->payment_link_token, 'method' => 'fake_card']));
        $this->assertSame(1, Order::count());

        // ...but there is no order to jump to for someone who never checked out.
        $this->actingAs(User::factory()->create())->post(route('front.checkout.store'), ['payment_method' => 'fake_card'])->assertRedirect(route('front.events'));
    }

    public function test_a_price_change_between_review_and_payment_aborts_the_order(): void
    {
        $ga = $this->makeTicket($this->event, ['price' => 100]);
        $this->begin([['ticket_id' => $ga->id, 'qty' => 1]]);
        $this->saveDetails(1);
        $this->actingAs($this->user)->get(route('front.checkout.review'))->assertSee('name="expected_total" id="expectedTotal" value="100.00"', false);

        $ga->update(['price' => 160]);

        $this->place(['expected_total' => '100.00'])->assertRedirect(route('front.checkout.review'))->assertSessionHas('error');
        $this->assertSame(0, Order::count());

        // Seeing the new price and confirming it works.
        $this->actingAs($this->user)->get(route('front.checkout.review'))->assertSee('160.00 EGP');
        $this->place(['expected_total' => '160.00']);
        $this->assertEquals(160, Order::firstOrFail()->total_amount);
    }

    public function test_the_browser_cannot_set_prices_quantities_or_the_event(): void
    {
        $ga = $this->makeTicket($this->event, ['price' => 100]);
        $other = $this->makeEvent(['name' => 'Other']);
        $otherTicket = $this->makeTicket($other, ['price' => 1]);
        $this->begin([['ticket_id' => $ga->id, 'qty' => 1]]);
        $this->saveDetails(1);

        $this->place([
            'total_amount' => 1, 'price' => 1, 'event_id' => $other->id, 'ticket_id' => $otherTicket->id, 'quantity' => 6,
            'subtotal' => 1, 'fees_total' => 0, 'discount_amount' => 99,
        ]);

        $order = Order::firstOrFail();
        $this->assertEquals(100, $order->total_amount);
        $this->assertSame($this->event->id, $order->items()->first()->event_id);
        $this->assertSame(1, $order->items()->count());
        $this->assertEquals(0, $order->discount_amount);
    }

    public function test_orders_are_refused_for_ended_or_sold_out_events_and_tickets(): void
    {
        $ga = $this->makeTicket($this->event);
        $this->begin([['ticket_id' => $ga->id, 'qty' => 1]]);
        $this->saveDetails(1);
        $this->event->update(['event_date' => now()->subDay()->toDateString()]);

        $this->place()->assertRedirect(route('front.events.show', $this->event))->assertSessionHas('error');
        $this->assertSame(0, Order::count());

        $this->event->update(['event_date' => now()->addDays(3)->toDateString(), 'status' => 'sold_out']);
        $this->begin_expect_redirect_to_event();
    }

    private function begin_expect_redirect_to_event(): void
    {
        $ga = $this->event->tickets()->first();
        $this->actingAs($this->user)->get(route('front.checkout', ['event' => $this->event->id, 'cart' => $this->cart([['ticket_id' => $ga->id, 'qty' => 1]])]))
            ->assertRedirect(route('front.events.show', $this->event))->assertSessionHas('error');
    }

    public function test_only_available_payment_methods_are_offered_and_accepted(): void
    {
        $ga = $this->makeTicket($this->event);
        // Paymob without credentials must not be offered; a configured Fawaterak is.
        $this->makeMethod('paymob', 'paymob_card', []);
        $this->makeMethod('fawaterak', 'fawaterak_card', ['api_key' => 'k', 'provider_key' => '5']);
        \App\Models\PaymentMethod::where('code', 'paymob_wallet')->update(['is_active' => false]);
        $this->begin([['ticket_id' => $ga->id, 'qty' => 1]]);
        $this->saveDetails(1);

        $this->actingAs($this->user)->get(route('front.checkout.review'))
            ->assertOk()->assertSee('value="fawaterak_card"', false)->assertSee('value="fake_card"', false)->assertDontSee('value="paymob_card"', false);

        $this->place(['payment_method' => 'paymob_card'])->assertSessionHasErrors('payment_method');
        $this->place(['payment_method' => ''])->assertSessionHasErrors('payment_method');
        $this->assertSame(0, Order::count());

        config(['payments.fake.enabled' => false]);
        $this->actingAs($this->user)->get(route('front.checkout.review'))->assertDontSee('value="fake_card"', false);
    }

    /* ------------------------------------------------------------- auth & access */

    public function test_checkout_steps_require_a_logged_in_customer(): void
    {
        foreach ([route('front.checkout'), route('front.checkout.review'), route('front.checkout.confirmation', '123')] as $url) {
            $this->get($url)->assertRedirect();
        }
        $this->post(route('front.checkout.details'), [])->assertRedirect();
        $this->post(route('front.checkout.store'), [])->assertRedirect();
        $this->post(route('front.checkout.promo'), [])->assertRedirect();
    }

    public function test_steps_without_a_selection_send_the_customer_back_to_choose_tickets(): void
    {
        $this->actingAs($this->user);

        $this->get(route('front.checkout'))->assertRedirect(route('front.events'));
        $this->get(route('front.checkout.review'))->assertRedirect(route('front.events'));
        $this->post(route('front.checkout.details'), $this->details(1))->assertRedirect(route('front.events'));
        $this->postJson(route('front.checkout.promo'), ['promo_code' => 'X'])->assertStatus(409);
    }

    /* ------------------------------------------------------------- confirmation states */

    public function test_confirmation_reflects_the_real_order_status_only(): void
    {
        $paid = $this->makePendingOrder($this->user, $this->event, 1);
        $paid->update(['status' => 'paid', 'paid_at' => now()]);
        $this->actingAs($this->user)->get(route('front.checkout.confirmation', $paid->order_number))->assertSee("You're in!", false);

        $unpaid = $this->makePendingOrder($this->user, $this->event, 1);
        $this->actingAs($this->user)->get(route('front.checkout.confirmation', $unpaid->order_number))->assertSee('Complete your payment')->assertDontSee("You're in!", false);

        $pending = $this->makePendingOrder($this->user, $this->event, 1);
        PaymentAttempt::create(['order_id' => $pending->id, 'provider' => 'fake', 'method_code' => 'fake_card', 'amount' => 100, 'status' => 'initiated', 'transaction_id' => 'fake_p', 'meta' => ['fake_state' => 'pending']]);
        $this->actingAs($this->user)->get(route('front.checkout.confirmation', $pending->order_number))->assertSee('Payment pending')->assertSee('data-autorefresh', false)->assertDontSee("You're in!", false);

        $failed = $this->makePendingOrder($this->user, $this->event, 1);
        PaymentAttempt::create(['order_id' => $failed->id, 'provider' => 'fake', 'method_code' => 'fake_card', 'amount' => 100, 'status' => 'failed', 'transaction_id' => 'fake_f']);
        $this->actingAs($this->user)->get(route('front.checkout.confirmation', $failed->order_number))->assertSee('Payment failed')->assertSee('Try again')->assertSee(route('front.orders.payment', ['order' => $failed, 'token' => $failed->payment_link_token]), false);

        $closed = $this->makePendingOrder($this->user, $this->event, 1);
        $closed->update(['status' => 'canceled']);
        $this->actingAs($this->user)->get(route('front.checkout.confirmation', $closed->order_number))->assertSee('Order closed');
    }

    public function test_a_pending_confirmation_asks_the_gateway_and_flips_to_paid(): void
    {
        $order = $this->makePendingOrder($this->user, $this->event, 2);
        $attempt = PaymentAttempt::create(['order_id' => $order->id, 'provider' => 'fake', 'method_code' => 'fake_card', 'amount' => 200, 'status' => 'initiated', 'transaction_id' => 'fake_x', 'meta' => ['fake_state' => 'paid']]);

        $this->actingAs($this->user)->get(route('front.checkout.confirmation', $order->order_number))->assertSee("You're in!", false);

        $this->assertSame('paid', $order->fresh()->status);
        $this->assertSame('paid', $attempt->fresh()->status);
        $this->assertSame(2, IssuedTicket::count());
    }

    public function test_confirmation_is_private_to_the_buyer_and_ignores_query_strings(): void
    {
        $order = $this->makePendingOrder($this->user, $this->event, 1);
        $stranger = User::factory()->create();

        $this->actingAs($stranger)->get(route('front.checkout.confirmation', $order->order_number))->assertNotFound();

        // The legacy "?flow=payment_success" URL can no longer fake a success page.
        $this->actingAs($this->user)->get(route('front.checkout.thank-you', ['flow' => 'payment_success', 'order' => $order->order_number]))
            ->assertRedirect(route('front.checkout.confirmation', $order->order_number));
        $this->actingAs($this->user)->get(route('front.checkout.thank-you', ['flow' => 'payment_success']))->assertRedirect(route('front.account.orders'));
        $this->actingAs($this->user)->get(route('front.checkout.confirmation', $order->order_number).'?flow=payment_success')->assertDontSee("You're in!", false);
    }

    /* ------------------------------------------------------------- retry page */

    public function test_the_payment_page_lets_a_customer_retry_after_a_failure(): void
    {
        $order = $this->makePendingOrder($this->user, $this->event, 1);
        PaymentAttempt::create(['order_id' => $order->id, 'provider' => 'fake', 'method_code' => 'fake_card', 'amount' => 100, 'status' => 'failed']);

        $url = route('front.orders.payment', ['order' => $order, 'token' => $order->payment_link_token]);
        $this->actingAs($this->user)->get($url)->assertOk()->assertSee('Complete your payment')->assertSee('not completed and you were not charged')->assertSee('value="fake_card"', false);

        $this->actingAs($this->user)->get(route('front.orders.payment.gateway', ['order' => $order, 'token' => $order->payment_link_token, 'method' => 'fake_card']))->assertRedirect();
        $this->assertSame(2, $order->paymentAttempts()->count());

        $stranger = User::factory()->create();
        $this->actingAs($stranger)->get($url)->assertForbidden();
    }

    public function test_the_payment_page_of_a_paid_order_goes_to_the_confirmation(): void
    {
        $order = $this->makePendingOrder($this->user, $this->event, 1);
        $order->update(['status' => 'paid', 'paid_at' => now()]);

        $this->actingAs($this->user)->get(route('front.orders.payment', ['order' => $order, 'token' => $order->payment_link_token]))
            ->assertRedirect(route('front.checkout.confirmation', $order->order_number));
    }

    public function test_the_fake_gateway_page_is_not_reachable_in_production_mode(): void
    {
        $order = $this->makePendingOrder($this->user, $this->event, 1);
        $attempt = PaymentAttempt::create(['order_id' => $order->id, 'provider' => 'fake', 'method_code' => 'fake_card', 'amount' => 100, 'status' => 'initiated']);

        $this->actingAs($this->user)->get(route('front.payments.fake.show', $attempt))->assertOk()->assertSee('Test gateway');

        config(['payments.fake.enabled' => false]);
        $this->actingAs($this->user)->get(route('front.payments.fake.show', $attempt))->assertNotFound();
        $this->actingAs($this->user)->post(route('front.payments.fake.complete', $attempt), ['outcome' => 'paid'])->assertNotFound();
        $this->assertSame('pending_payment', $order->fresh()->status);
    }
}
