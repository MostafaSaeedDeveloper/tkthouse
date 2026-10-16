<?php

namespace Tests\Feature;

use App\Mail\LoginOtpMail;
use App\Models\Customer;
use App\Models\Event;
use App\Models\Order;
use App\Models\PaymentAttempt;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Spatie\Permission\Models\Role;
use Tests\Support\BuildsOrders;
use Tests\Support\PinnedSessionManager;
use Tests\Support\PinnedSessionStore;
use Tests\TestCase;

/**
 * The sequential wizard (Your details → each ticket holder → Review & payment) is a presentation layer over the same
 * endpoints and rules. Click-through behaviour (Back/Continue, Enter, pay gate, step reveal) is covered by the browser run
 * in docs/checkout-v2.md; here: markup, the feature flag, the two read-only helper endpoints and that nothing was weakened.
 */
class CheckoutWizardTest extends TestCase
{
    use BuildsOrders, RefreshDatabase;

    private Event $event;

    protected function setUp(): void
    {
        parent::setUp();
        config(['payments.fake.enabled' => true, 'checkout.experience' => 'wizard']);
        Mail::fake();
        Http::preventStrayRequests();
        $this->app->singleton('session', fn ($app) => new PinnedSessionManager($app));
        $this->app->forgetInstance('session.store');
        $this->browser('a');
        $this->event = $this->makeEvent();
        $this->makeMethod('fake', 'fake_card');
    }

    private function browser(string $name): void
    {
        PinnedSessionStore::$pin = substr(hash('sha256', 'wz-'.$name), 0, 40);
        $this->app['session.store']->flush();
        auth()->forgetGuards();
    }

    private function begin(array $rows): void
    {
        $cart = rtrim(strtr(base64_encode(json_encode($rows)), '+/', '-_'), '=');
        $this->get(route('front.checkout', ['event' => $this->event->id, 'cart' => $cart]))->assertRedirect(route('front.checkout'));
    }

    private function row(int $qty, array $o = []): array
    {
        return ['ticket_id' => $this->makeTicket($this->event, $o)->id, 'qty' => $qty];
    }

    private function buyerOnly(array $o = []): array
    {
        return array_merge(['full_name' => 'Nour Hassan', 'email' => 'nour@example.test', 'phone' => '+201000000000'], $o);
    }

    private function payload(int $people, array $o = []): array
    {
        $att = [];
        for ($i = 1; $i <= $people; $i++) {
            $att[] = ['name' => "Guest $i", 'email' => "guest$i@example.test", 'phone' => "+2010000000$i", 'gender' => 'male', 'social_profile' => "g$i"];
        }

        return array_merge($this->buyerOnly(), ['payment_method' => 'fake_card', 'attendees' => $att], $o);
    }

    private function code(): string
    {
        $code = null;
        Mail::assertSent(LoginOtpMail::class, function (LoginOtpMail $m) use (&$code) {
            $code = $m->otpCode;

            return true;
        });

        return (string) $code;
    }

    /* ---------------------------------------------------------------- markup + flag */

    public function test_the_wizard_is_the_default_and_every_step_lives_in_the_same_form(): void
    {
        $this->begin([$this->row(4, ['price' => 100])]);

        $html = $this->get(route('front.checkout'))->assertOk()
            ->assertSee('data-wz', false)->assertSee('js/tkt-checkout-wizard.js', false)
            ->assertSee('data-wz-progress', false)->assertSee('data-wz-otp', false)->assertSee('data-wz-recap', false)
            ->assertSee('Pay now — 400.00 EGP')->assertSee('data-wz-pay', false)
            ->assertDontSee('id="otpModal"', false)                  // the code box is inline in step 1, not a modal
            ->getContent();

        $form = substr($html, strpos($html, 'id="expressForm"'));
        $form = substr($form, 0, strpos($form, '</form>'));
        foreach (['data-wz-step="buyer"', 'data-wz-step="attendees"', 'data-wz-step="review"'] as $step) {
            $this->assertStringContainsString($step, $form);
        }
        $this->assertSame(4, substr_count($form, 'data-attendee="'));  // every ticket's fields are in the one form
        $this->assertStringContainsString('id="promoInput"', $form);
        $this->assertStringContainsString('id="methodGroup"', $form);
        $this->assertStringNotContainsString('type="password"', $form);
    }

    public function test_one_ticket_mixed_categories_and_couple_pass_labels(): void
    {
        $this->begin([$this->row(1, ['name' => 'GA', 'price' => 100])]);
        $this->get(route('front.checkout'))->assertOk()->assertSee('data-ticket-name="GA"', false)->assertSee('data-persons="1"', false);

        $this->browser('b');
        $this->begin([$this->row(2, ['name' => 'GA', 'price' => 100]), $this->row(1, ['name' => 'VIP', 'price' => 300])]);
        $this->get(route('front.checkout'))->assertOk()->assertSee('data-ticket-name="VIP"', false)->assertSee('data-attendee="2"', false)->assertDontSee('data-attendee="3"', false);

        $this->browser('c');
        $this->begin([$this->row(2, ['name' => 'Couple', 'price' => 250, 'is_couple' => true])]);
        $html = $this->get(route('front.checkout'))->assertOk()->assertSee('Pay now — 500.00 EGP')->getContent();
        $this->assertSame(2, substr_count($html, 'data-couple="1"'));
        $this->assertStringContainsString('data-person="2" data-persons="2"', $html);
        $this->postJson(route('front.checkout.store'), $this->payload(2))->assertOk();
        $this->assertEquals(500, Order::firstOrFail()->total_amount);
        $this->assertSame(2, Order::firstOrFail()->items()->count());
    }

    public function test_the_one_page_design_is_still_available_through_the_flag(): void
    {
        config(['checkout.experience' => 'one-page']);
        $this->begin([$this->row(3)]);

        $this->get(route('front.checkout'))->assertOk()
            ->assertDontSee('data-wz', false)->assertDontSee('tkt-checkout-wizard.js', false)
            ->assertSee('id="otpModal"', false)->assertSee('tkt-mobile-summary', false)->assertSee('name="attendees[2][name]"', false);
    }

    public function test_outside_production_a_query_switches_experience_and_is_remembered_for_comparison(): void
    {
        $this->begin([$this->row(2)]);

        $this->get(route('front.checkout', ['experience' => 'one-page']))->assertOk()->assertDontSee('data-wz-progress', false)->assertSee('data-dev-compare', false);
        $this->get(route('front.checkout'))->assertOk()->assertDontSee('data-wz-progress', false);
        $this->get(route('front.checkout', ['experience' => 'wizard']))->assertOk()->assertSee('data-wz-progress', false);
        $this->get(route('front.checkout', ['experience' => 'nonsense']))->assertOk()->assertSee('data-wz-progress', false);   // ignored
    }

    public function test_production_ignores_the_query_override_and_hides_the_compare_links(): void
    {
        $this->app->detectEnvironment(fn () => 'production');
        config(['checkout.experience' => 'one-page']);
        $this->begin([$this->row(2)]);

        $this->get(route('front.checkout', ['experience' => 'wizard']))->assertOk()->assertDontSee('data-wz-progress', false)->assertDontSee('data-dev-compare', false);
    }

    /* ---------------------------------------------------------------- step 1: preflight */

    public function test_preflight_validates_the_buyer_and_creates_nothing(): void
    {
        $this->begin([$this->row(2)]);

        $this->postJson(route('front.checkout.preflight'), $this->buyerOnly())->assertOk()->assertJson(['ok' => true]);

        $this->assertSame(0, User::count());
        $this->assertSame(0, Customer::count());
        $this->assertSame(0, Order::count());
        Mail::assertNothingSent();
        $this->get(route('front.checkout'))->assertOk()
            ->assertSee('value="Nour Hassan"', false)->assertSee('data-preflight-email="nour@example.test"', false);   // remembered for a refresh

        $this->postJson(route('front.checkout.preflight'), ['full_name' => '', 'email' => 'nope', 'phone' => ''])
            ->assertStatus(422)->assertJsonValidationErrors(['first_name', 'email']);
    }

    public function test_preflight_for_an_existing_email_asks_for_the_code_without_revealing_or_changing_anything(): void
    {
        $existing = User::factory()->create(['email' => 'nour@example.test', 'name' => 'Real Nour']);
        $this->begin([$this->row(1)]);

        $r = $this->postJson(route('front.checkout.preflight'), $this->buyerOnly(['full_name' => 'Attacker']))
            ->assertStatus(409)->assertJson(['needs_verification' => true, 'masked' => 'n•••@example.test'])->assertJsonMissingPath('email');
        $this->assertStringNotContainsString('nour@example.test', $r->getContent());
        $this->assertStringNotContainsStringIgnoringCase('already has an account', $r->getContent());

        $this->assertGuest();
        $this->assertSame('Real Nour', $existing->fresh()->name);
        $this->assertSame(0, Customer::count());
        Mail::assertNothingSent();                                    // the code only goes out when the page asks for it
        $this->get(route('front.checkout'))->assertDontSee('data-preflight-email="nour@example.test"', false);

        // The code box then works exactly as before, right in the same page.
        $this->postJson(route('front.auth.otp.send'), ['email' => 'nour@example.test'])->assertOk();
        $this->postJson(route('front.auth.otp.verify'), ['email' => 'nour@example.test', 'code' => $this->code()])->assertOk();
        $this->assertAuthenticatedAs($existing);
        $this->postJson(route('front.checkout.preflight'), $this->buyerOnly())->assertOk();       // now the verified owner
    }

    public function test_preflight_shares_the_probing_throttle_and_refuses_staff_addresses(): void
    {
        foreach (range(1, 8) as $i) {
            User::factory()->create(['email' => "known$i@example.test"]);
        }
        Role::findOrCreate('admin', 'web');
        $staff = User::factory()->create(['email' => 'boss@example.test']);
        $staff->assignRole('admin');
        $this->begin([$this->row(1)]);

        $this->postJson(route('front.checkout.preflight'), $this->buyerOnly(['email' => 'boss@example.test']))->assertStatus(422)->assertJsonValidationErrors('email');
        foreach (range(1, 8) as $i) {
            $this->postJson(route('front.checkout.preflight'), $this->buyerOnly(['email' => "known$i@example.test"]))->assertStatus(409);
        }
        $this->postJson(route('front.checkout.preflight'), $this->buyerOnly(['email' => 'known1@example.test']))->assertStatus(429);
    }

    public function test_preflight_needs_a_live_selection_and_signed_in_users_pass(): void
    {
        $this->postJson(route('front.checkout.preflight'), $this->buyerOnly())->assertStatus(409);       // no cart yet

        $user = User::factory()->create(['email' => 'me@example.test']);
        $this->begin([$this->row(1)]);
        $this->actingAs($user)->postJson(route('front.checkout.preflight'), $this->buyerOnly(['email' => 'someone.else@example.test']))->assertOk();
        $this->assertSame(1, User::count());
    }

    /* ---------------------------------------------------------------- refresh (draft) */

    public function test_draft_keeps_every_ticket_for_a_refresh_and_creates_nothing(): void
    {
        $this->begin([$this->row(3)]);
        $draft = $this->payload(3);
        $draft['attendees'][1]['email'] = 'half-typed';
        $draft['attendees'][0]['evil'] = 'x';                                    // unknown keys are dropped
        $draft['attendees'][2]['name'] = str_repeat('a', 400);                   // long values are cut

        $this->postJson(route('front.checkout.draft'), $draft)->assertOk();

        $this->assertSame(0, Order::count());
        $this->assertSame(0, User::count());
        $page = $this->get(route('front.checkout'))->assertOk()
            ->assertSee('value="Guest 1"', false)->assertSee('value="Guest 2"', false)->assertSee('value="half-typed"', false)
            ->assertSee('value="Nour Hassan"', false)->assertDontSee('evil', false);
        $this->assertStringNotContainsString(str_repeat('a', 300), $page->getContent());
    }

    public function test_autosaving_the_draft_never_eats_the_payment_request_limit(): void
    {
        $this->begin([$this->row(1)]);

        foreach (range(1, 40) as $i) {                                           // typing triggers many autosaves
            $this->postJson(route('front.checkout.draft'), $this->payload(1))->assertOk();
        }
        foreach (range(1, 8) as $i) {
            $this->postJson(route('front.checkout.preflight'), $this->buyerOnly())->assertOk();
        }

        $this->postJson(route('front.checkout.store'), $this->payload(1))->assertOk();   // each route has its own counter
    }

    public function test_draft_needs_a_live_selection(): void
    {
        $this->postJson(route('front.checkout.draft'), $this->payload(1))->assertStatus(409);
    }

    /* ---------------------------------------------------------------- the final submit is unchanged */

    public function test_the_whole_wizard_journey_creates_one_order_and_one_account_then_pays_with_the_fake_gateway(): void
    {
        $this->begin([$this->row(2, ['price' => 100]), $this->row(1, ['name' => 'VIP', 'price' => 300])]);

        $this->postJson(route('front.checkout.preflight'), $this->buyerOnly())->assertOk();
        $this->postJson(route('front.checkout.draft'), $this->payload(3))->assertOk();
        $this->assertSame(0, Order::count());                                      // nothing exists before the payment step

        $redirect = $this->postJson(route('front.checkout.store'), $this->payload(3))->assertOk()->json('redirect');
        $again = $this->postJson(route('front.checkout.store'), $this->payload(3))->assertOk()->json('redirect');

        $this->assertSame($redirect, $again);                                      // repeated "Pay now" → the same order
        $this->assertSame(1, Order::count());
        $this->assertSame(1, User::where('email', 'nour@example.test')->count());
        $this->assertTrue((bool) User::firstOrFail()->passwordless_since !== null);
        $this->assertGuest();

        $order = Order::firstOrFail();
        $this->get($redirect)->assertRedirect();
        $attempt = PaymentAttempt::where('order_id', $order->id)->firstOrFail();
        $this->post(route('front.payments.fake.complete', $attempt), ['outcome' => 'failed'])->assertRedirect();
        $this->get(route('front.checkout.confirmation', $order->order_number))->assertSee('Payment failed');          // failure + retry
        $this->get(route('front.orders.payment.gateway', ['order' => $order, 'token' => $order->payment_link_token, 'method' => 'fake_card']))->assertRedirect();
        $this->post(route('front.payments.fake.complete', PaymentAttempt::where('order_id', $order->id)->latest('id')->first()), ['outcome' => 'paid'])->assertRedirect();

        $this->assertSame('paid', $order->fresh()->status);
        $this->assertSame(3, $order->issuedTickets()->count());
    }

    public function test_the_experience_flag_never_changes_what_the_server_accepts(): void
    {
        $bad = $this->payload(3);
        $bad['attendees'][1]['email'] = 'a..b@example.com';
        $bad['attendees'][2]['gender'] = '';

        $results = [];
        foreach (['wizard', 'one-page'] as $experience) {
            config(['checkout.experience' => $experience]);
            $this->browser($experience);
            $this->begin([$this->row(3)]);
            $results[$experience] = $this->postJson(route('front.checkout.store'), $bad)->assertStatus(422)
                ->assertJsonValidationErrors(['attendees.1.email', 'attendees.2.gender'])->json('errors');
            $this->postJson(route('front.checkout.store'), array_merge($this->payload(3), ['expected_total' => '1.00']))->assertStatus(409)->assertJson(['price_changed' => true]);
        }
        $this->assertSame($results['wizard'], $results['one-page']);
        $this->assertSame(0, Order::count());
        $this->assertSame(0, User::count());
    }

    public function test_an_existing_email_that_skipped_step_one_is_still_stopped_at_payment(): void
    {
        User::factory()->create(['email' => 'nour@example.test']);
        $this->begin([$this->row(1)]);

        // A refresh can land straight on the payment step; the server still demands the code.
        $this->postJson(route('front.checkout.store'), $this->payload(1))->assertStatus(409)->assertJson(['needs_verification' => true]);
        $this->assertSame(0, Order::count());
    }

    public function test_scripts_guard_enter_the_pay_gate_and_never_create_orders_while_navigating(): void
    {
        $wz = file_get_contents(public_path('js/tkt-checkout-wizard.js'));
        $express = file_get_contents(public_path('js/tkt-express.js'));

        $this->assertStringContainsString("e.key !== 'Enter'", $wz);               // Enter never submits payment
        $this->assertStringContainsString('ensureComplete', $express);             // submit is gated on every step
        $this->assertStringContainsString('revealErrors', $express);               // server errors open the right step
        $this->assertStringContainsString('payButtons', $wz);                       // payment controls only on the last step
        $this->assertStringNotContainsString('fetch(', $wz);                        // the wizard itself never calls the server
        $this->assertStringContainsString('/checkout/buyer', $express);
    }
}
