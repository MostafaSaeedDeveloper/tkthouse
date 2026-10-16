<?php

namespace Tests\Feature;

use App\Mail\HolderTicketsIssuedMail;
use App\Mail\LoginOtpMail;
use App\Mail\OrderTicketsIssuedMail;
use App\Models\Customer;
use App\Models\Event;
use App\Models\EventFee;
use App\Models\IssuedTicket;
use App\Models\Order;
use App\Models\PaymentAttempt;
use App\Models\PromoCode;
use App\Models\User;
use App\Services\Auth\EmailOtpService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Spatie\Permission\Models\Role;
use Tests\Support\BuildsOrders;
use Tests\Support\PinnedSessionManager;
use Tests\Support\PinnedSessionStore;
use Tests\TestCase;

/**
 * Express checkout: one page, no login, automatic passwordless account, e-mail code for existing accounts.
 * Fake gateway only; mail is faked so nothing can reach a real customer.
 */
class ExpressCheckoutTest extends TestCase
{
    use BuildsOrders, RefreshDatabase;

    private Event $event;

    protected function setUp(): void
    {
        parent::setUp();
        config(['payments.fake.enabled' => true]);
        Mail::fake();
        Http::preventStrayRequests();

        $this->app->singleton('session', fn ($app) => new PinnedSessionManager($app));
        $this->app->forgetInstance('session.store');
        $this->browser('a');
        $this->event = $this->makeEvent();
        $this->makeMethod('fake', 'fake_card');
    }

    /** The test client mints a new session id per request; pin one like a real browser cookie does. */
    private function browser(string $name): void
    {
        PinnedSessionStore::$pin = substr(hash('sha256', 'browser-'.$name), 0, 40);
        $this->app['session.store']->flush();   // the store keeps its attributes between requests in tests
        auth()->forgetGuards();
    }

    private function cart(array $rows): string
    {
        return rtrim(strtr(base64_encode(json_encode($rows)), '+/', '-_'), '=');
    }

    /** Continue from the event page: works for a guest, then lands on the one-page checkout. */
    private function begin(array $rows, ?Event $event = null, ?User $as = null): void
    {
        $event ??= $this->event;
        $client = $as ? $this->actingAs($as) : $this;
        $client->get(route('front.checkout', ['event' => $event->id, 'cart' => $this->cart($rows)]))->assertRedirect(route('front.checkout'));
    }

    private function ticketRows(int $qty = 1, array $o = []): array
    {
        $ticket = $this->makeTicket($this->event, $o);

        return [['ticket_id' => $ticket->id, 'qty' => $qty]];
    }

    private function attendee(int $i = 1, array $o = []): array
    {
        return array_merge(['name' => "Guest $i", 'email' => "guest$i@example.test", 'phone' => "+2010000000$i", 'gender' => $i % 2 ? 'male' : 'female', 'social_profile' => "guest$i"], $o);
    }

    private function payload(int $people = 1, array $o = []): array
    {
        return array_merge([
            'full_name' => 'Nour Hassan', 'email' => 'nour@example.test', 'phone' => '+201000000000', 'payment_method' => 'fake_card',
            'attendees' => collect(range(1, $people))->map(fn ($i) => $this->attendee($i))->all(),
        ], $o);
    }

    private function place(array $payload, ?User $as = null)
    {
        return ($as ? $this->actingAs($as) : $this)->postJson(route('front.checkout.store'), $payload);
    }

    private function lastCode(): string
    {
        $code = null;
        Mail::assertSent(LoginOtpMail::class, function (LoginOtpMail $m) use (&$code) {
            $code = $m->otpCode;

            return true;
        });

        return (string) $code;
    }

    private function pay(Order $order, string $outcome = 'paid')
    {
        $attempt = PaymentAttempt::where('order_id', $order->id)->latest('id')->firstOrFail();

        return $this->post(route('front.payments.fake.complete', $attempt), ['outcome' => $outcome]);
    }

    /* ============================================================ the page itself */

    public function test_a_guest_reaches_the_one_page_checkout_without_logging_in(): void
    {
        $this->begin($this->ticketRows(2));

        $this->get(route('front.checkout'))->assertOk()
            ->assertSee('Your contact details')->assertSee('Who is attending?')->assertSee('Promo code')->assertSee('Payment')->assertSee('Same as buyer')
            ->assertSee('Pay now — 200.00 EGP')->assertSee('data-bottombar', false)->assertSee('tkt-mobile-summary', false)
            ->assertDontSee('Review &amp; payment', false);

        // The checkout form itself never asks for a password (the login modal in the header is separate).
        $html = $this->get(route('front.checkout'))->getContent();
        $form = substr($html, strpos($html, 'id="expressForm"'));
        $form = substr($form, 0, strpos($form, '</form>'));
        $this->assertStringNotContainsString('type="password"', $form);

        $this->get('/checkout/review')->assertRedirect('/checkout');
    }

    public function test_one_form_per_ticket_and_the_couple_rule_is_kept(): void
    {
        $ga = $this->makeTicket($this->event, ['name' => 'GA', 'price' => 100]);
        $couple = $this->makeTicket($this->event, ['name' => 'Couple', 'price' => 250, 'is_couple' => true]);
        $this->begin([['ticket_id' => $ga->id, 'qty' => 1], ['ticket_id' => $couple->id, 'qty' => 2]]);

        $this->get(route('front.checkout'))->assertOk()
            ->assertSee('attendees[2][name]', false)->assertDontSee('attendees[3][name]', false)
            ->assertSee('Person 1 of 2')->assertSee('Person 2 of 2')->assertSee('Couple pass');

        // A couple pass with one ticket is refused at the cart level, for guests too.
        $this->get(route('front.checkout', ['event' => $this->event->id, 'cart' => $this->cart([['ticket_id' => $couple->id, 'qty' => 1]])]))
            ->assertRedirect(route('front.events.show', $this->event))->assertSessionHas('error');
    }

    public function test_the_event_page_continue_link_is_not_hijacked_by_the_login_modal(): void
    {
        $this->makeTicket($this->event);

        $this->get(route('front.events.show', $this->event))->assertOk()->assertSee('data-express-checkout', false)->assertSee('No account needed');
        $this->get(route('front.home'))->assertSee("link.hasAttribute('data-express-checkout')", false);
    }

    /* ============================================================ new customer */

    public function test_a_new_customer_buys_and_a_passwordless_account_is_created(): void
    {
        $this->begin($this->ticketRows(2));

        $response = $this->place($this->payload(2))->assertOk()->assertJson(['ok' => true]);

        $user = User::where('email', 'nour@example.test')->firstOrFail();
        $this->assertSame('Nour Hassan', $user->name);
        $this->assertSame('+201000000000', $user->phone);
        $this->assertNotNull($user->passwordless_since);
        $this->assertNull($user->email_verified_at);                   // the mailbox is not proven yet
        $this->assertFalse(Hash::check('', $user->password));          // random, unknown, never sent
        $this->assertGuest();                                           // typing an e-mail grants no session
        Mail::assertNothingSent();                                      // no password mail, nothing to a real person yet

        $order = Order::firstOrFail();
        $this->assertSame($user->id, $order->user_id);                  // order bound to the new account
        $this->assertSame(2, $order->items()->count());
        $this->assertStringContainsString('/orders/'.$order->id.'/payment/'.$order->payment_link_token.'/gateway', $response->json('redirect'));
        $this->assertSame(1, Customer::where('email', 'nour@example.test')->count());
    }

    public function test_the_guest_pays_with_the_fake_gateway_and_receives_the_tickets(): void
    {
        $this->begin($this->ticketRows(2));
        $redirect = $this->place($this->payload(2))->json('redirect');
        $order = Order::firstOrFail();

        $this->get($redirect)->assertRedirect();                                // hand-off (this browser holds the order)
        $this->pay($order)->assertRedirect(route('front.checkout.confirmation', $order->order_number));

        $page = $this->get(route('front.checkout.confirmation', $order->order_number))->assertOk()
            ->assertSee("You're in!", false)->assertSee('We created a TKT House account')->assertSee('nour@example.test')
            ->assertSee('Download PDF')->assertDontSee('View my tickets');
        $this->assertStringContainsString('signature=', $page->getContent());   // short-lived signed PDF links, not account pages

        $this->assertSame('paid', $order->fresh()->status);
        $this->assertSame(2, IssuedTicket::where('order_id', $order->id)->count());
        Mail::assertSent(OrderTicketsIssuedMail::class, 1);
        Mail::assertSent(HolderTicketsIssuedMail::class, 2);

        // The browser still has no account session: account pages stay closed.
        $this->assertGuest();
        $this->get(route('front.account.tickets'))->assertRedirect();
        $ticket = IssuedTicket::first();
        $this->get(route('front.tickets.show', $ticket))->assertRedirect();
    }

    public function test_the_new_customer_can_claim_the_account_with_a_code_after_buying_and_set_a_password_later(): void
    {
        $this->begin($this->ticketRows(1));
        $this->place($this->payload(1));
        $order = Order::firstOrFail();

        $this->postJson(route('front.auth.otp.send'), ['email' => 'nour@example.test'])->assertOk();
        $this->postJson(route('front.auth.otp.verify'), ['email' => 'nour@example.test', 'code' => $this->lastCode()])
            ->assertOk()->assertJson(['ok' => true, 'passwordless' => true]);

        $user = User::where('email', 'nour@example.test')->firstOrFail();
        $this->assertAuthenticatedAs($user);
        $this->assertNotNull($user->fresh()->email_verified_at);
        $this->assertSame($user->id, $order->fresh()->user_id);

        // Optional password, right after the code.
        $this->get(route('front.account.set-password'))->assertOk()->assertSee('Optional');
        $this->post(route('front.account.set-password.store'), ['password' => 'a-good-password', 'password_confirmation' => 'a-good-password'])
            ->assertRedirect(route('front.account.profile'));
        $user->refresh();
        $this->assertNull($user->passwordless_since);
        $this->assertTrue(Hash::check('a-good-password', $user->password));
        $this->get(route('front.account.set-password'))->assertForbidden();     // no longer passwordless
    }

    public function test_the_set_password_page_cannot_be_used_on_normal_accounts_or_with_a_stale_code(): void
    {
        $normal = User::factory()->create();
        $this->actingAs($normal)->withSession(['auth.otp_verified_at' => now()->timestamp])->get(route('front.account.set-password'))->assertForbidden();
        $this->actingAs($normal)->post(route('front.account.set-password.store'), ['password' => 'new-password-1', 'password_confirmation' => 'new-password-1'])->assertForbidden();
        $this->assertFalse(Hash::check('new-password-1', $normal->fresh()->password));

        $passwordless = User::factory()->create(['passwordless_since' => now()]);
        $this->browser('c');
        $this->actingAs($passwordless)->withSession(['auth.otp_verified_at' => now()->subHour()->timestamp])->get(route('front.account.set-password'))->assertForbidden();
        $this->browser('d');
        $this->actingAs($passwordless)->get(route('front.account.set-password'))->assertForbidden();
        $this->browser('e');
        $this->get(route('front.account.set-password'))->assertRedirect();
    }

    /* ============================================================ existing e-mail: never trusted */

    public function test_an_existing_email_needs_a_code_and_nothing_is_created_or_linked_before_it(): void
    {
        $victim = User::factory()->create(['email' => 'nour@example.test', 'name' => 'Real Nour']);
        $this->begin($this->ticketRows(1));

        $this->place($this->payload(1, ['full_name' => 'Attacker']))
            ->assertStatus(409)->assertJson(['needs_verification' => true, 'email' => 'nour@example.test']);

        $this->assertGuest();
        $this->assertSame(0, Order::count());
        $this->assertSame(1, User::count());
        $this->assertSame('Real Nour', $victim->fresh()->name);                 // nothing was edited
        $this->assertSame(0, Customer::count());
        Mail::assertNothingSent();                                               // the code is sent only when asked for
    }

    public function test_an_existing_customer_signs_in_with_the_code_inside_checkout_and_the_order_is_theirs(): void
    {
        $existing = User::factory()->create(['email' => 'nour@example.test', 'name' => 'Nour H']);
        $this->begin($this->ticketRows(2));
        $payload = $this->payload(2);

        $this->place($payload)->assertStatus(409);
        $this->postJson(route('front.auth.otp.send'), ['email' => 'nour@example.test'])->assertOk();
        $code = $this->lastCode();

        // A wrong code does not log anyone in.
        $this->postJson(route('front.auth.otp.verify'), ['email' => 'nour@example.test', 'code' => '000000'])->assertStatus(422)->assertJson(['ok' => false]);
        $this->assertGuest();

        $this->postJson(route('front.auth.otp.verify'), ['email' => 'nour@example.test', 'code' => $code])->assertOk()->assertJson(['ok' => true]);
        $this->assertAuthenticatedAs($existing);

        // The selection survived the sign-in; the same details are submitted again, now as the verified owner.
        $this->place($payload)->assertOk()->assertJson(['ok' => true]);

        $order = Order::firstOrFail();
        $this->assertSame($existing->id, $order->user_id);
        $this->assertSame(1, User::count());                                     // no duplicate account
        $this->assertSame(2, $order->items()->count());

        // A code works once.
        $this->postJson(route('front.auth.otp.verify'), ['email' => 'nour@example.test', 'code' => $code])->assertStatus(422);
    }

    public function test_the_code_is_bound_to_the_browser_expires_and_locks_after_wrong_guesses(): void
    {
        $user = User::factory()->create(['email' => 'nour@example.test']);
        $otp = app(EmailOtpService::class);

        $otp->send('nour@example.test', 'session-A', '1.1.1.1');
        $code = $this->lastCode();

        $this->assertNull($otp->verify('nour@example.test', $code, 'session-B'));           // another browser can't use it
        for ($i = 0; $i < 5; $i++) {
            $this->assertNull($otp->verify('nour@example.test', '111111', 'session-A'));    // wrong guesses
        }
        $this->assertNull($otp->verify('nour@example.test', $code, 'session-A'));           // locked: even the right code now fails

        $this->travel(11)->minutes();
        \Illuminate\Support\Facades\Cache::flush();
        $otp->send('nour@example.test', 'session-A', '1.1.1.1');
        $fresh = $this->lastCode();
        $this->travel(11)->minutes();
        $this->assertNull($otp->verify('nour@example.test', $fresh, 'session-A'));          // expired
        $this->assertSame($user->id, EmailOtpService::findUser('NOUR@Example.test')->id);   // lookups ignore case
    }

    public function test_sending_a_code_never_reveals_whether_an_account_exists_and_is_rate_limited(): void
    {
        User::factory()->create(['email' => 'known@example.test']);

        $known = $this->postJson(route('front.auth.otp.send'), ['email' => 'known@example.test'])->assertOk()->json();
        $unknown = $this->postJson(route('front.auth.otp.send'), ['email' => 'unknown@example.test'])->assertOk()->json();

        $this->assertSame($known, $unknown);
        Mail::assertSent(LoginOtpMail::class, 1);                                // only the real account got a mail

        // 30 s cooldown, then an hourly cap per address.
        $this->postJson(route('front.auth.otp.send'), ['email' => 'known@example.test'])->assertOk()->assertJsonPath('message', fn ($m) => str_contains($m, 'just sent'));
        for ($i = 0; $i < 6; $i++) {
            $this->travel(31)->seconds();
            $last = $this->postJson(route('front.auth.otp.send'), ['email' => 'known@example.test']);
        }
        $last->assertStatus(429);
    }

    public function test_staff_accounts_cannot_be_used_through_the_passwordless_flow(): void
    {
        Role::findOrCreate('admin', 'web');
        $staff = User::factory()->create(['email' => 'boss@example.test']);
        $staff->assignRole('admin');
        $this->begin($this->ticketRows(1));

        $this->place($this->payload(1, ['email' => 'boss@example.test']))->assertStatus(422)->assertJsonValidationErrors('email');
        $this->assertSame(0, Order::count());

        // And a code can never log a staff account in.
        $this->postJson(route('front.auth.otp.send'), ['email' => 'boss@example.test']);
        $this->postJson(route('front.auth.otp.verify'), ['email' => 'boss@example.test', 'code' => $this->lastCode()])->assertStatus(403);
        $this->assertGuest();
    }

    public function test_a_guest_cannot_attach_an_order_to_someone_elses_account_by_typing_their_email(): void
    {
        $victim = User::factory()->create(['email' => 'victim@example.test']);
        $this->begin($this->ticketRows(1));

        $this->place($this->payload(1, ['email' => 'victim@example.test']))->assertStatus(409);
        $this->place($this->payload(1, ['email' => 'VICTIM@example.test']))->assertStatus(409);   // case tricks
        $this->place($this->payload(1, ['email' => ' victim@example.test ']))->assertStatus(409);   // whitespace tricks

        $this->assertSame(0, Order::where('user_id', $victim->id)->count());
        $this->assertSame(1, User::count());
    }

    /* ============================================================ already signed in */

    public function test_a_signed_in_customer_buys_directly_and_may_change_the_order_contact_without_touching_the_account(): void
    {
        $user = User::factory()->create(['email' => 'me@example.test', 'name' => 'Me Myself']);
        $this->begin($this->ticketRows(1), null, $user);

        $this->actingAs($user)->get(route('front.checkout'))->assertOk()->assertSee('Signed in as me@example.test')->assertSee('value="Me Myself"', false);

        $this->place($this->payload(1, ['full_name' => 'Gift Giver', 'email' => 'someone.else@example.test']), $user)->assertOk();

        $order = Order::firstOrFail();
        $this->assertSame($user->id, $order->user_id);
        $this->assertSame('someone.else@example.test', $order->customer->email);      // contact for THIS order
        $fresh = $user->fresh();
        $this->assertSame('me@example.test', $fresh->email);                         // identity unchanged
        $this->assertSame('Me Myself', $fresh->name);
        $this->assertSame(1, User::count());
        Mail::assertNothingSent();                                                   // no verification needed
    }

    public function test_a_signed_in_customer_does_not_overwrite_another_customers_contact_record(): void
    {
        Customer::create(['first_name' => 'Real', 'last_name' => 'Person', 'email' => 'real@example.test', 'phone' => '+201111111111']);
        $user = User::factory()->create();
        $this->begin($this->ticketRows(1), null, $user);

        $this->place($this->payload(1, ['full_name' => 'Fake Name', 'email' => 'real@example.test', 'phone' => '+209999999999']), $user)->assertOk();

        $customer = Customer::where('email', 'real@example.test')->firstOrFail();
        $this->assertSame('Real', $customer->first_name);
        $this->assertSame('+201111111111', $customer->phone);
    }

    /* ============================================================ double submit, concurrency, refresh */

    public function test_double_submit_and_refresh_create_one_order_and_one_account(): void
    {
        $this->begin($this->ticketRows(1));

        $first = $this->place($this->payload(1))->assertOk();
        $second = $this->place($this->payload(1))->assertOk();
        $third = $this->place($this->payload(1))->assertOk();

        $this->assertSame(1, Order::count());
        $this->assertSame(1, User::where('email', 'nour@example.test')->count());
        $this->assertSame($first->json('redirect'), $second->json('redirect'));
        $this->assertSame($first->json('redirect'), $third->json('redirect'));
    }

    public function test_the_back_button_from_the_gateway_continues_the_same_payment(): void
    {
        $this->begin($this->ticketRows(1));
        $this->place($this->payload(1));
        $order = Order::firstOrFail();

        // Back to /checkout after ordering: no selection left, so the unpaid order's payment page is offered.
        $this->get(route('front.checkout'))->assertRedirect(route('front.orders.payment', ['order' => $order, 'token' => $order->payment_link_token]));
        $this->get(route('front.orders.payment', ['order' => $order, 'token' => $order->payment_link_token]))->assertOk()->assertSee('Complete your payment');
        $this->assertSame(1, Order::count());
    }

    public function test_the_same_new_email_from_two_browsers_creates_a_single_account(): void
    {
        $this->begin($this->ticketRows(1));
        $this->place($this->payload(1))->assertOk();

        // A second browser (fresh session) uses the same address a moment later.
        $this->browser('b');
        $this->begin($this->ticketRows(1));
        $this->place($this->payload(1))->assertStatus(409)->assertJson(['needs_verification' => true]);

        $this->assertSame(1, User::where('email', 'nour@example.test')->count());
        $this->assertSame(1, Order::count());
    }

    public function test_losing_a_race_for_the_same_email_falls_back_to_verification_not_a_duplicate(): void
    {
        $this->begin($this->ticketRows(1));

        // Another process inserts the same e-mail between our existence check and our insert.
        User::creating(function () {
            static $done = false;
            if (! $done) {
                $done = true;
                DB::table('users')->insert(['name' => 'Racer', 'username' => 'racer', 'email' => 'nour@example.test', 'password' => 'x', 'created_at' => now(), 'updated_at' => now()]);
            }
        });

        $this->place($this->payload(1))->assertStatus(409)->assertJson(['needs_verification' => true]);
        $this->assertSame(1, User::where('email', 'nour@example.test')->count());
        $this->assertSame(0, Order::count());
    }

    public function test_a_retry_after_a_failure_by_the_same_browser_reuses_the_account_it_just_created(): void
    {
        $this->begin($this->ticketRows(1));
        $bad = $this->payload(1);
        $bad['attendees'][0]['email'] = 'not-an-email';

        $this->place($bad)->assertStatus(422);
        $this->assertSame(0, User::count());                                        // validation first: no account for a bad form

        $this->place($this->payload(1))->assertOk();
        $this->assertSame(1, User::count());
    }

    /* ============================================================ errors keep the data */

    public function test_validation_errors_are_returned_per_field_and_the_draft_survives_a_reload(): void
    {
        $this->begin($this->ticketRows(2));
        $bad = $this->payload(2, ['full_name' => 'Typed Name']);
        $bad['attendees'][1]['email'] = 'oops';
        $bad['attendees'][1]['gender'] = '';

        $this->place($bad)->assertStatus(422)->assertJsonValidationErrors(['attendees.1.email', 'attendees.1.gender']);
        $this->assertSame(0, Order::count());

        $this->get(route('front.checkout'))->assertOk()
            ->assertSee('value="Typed Name"', false)->assertSee('value="Guest 1"', false)->assertSee('value="oops"', false)->assertSee('value="nour@example.test"', false);
    }

    public function test_every_required_field_is_enforced_but_the_buyer_phone_is_optional(): void
    {
        $this->begin($this->ticketRows(1));

        $this->place(['attendees' => [['name' => '', 'phone' => '', 'email' => '', 'gender' => '', 'social_profile' => '']]])
            ->assertStatus(422)->assertJsonValidationErrors(['first_name', 'email', 'payment_method', 'attendees.0.name', 'attendees.0.phone', 'attendees.0.email', 'attendees.0.gender', 'attendees.0.social_profile']);

        $this->place($this->payload(1, ['phone' => '']))->assertOk();
        $this->assertSame(1, Order::count());
    }

    public function test_the_draft_is_kept_through_the_email_code_step(): void
    {
        User::factory()->create(['email' => 'nour@example.test']);
        $this->begin($this->ticketRows(1));

        $this->place($this->payload(1, ['full_name' => 'Kept Name']))->assertStatus(409);
        $this->get(route('front.checkout'))->assertOk()->assertSee('value="Kept Name"', false)->assertSee('value="Guest 1"', false);
    }

    /* ============================================================ prices, promo, fees, tampering */

    public function test_fees_and_promo_are_applied_server_side_for_guests(): void
    {
        $rows = $this->ticketRows(2, ['price' => 200]);
        EventFee::create(['event_id' => $this->event->id, 'name' => 'Service fee', 'fee_type' => 'percentage', 'value' => 10]);
        EventFee::create(['event_id' => $this->event->id, 'name' => 'Booking fee', 'fee_type' => 'fixed', 'value' => 25]);
        PromoCode::create(['code' => 'FIXED50', 'discount_type' => 'fixed', 'discount_value' => 50, 'is_active' => true]);
        $this->begin($rows);

        $this->postJson(route('front.checkout.promo'), ['promo_code' => 'fixed50'])->assertOk()->assertJson(['quote' => ['subtotal' => 400, 'discount' => 50, 'fees_total' => 65, 'total' => 415]]);
        $this->postJson(route('front.checkout.promo'), ['promo_code' => 'NOPE'])->assertStatus(422);

        $this->get(route('front.checkout'))->assertSee('Service fee')->assertSee('Booking fee')->assertSee('Pay now — 415.00 EGP');
        $this->place($this->payload(2))->assertOk();

        $order = Order::firstOrFail();
        $this->assertEquals(415, $order->total_amount);
        $this->assertEquals(50, $order->discount_amount);
        $this->assertSame(1, (int) PromoCode::where('code', 'FIXED50')->value('used_count'));
    }

    public function test_a_browser_supplied_price_event_or_quantity_is_ignored(): void
    {
        $rows = $this->ticketRows(1, ['price' => 100]);
        $other = $this->makeEvent(['name' => 'Other']);
        $this->makeTicket($other, ['price' => 1]);
        $this->begin($rows);

        $this->place($this->payload(1, ['total_amount' => 1, 'price' => 1, 'event_id' => $other->id, 'quantity' => 9, 'discount_amount' => 99, 'expected_total' => '100.00']))->assertOk();

        $order = Order::firstOrFail();
        $this->assertEquals(100, $order->total_amount);
        $this->assertSame($this->event->id, $order->items()->first()->event_id);
        $this->assertEquals(0, $order->discount_amount);
    }

    public function test_a_price_change_while_checking_out_asks_the_customer_to_look_again(): void
    {
        $ticket = $this->makeTicket($this->event, ['price' => 100]);
        $this->begin([['ticket_id' => $ticket->id, 'qty' => 1]]);
        $this->get(route('front.checkout'))->assertSee('name="expected_total" id="expectedTotal" value="100.00"', false);

        $ticket->update(['price' => 160]);

        $this->place($this->payload(1, ['expected_total' => '100.00']))->assertStatus(409)->assertJson(['price_changed' => true]);
        $this->assertSame(0, Order::count());
        $this->assertSame(0, User::count());                                                // and no account was created for it

        $this->place($this->payload(1, ['expected_total' => '160.00']))->assertOk();
        $this->assertEquals(160, Order::firstOrFail()->total_amount);
    }

    public function test_closed_events_unavailable_tickets_and_unavailable_methods_are_refused(): void
    {
        $rows = $this->ticketRows(1);
        $this->begin($rows);

        $this->place($this->payload(1, ['payment_method' => 'nope']))->assertStatus(422)->assertJsonValidationErrors('payment_method');

        $this->event->update(['event_date' => now()->subDay()->toDateString()]);
        $this->place($this->payload(1))->assertStatus(409)->assertJsonStructure(['redirect']);
        $this->assertSame(0, Order::count());
        $this->assertSame(0, User::count());
    }

    public function test_events_that_need_approval_skip_payment_and_end_in_a_review_state(): void
    {
        $event = $this->makeEvent(['name' => 'Approval Night', 'requires_booking_approval' => true]);
        $t = $this->makeTicket($event);
        $this->begin([['ticket_id' => $t->id, 'qty' => 1]], $event);

        $this->get(route('front.checkout'))->assertSee('You are not charged today')->assertSee('Submit booking request')->assertDontSee('name="payment_method"', false);

        $redirect = $this->place($this->payload(1, ['payment_method' => '']))->assertOk()->json('redirect');
        $order = Order::firstOrFail();
        $this->assertSame('pending_approval', $order->status);
        $this->assertSame(route('front.checkout.confirmation', $order->order_number), $redirect);
        $this->get($redirect)->assertOk()->assertSee('Request received');
    }

    /* ============================================================ payment failure & retry */

    public function test_a_failed_payment_can_be_retried_by_the_same_browser_without_logging_in(): void
    {
        $this->begin($this->ticketRows(1));
        $redirect = $this->place($this->payload(1))->json('redirect');
        $order = Order::firstOrFail();

        $this->get($redirect)->assertRedirect();
        $this->pay($order, 'failed')->assertRedirect(route('front.checkout.confirmation', $order->order_number));

        $this->get(route('front.checkout.confirmation', $order->order_number))->assertSee('Payment failed')->assertSee('Try again');
        $payUrl = route('front.orders.payment', ['order' => $order, 'token' => $order->payment_link_token]);
        $this->get($payUrl)->assertOk()->assertSee('not completed and you were not charged');

        $this->get(route('front.orders.payment.gateway', ['order' => $order, 'token' => $order->payment_link_token, 'method' => 'fake_card']))->assertRedirect();
        $this->assertSame(2, $order->paymentAttempts()->count());
        $this->pay($order, 'paid');

        $this->assertSame('paid', $order->fresh()->status);
        $this->assertSame(1, IssuedTicket::where('order_id', $order->id)->count());
        $this->pay($order, 'paid');                                                       // duplicate callback
        Mail::assertSent(OrderTicketsIssuedMail::class, 1);
    }

    /* ============================================================ who may see an order */

    public function test_orders_and_payment_links_are_not_exposed_to_other_browsers(): void
    {
        $this->begin($this->ticketRows(1));
        $this->place($this->payload(1));
        $order = Order::firstOrFail();

        $this->browser('b');   // someone else's browser

        $this->get(route('front.checkout.confirmation', $order->order_number))->assertNotFound();
        $this->get(route('front.checkout.thank-you', ['order' => $order->order_number]))->assertRedirect(route('front.events'));

        $link = route('front.orders.payment', ['order' => $order, 'token' => $order->payment_link_token]);
        $this->get($link)->assertStatus(401)->assertSee('Confirm it')->assertDontSee($order->customer->email)->assertSee('n••');   // e-mail only masked
        $this->get(route('front.orders.payment', ['order' => $order, 'token' => 'wrong-token']))->assertNotFound();
        $this->get(route('front.orders.payment.gateway', ['order' => $order, 'token' => $order->payment_link_token, 'method' => 'fake_card']))->assertStatus(401);

        // A different signed-in customer is no better off.
        $this->actingAs(User::factory()->create())->get(route('front.checkout.confirmation', $order->order_number))->assertNotFound();
        $this->assertSame(0, PaymentAttempt::count());
    }

    public function test_an_emailed_payment_link_works_after_the_owner_proves_the_mailbox(): void
    {
        $this->begin($this->ticketRows(1));
        $this->place($this->payload(1));
        $order = Order::firstOrFail();
        $this->browser('b');
        $link = route('front.orders.payment', ['order' => $order, 'token' => $order->payment_link_token]);

        $this->get($link)->assertStatus(401);

        // The code goes to the order's account address, which the browser never supplies.
        $this->postJson(route('front.auth.otp.send'), ['order' => $order->order_number, 'token' => $order->payment_link_token, 'email' => 'attacker@example.test'])->assertOk();
        Mail::assertSent(LoginOtpMail::class, fn ($m) => $m->hasTo('nour@example.test'));
        Mail::assertNotSent(LoginOtpMail::class, fn ($m) => $m->hasTo('attacker@example.test'));

        $this->postJson(route('front.auth.otp.verify'), ['order' => $order->order_number, 'token' => 'wrong', 'email' => 'x@example.test', 'code' => '123456'])->assertNotFound();
        $this->postJson(route('front.auth.otp.verify'), ['order' => $order->order_number, 'token' => $order->payment_link_token, 'email' => 'x@example.test', 'code' => $this->lastCode()])->assertOk();

        $this->get($link)->assertOk()->assertSee('Complete your payment');
    }

    public function test_existing_customers_orders_and_tickets_stay_private(): void
    {
        $owner = User::factory()->create();
        $order = $this->makePendingOrder($owner, $this->event, 1);
        $order->update(['status' => 'paid', 'paid_at' => now()]);
        app(\App\Services\TicketIssuanceService::class)->issueIfPaid($order->fresh());
        $ticket = IssuedTicket::firstOrFail();

        $this->get(route('front.checkout.confirmation', $order->order_number))->assertNotFound();
        $this->get(route('front.tickets.show', $ticket))->assertRedirect();
        $this->actingAs(User::factory()->create())->get(route('front.tickets.show', $ticket))->assertForbidden();
        $this->actingAs($owner)->get(route('front.checkout.confirmation', $order->order_number))->assertOk()->assertSee('View my tickets');
    }

    /* ============================================================ old behaviour intact */

    public function test_the_gateway_callbacks_and_old_account_pages_still_work_without_a_session(): void
    {
        $this->get(route('front.about'))->assertOk();
        $this->postJson(route('front.paymob.callback'), [])->assertOk()->assertJson(['updated' => false]);   // webhook has no CSRF/session
        $this->get(route('front.customer.login'))->assertOk();
        $this->get(route('front.account.orders'))->assertRedirect();
    }
}
