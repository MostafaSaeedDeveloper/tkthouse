<?php

namespace Tests\Feature;

use App\Mail\LoginOtpMail;
use App\Models\Customer;
use App\Models\Event;
use App\Models\IssuedTicket;
use App\Models\Order;
use App\Models\User;
use App\Services\Auth\EmailOtpService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Spatie\Permission\Models\Role;
use Tests\Support\BuildsOrders;
use Tests\Support\PinnedSessionManager;
use Tests\Support\PinnedSessionStore;
use Tests\TestCase;

/**
 * Typos, resend/expiry of the e-mail code, neutral answers, and what an unconfirmed (passwordless) account can reach.
 */
class ExpressCheckoutIdentityTest extends TestCase
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

    private function browser(string $name): void
    {
        PinnedSessionStore::$pin = substr(hash('sha256', 'browser-'.$name), 0, 40);
        $this->app['session.store']->flush();
        auth()->forgetGuards();
    }

    private function begin(int $qty = 1): void
    {
        $ticket = $this->makeTicket($this->event);
        $cart = rtrim(strtr(base64_encode(json_encode([['ticket_id' => $ticket->id, 'qty' => $qty]])), '+/', '-_'), '=');
        $this->get(route('front.checkout', ['event' => $this->event->id, 'cart' => $cart]))->assertRedirect(route('front.checkout'));
    }

    private function payload(string $email = 'nour@example.test', int $people = 1, array $o = []): array
    {
        $att = [];
        for ($i = 1; $i <= $people; $i++) {
            $att[] = ['name' => "Guest $i", 'email' => "guest$i@example.test", 'phone' => "+2010000000$i", 'gender' => 'male', 'social_profile' => "g$i"];
        }

        return array_merge(['full_name' => 'Nour Hassan', 'email' => $email, 'phone' => '+201000000000', 'payment_method' => 'fake_card', 'attendees' => $att], $o);
    }

    private function place(array $payload)
    {
        return $this->postJson(route('front.checkout.store'), $payload);
    }

    private function code(?string $to = null): string
    {
        $code = null;
        Mail::assertSent(LoginOtpMail::class, function (LoginOtpMail $m) use (&$code, $to) {
            if ($to === null || $m->hasTo($to)) {
                $code = $m->otpCode;
            }

            return true;
        });

        return (string) $code;
    }

    private function sendCode(string $email): void
    {
        Cache::forget('email-otp:'.sha1($email).':cooldown');
        $this->postJson(route('front.auth.otp.send'), ['email' => $email])->assertOk();
    }

    private function verify(string $email, string $code)
    {
        return $this->postJson(route('front.auth.otp.verify'), ['email' => $email, 'code' => $code]);
    }

    /* ------------------------------------------------------------ typed the e-mail wrong */

    public function test_a_typo_is_fixed_on_the_confirmation_page_without_losing_the_booking(): void
    {
        $this->begin(2);
        $this->place($this->payload('nour@exmaple.test', 2))->assertOk();
        $order = Order::firstOrFail();
        $account = User::firstOrFail();

        $this->postJson(route('front.checkout.change-email', $order->order_number), ['email' => 'Nour@Example.test'])
            ->assertOk()->assertJson(['ok' => true, 'email' => 'nour@example.test', 'masked' => 'n•••@example.test']);

        $this->assertSame(1, User::count());                                         // same account, new address
        $this->assertSame('nour@example.test', $account->fresh()->email);
        $this->assertNull($account->fresh()->email_verified_at);
        $this->assertSame('nour@example.test', $order->fresh()->customer->email);
        $this->assertSame(2, $order->items()->count());                               // cart and attendees untouched
        $this->assertSame('Guest 2', $order->items()->orderByDesc('id')->first()->holder_name);
        $this->get(route('front.checkout.confirmation', $order->order_number))->assertOk();   // still reachable by this browser

        // The code now goes to the corrected address only.
        $this->sendCode('nour@example.test');
        Mail::assertSent(LoginOtpMail::class, fn ($m) => $m->hasTo('nour@example.test'));
        Mail::assertNotSent(LoginOtpMail::class, fn ($m) => $m->hasTo('nour@exmaple.test'));
        $this->sendCode('nour@exmaple.test');                                          // old address has no account any more
        Mail::assertSent(LoginOtpMail::class, 1);
    }

    public function test_a_typo_is_fixed_by_resubmitting_the_form_in_the_same_browser(): void
    {
        $this->begin();
        $this->place($this->payload('nour@exmaple.test'))->assertOk();
        $first = User::firstOrFail();

        $this->begin();                                                                // back to tickets, again
        $this->place($this->payload('nour@example.test'))->assertOk();

        $this->assertSame(1, User::count());                                           // no orphan account on the typo
        $this->assertSame($first->id, User::firstOrFail()->id);
        $this->assertSame('nour@example.test', User::firstOrFail()->email);
        $this->assertSame(2, Order::count());
    }

    public function test_the_correction_cannot_be_used_to_reach_an_existing_or_staff_account(): void
    {
        $existing = User::factory()->create(['email' => 'taken@example.test']);
        Role::findOrCreate('admin', 'web');
        $staff = User::factory()->create(['email' => 'boss@example.test']);
        $staff->assignRole('admin');
        $this->begin();
        $this->place($this->payload('typo@exmaple.test'))->assertOk();
        $order = Order::firstOrFail();

        $a = $this->postJson(route('front.checkout.change-email', $order->order_number), ['email' => 'taken@example.test'])->assertStatus(422)->json();
        $b = $this->postJson(route('front.checkout.change-email', $order->order_number), ['email' => 'boss@example.test'])->assertStatus(422)->json();

        $this->assertSame($a, $b);                                                     // one generic answer
        $this->assertStringNotContainsString('account', $a['message']);
        $this->assertSame('typo@exmaple.test', User::where('id', '!=', $existing->id)->where('id', '!=', $staff->id)->firstOrFail()->email);
        $this->assertSame(0, Order::where('user_id', $existing->id)->count());
    }

    public function test_only_the_buying_browser_can_correct_the_email_and_only_until_it_is_claimed(): void
    {
        $this->begin();
        $this->place($this->payload('nour@exmaple.test'))->assertOk();
        $order = Order::firstOrFail();

        $this->browser('b');
        $this->postJson(route('front.checkout.change-email', $order->order_number), ['email' => 'evil@example.test'])->assertNotFound();
        $this->assertSame('nour@exmaple.test', User::firstOrFail()->email);

        // Back in the first browser the owner confirms the mailbox: the correction window closes.
        $this->browser('a');
        $this->place($this->payload('nour@exmaple.test'))->assertOk();                 // (draft survives; same order reused)
        $order = Order::firstOrFail();
        $this->sendCode('nour@exmaple.test');
        $this->verify('nour@exmaple.test', $this->code())->assertOk();
        $this->postJson(route('front.checkout.change-email', $order->order_number), ['email' => 'later@example.test'])->assertNotFound();
        $this->assertSame('nour@exmaple.test', User::firstOrFail()->email);
    }

    public function test_the_modal_never_tells_the_browser_the_full_address_back_and_probing_is_throttled(): void
    {
        foreach (range(1, 8) as $i) {
            User::factory()->create(['email' => "known$i@example.test"]);
        }
        $this->begin();

        foreach (range(1, 8) as $i) {
            $r = $this->place($this->payload("known$i@example.test"))->assertStatus(409)->assertJson(['needs_verification' => true]);
            $this->assertStringNotContainsString("known$i@example.test", $r->getContent());
            $this->assertStringNotContainsStringIgnoringCase('already has an account', $r->getContent());
        }
        $this->place($this->payload('known1@example.test'))->assertStatus(429);
        $this->assertSame(0, Order::count());
    }

    /* ------------------------------------------------------------ the code: resend and expiry */

    public function test_resending_replaces_the_code_and_an_expired_code_is_refused(): void
    {
        $user = User::factory()->create(['email' => 'nour@example.test']);

        $this->sendCode('nour@example.test');
        $first = $this->code();
        $this->travel(31)->seconds();
        $this->sendCode('nour@example.test');
        Mail::assertSent(LoginOtpMail::class, 2);

        $this->verify('nour@example.test', $first)->assertStatus(422);                  // the old code died with the resend
        $second = $this->code();
        $this->assertNotSame($first, $second);

        $this->travel(11)->minutes();
        $this->verify('nour@example.test', $second)->assertStatus(422);                 // expired
        $this->assertGuest();

        $this->sendCode('nour@example.test');
        $this->verify('nour@example.test', $this->code())->assertOk();                  // a fresh one works
        $this->assertAuthenticatedAs($user);
    }

    public function test_a_resend_inside_the_cooldown_sends_nothing_new_and_says_the_same_thing(): void
    {
        User::factory()->create(['email' => 'nour@example.test']);

        $this->postJson(route('front.auth.otp.send'), ['email' => 'nour@example.test'])->assertOk();
        $this->postJson(route('front.auth.otp.send'), ['email' => 'nour@example.test'])->assertOk();

        Mail::assertSent(LoginOtpMail::class, 1);
    }

    /* ------------------------------------------------------------ what an unconfirmed account can reach */

    public function test_an_unconfirmed_account_cannot_be_logged_into_or_used_to_see_orders(): void
    {
        $this->begin();
        $this->place($this->payload('victim@example.test'))->assertOk();
        $order = Order::firstOrFail();
        $order->forceFill(['status' => 'paid', 'paid_at' => now()])->save();
        app(\App\Services\TicketIssuanceService::class)->issueIfPaid($order->fresh());
        $ticket = IssuedTicket::firstOrFail();

        $this->assertGuest();
        $this->get(route('front.account.orders'))->assertRedirect();
        $this->get(route('front.account.tickets'))->assertRedirect();

        // Even the typed e-mail plus the right order number gives a stranger nothing.
        $this->browser('stranger');
        $this->get(route('front.checkout.confirmation', $order->order_number))->assertNotFound();
        $this->get(route('front.tickets.show', $ticket))->assertRedirect();
        $this->place($this->payload('victim@example.test'))->assertStatus(409);        // not even a second order on it
    }

    public function test_orders_placed_before_the_mailbox_was_proven_do_not_show_up_for_the_real_owner(): void
    {
        // An attacker types the victim's e-mail and buys.
        $this->begin();
        $this->place($this->payload('victim@example.test'))->assertOk();
        $order = Order::firstOrFail();
        $order->forceFill(['status' => 'paid', 'paid_at' => now()])->save();
        app(\App\Services\TicketIssuanceService::class)->issueIfPaid($order->fresh());
        $ticket = IssuedTicket::firstOrFail();
        $this->assertTrue((bool) $order->fresh()->owner_unverified);

        // The real owner later signs in with the e-mail code from another browser.
        $this->browser('victim');
        $this->sendCode('victim@example.test');
        $this->verify('victim@example.test', $this->code())->assertOk();

        $this->get(route('front.account.orders'))->assertOk()->assertDontSee($order->order_number);
        $this->get(route('front.account.tickets'))->assertOk()->assertDontSee($ticket->ticket_number);
        $this->get(route('front.tickets.show', $ticket))->assertForbidden();
        $this->get(route('front.tickets.download', $ticket))->assertForbidden();
        $this->get(route('front.checkout.confirmation', $order->order_number))->assertNotFound();
        $this->assertTrue((bool) $order->fresh()->owner_unverified);                    // stays unclaimed
    }

    public function test_the_buying_browser_claims_its_own_orders_when_it_confirms_the_mailbox(): void
    {
        $this->begin();
        $this->place($this->payload('nour@example.test'))->assertOk();
        $order = Order::firstOrFail();

        $this->get(route('front.checkout.confirmation', $order->order_number))->assertOk();   // temporary access by session
        $order->forceFill(['status' => 'paid', 'paid_at' => now()])->save();
        app(\App\Services\TicketIssuanceService::class)->issueIfPaid($order->fresh());
        $ticket = IssuedTicket::firstOrFail();
        $this->sendCode('nour@example.test');
        $this->verify('nour@example.test', $this->code())->assertOk();
        $this->get(route('front.account.tickets'))->assertOk()->assertSee($ticket->ticket_number);
        $this->get(route('front.tickets.show', $ticket))->assertOk();

        $this->assertFalse((bool) $order->fresh()->owner_unverified);
        $this->get(route('front.account.orders'))->assertOk()->assertSee($order->order_number);
        $this->get(route('front.checkout.confirmation', $order->order_number))->assertOk();
    }

    public function test_an_emailed_payment_link_lets_another_device_claim_that_one_order_after_the_code(): void
    {
        $this->begin();
        $this->place($this->payload('nour@example.test'))->assertOk();
        $order = Order::firstOrFail();
        $this->browser('phone');

        $this->postJson(route('front.auth.otp.send'), ['order' => $order->order_number, 'token' => $order->payment_link_token])->assertOk();
        $this->postJson(route('front.auth.otp.verify'), ['order' => $order->order_number, 'token' => $order->payment_link_token, 'code' => $this->code()])->assertOk();

        $this->assertFalse((bool) $order->fresh()->owner_unverified);
        $this->get(route('front.account.orders'))->assertOk()->assertSee($order->order_number);
    }

    public function test_signed_in_and_existing_orders_are_unaffected_by_the_guard(): void
    {
        $user = User::factory()->create();
        $legacy = $this->makePendingOrder($user, $this->event, 1);                       // default flag = false (legacy rows)
        $this->actingAs($user)->get(route('front.account.orders'))->assertOk()->assertSee($legacy->order_number);

        $this->begin();
        $this->postJson(route('front.checkout.store'), $this->payload('whatever@example.test'))->assertOk();
        $this->assertFalse((bool) Order::latest('id')->first()->owner_unverified);        // a signed-in purchase is the owner's
    }

    public function test_auto_account_creation_never_signs_anyone_in_or_exposes_other_customers(): void
    {
        $other = User::factory()->create(['email' => 'other@example.test']);
        $theirs = $this->makePendingOrder($other, $this->event, 1);

        $this->begin();
        $this->place($this->payload('new@example.test'))->assertOk();

        $this->assertGuest();
        $this->get(route('front.checkout.confirmation', $theirs->order_number))->assertNotFound();
        $this->get(route('front.orders.payment', ['order' => $theirs, 'token' => $theirs->payment_link_token]))->assertOk();   // the emailed link alone opens the payment page
        $this->get(route('front.orders.payment', ['order' => $theirs, 'token' => 'wrong-token']))->assertNotFound();
        $this->get(route('front.account.orders'))->assertRedirect();
        $this->assertSame(1, Order::where('user_id', $other->id)->count());
        $this->assertSame(0, Order::where('user_id', User::where('email', 'new@example.test')->value('id'))->where('owner_unverified', false)->count());
    }

    public function test_an_unconfirmed_account_created_elsewhere_still_asks_for_a_code_and_the_code_is_per_browser(): void
    {
        User::factory()->create(['email' => 'half@example.test', 'passwordless_since' => now(), 'email_verified_at' => null]);
        $this->begin();

        $this->place($this->payload('half@example.test'))->assertStatus(409);
        $this->sendCode('half@example.test');
        $code = $this->code();

        $this->browser('b');
        $this->verify('half@example.test', $code)->assertStatus(422);                    // code mailed for browser a only
        $this->assertGuest();

        $this->assertNotNull(EmailOtpService::findUser('half@example.test'));
        $this->assertNull(Customer::where('email', 'half@example.test')->first());
    }
}
