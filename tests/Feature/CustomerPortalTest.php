<?php

namespace Tests\Feature;

use App\Mail\LoginOtpMail;
use App\Models\Event;
use App\Models\IssuedTicket;
use App\Models\Order;
use App\Models\PaymentAttempt;
use App\Models\Ticket;
use App\Models\User;
use App\Services\TicketIssuanceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Spatie\Permission\Models\Role;
use Tests\Support\BuildsOrders;
use Tests\Support\PinnedSessionManager;
use Tests\Support\PinnedSessionStore;
use Tests\TestCase;

class CustomerPortalTest extends TestCase
{
    use BuildsOrders, RefreshDatabase;

    private Event $event;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        Http::preventStrayRequests();
        // The test client mints a new session id per request; keep one like a real browser cookie (code sign-in is session-bound).
        $this->app->singleton('session', fn ($app) => new PinnedSessionManager($app));
        $this->app->forgetInstance('session.store');
        PinnedSessionStore::$pin = substr(hash('sha256', 'portal'), 0, 40);
        $this->app['session.store']->flush();
        $this->event = $this->makeEvent();
    }

    private function customer(array $o = []): User
    {
        return User::factory()->create(array_merge(['email_verified_at' => now()], $o));
    }

    private function paid(User $user, int $people = 2, ?Event $event = null): Order
    {
        $order = $this->makePendingOrder($user, $event ?? $this->event, $people);
        $order->forceFill(['status' => 'paid', 'paid_at' => now()])->save();
        app(TicketIssuanceService::class)->issueIfPaid($order->fresh());

        return $order->fresh();
    }

    /* ------------------------------------------------------------ ownership */

    public function test_scopes_never_trust_an_email_alone(): void
    {
        $me = $this->customer(['email' => 'me@example.test']);
        $stranger = $this->customer(['email' => 'other@example.test']);

        $mine = $this->makePendingOrder($me, $this->event, 1);
        $theirs = $this->makePendingOrder($stranger, $this->event, 1);
        $theirs->customer->update(['email' => 'me@example.test']);              // same e-mail typed on someone else's order
        $legacy = $this->makePendingOrder($me, $this->event, 1);
        $legacy->forceFill(['user_id' => null])->save();
        $legacy->customer->update(['email' => 'ME@example.test']);              // no account at all, same verified e-mail
        $unclaimed = $this->makePendingOrder($me, $this->event, 1);
        $unclaimed->forceFill(['owner_unverified' => true])->save();

        $ids = Order::query()->ownedByCustomer($me)->pluck('id')->all();

        $this->assertContains($mine->id, $ids);
        $this->assertContains($legacy->id, $ids);                                // verified e-mail + orphan order
        $this->assertNotContains($theirs->id, $ids);                             // bound to another account
        $this->assertNotContains($unclaimed->id, $ids);                          // placed before the mailbox was proven

        $me->forceFill(['email_verified_at' => null])->save();
        $this->assertNotContains($legacy->id, Order::query()->ownedByCustomer($me->fresh())->pluck('id')->all());   // unverified e-mail proves nothing
    }

    /* ------------------------------------------------------------ My orders */

    public function test_my_orders_lists_only_owned_orders_with_the_requested_details(): void
    {
        $me = $this->customer();
        $mine = $this->makePendingOrder($me, $this->event, 3);
        $other = $this->makePendingOrder($this->customer(), $this->makeEvent(['name' => 'Someone Elses Party']), 1);
        $unclaimed = $this->makePendingOrder($me, $this->makeEvent(['name' => 'Unclaimed Night']), 1);
        $unclaimed->forceFill(['owner_unverified' => true])->save();

        $this->actingAs($me)->get(route('front.account.orders'))->assertOk()
            ->assertSee('Order '.$mine->order_number)->assertSee('Pay Night')->assertSee('300.00 EGP')->assertSee('Awaiting payment')
            ->assertSee('View details')->assertSee(route('front.account.orders.show', $mine->order_number), false)
            ->assertDontSee($other->order_number)->assertDontSee('Someone Elses Party')->assertDontSee('Unclaimed Night');
    }

    public function test_orders_search_filters_and_pagination(): void
    {
        $me = $this->customer();
        $paid = $this->paid($me, 1, $this->makeEvent(['name' => 'Paid Event']));
        $pending = $this->makePendingOrder($me, $this->makeEvent(['name' => 'Pending Event']), 1);
        $failed = $this->makePendingOrder($me, $this->makeEvent(['name' => 'Failed Event']), 1);
        PaymentAttempt::create(['order_id' => $failed->id, 'provider' => 'fake', 'amount' => 100, 'status' => 'failed']);
        $canceled = $this->makePendingOrder($me, $this->makeEvent(['name' => 'Canceled Event']), 1);
        $canceled->forceFill(['status' => 'canceled'])->save();
        $refunded = $this->makePendingOrder($me, $this->makeEvent(['name' => 'Refunded Event']), 1);
        $refunded->forceFill(['status' => 'refunded'])->save();

        $see = fn (array $q) => $this->actingAs($me)->get(route('front.account.orders', $q))->assertOk();

        $see(['status' => 'paid'])->assertSee('Paid Event')->assertDontSee('Pending Event')->assertDontSee('Failed Event');
        $see(['status' => 'pending'])->assertSee('Pending Event')->assertDontSee('Failed Event')->assertDontSee('Paid Event');
        $see(['status' => 'failed'])->assertSee('Failed Event')->assertSee('Payment failed')->assertDontSee('Pending Event');
        $see(['status' => 'canceled'])->assertSee('Canceled Event')->assertDontSee('Paid Event');
        $see(['status' => 'refunded'])->assertSee('Refunded Event')->assertDontSee('Canceled Event');
        $see(['q' => $paid->order_number])->assertSee('Paid Event')->assertDontSee('Pending Event');
        $see(['q' => 'pending ev'])->assertSee('Pending Event')->assertDontSee('Paid Event');
        $see(['q' => 'nothing like this'])->assertSee('No orders match your search')->assertSee('Clear filters');
        $see(['status' => 'bogus'])->assertSee('Paid Event');                                  // unknown filter = all

        foreach (range(1, 10) as $i) {
            $this->makePendingOrder($me, $this->event, 1);
        }
        $see([])->assertSee('tkt-pagination', false);
        $this->actingAs($me)->get(route('front.account.orders', ['page' => 2]))->assertOk();
    }

    public function test_an_empty_account_gets_a_helpful_empty_state(): void
    {
        $this->actingAs($this->customer())->get(route('front.account.orders'))->assertOk()->assertSee('No orders yet')->assertSee('Browse events');
        $this->actingAs($this->customer())->get(route('front.account.tickets'))->assertOk()->assertSee('No upcoming tickets');
    }

    /* ------------------------------------------------------------ Order details */

    public function test_order_details_are_private(): void
    {
        $me = $this->customer();
        $mine = $this->makePendingOrder($me, $this->event, 1);
        $theirs = $this->makePendingOrder($this->customer(), $this->event, 1);
        $unclaimed = $this->makePendingOrder($me, $this->event, 1);
        $unclaimed->forceFill(['owner_unverified' => true])->save();

        $this->get(route('front.account.orders.show', $mine->order_number))->assertRedirect();                                   // guests are sent to sign in
        $this->actingAs($me)->get(route('front.account.orders.show', $mine->order_number))->assertOk();
        $this->actingAs($me)->get(route('front.account.orders.show', $theirs->order_number))->assertNotFound();
        $this->actingAs($me)->get(route('front.account.orders.show', $unclaimed->order_number))->assertNotFound();
    }

    public function test_a_pending_order_offers_the_existing_payment_path_and_a_failed_one_a_retry(): void
    {
        $me = $this->customer();
        $order = $this->makePendingOrder($me, $this->event, 2);
        $link = route('front.orders.payment', ['order' => $order, 'token' => $order->payment_link_token]);

        $this->actingAs($me)->get(route('front.account.orders.show', $order->order_number))->assertOk()
            ->assertSee('waiting for payment')->assertSee('Complete payment')->assertSee($link, false)->assertDontSee('View ticket');

        PaymentAttempt::create(['order_id' => $order->id, 'provider' => 'fake', 'amount' => 200, 'status' => 'failed']);
        $this->actingAs($me)->get(route('front.account.orders.show', $order->order_number))->assertOk()
            ->assertSee('did not go through')->assertSee('Try payment again')->assertSee($link, false);

        $this->actingAs($me)->get($link)->assertOk();                                      // the owner is let through by the same safe path
    }

    public function test_a_paid_order_shows_tickets_attendees_and_the_payment_breakdown(): void
    {
        $me = $this->customer();
        $order = $this->paid($me, 2);
        $order->forceFill(['subtotal_amount' => 200, 'discount_amount' => 20, 'total_amount' => 195, 'promo_code' => 'SAVE10'])->save();
        $ticket = IssuedTicket::where('order_id', $order->id)->firstOrFail();

        $this->actingAs($me)->get(route('front.account.orders.show', $order->order_number))->assertOk()
            ->assertSee('Holder 1')->assertSee('Holder 2')->assertSee('View ticket')->assertSee(route('front.tickets.show', $ticket), false)
            ->assertSee('200.00 EGP')->assertSee('−20.00 EGP')->assertSee('SAVE10')->assertSee('15.00 EGP')->assertSee('195.00 EGP')->assertSee('Paid');
    }

    public function test_other_order_states_use_the_statuses_that_already_exist(): void
    {
        $me = $this->customer();
        foreach (['pending_approval' => 'not charged', 'canceled' => 'is canceled', 'rejected' => 'is rejected', 'refunded' => 'was refunded'] as $status => $text) {
            $o = $this->makePendingOrder($me, $this->event, 1);
            $o->forceFill(['status' => $status])->save();
            $this->actingAs($me)->get(route('front.account.orders.show', $o->order_number))->assertOk()->assertSee($text, false)->assertDontSee('Complete payment');
        }
    }

    /* ------------------------------------------------------------ My tickets */

    public function test_my_tickets_only_shows_paid_owned_tickets_with_filters(): void
    {
        $me = $this->customer();
        $future = $this->paid($me, 2);
        $past = $this->paid($me, 1, $this->makeEvent(['name' => 'Old Night', 'event_date' => now()->subDays(5)->toDateString()]));
        $unpaid = $this->makePendingOrder($me, $this->makeEvent(['name' => 'Unpaid Night']), 1);
        $theirs = $this->paid($this->customer(), 1, $this->makeEvent(['name' => 'Their Night']));
        $unclaimed = $this->paid($me, 1, $this->makeEvent(['name' => 'Unclaimed Night']));
        $unclaimed->forceFill(['owner_unverified' => true])->save();

        $page = fn (string $when) => $this->actingAs($me)->get(route('front.account.tickets', ['when' => $when]))->assertOk();

        $page('upcoming')->assertSee('Pay Night')->assertSee('Holder 1')->assertSee('api.qrserver.com', false)
            ->assertDontSee('Old Night')->assertDontSee('Unpaid Night')->assertDontSee('Their Night')->assertDontSee('Unclaimed Night');
        $page('past')->assertSee('Old Night')->assertDontSee('Pay Night');
        $page('all')->assertSee('Old Night')->assertSee('Pay Night')->assertDontSee('Their Night')->assertDontSee('Unpaid Night');
        $this->actingAs($me)->get(route('front.account.tickets'))->assertOk()->assertSee('Pay Night')->assertDontSee('Old Night');   // default = upcoming
    }

    public function test_qr_codes_show_only_for_tickets_that_can_still_be_scanned(): void
    {
        $me = $this->customer();
        $order = $this->paid($me, 3);
        $tickets = IssuedTicket::where('order_id', $order->id)->orderBy('id')->get();
        Ticket::where('ticket_number', $tickets[1]->ticket_number)->update(['status' => 'checked_in']);
        Ticket::where('ticket_number', $tickets[2]->ticket_number)->update(['status' => 'canceled']);

        $html = $this->actingAs($me)->get(route('front.account.tickets', ['when' => 'all']))->assertOk()->assertSee('Checked in')->assertSee('Canceled')->assertSee('Valid')->getContent();

        $this->assertStringContainsString(urlencode($tickets[0]->ticket_number), $html);                  // valid: QR shown
        $this->assertStringNotContainsString('data='.urlencode($tickets[1]->ticket_number), $html);       // used: no QR
        $this->assertStringNotContainsString('data='.urlencode($tickets[2]->ticket_number), $html);       // canceled: no QR

        $this->actingAs($me)->get(route('front.tickets.show', $tickets[0]))->assertOk()->assertSee('Show this code at the entrance')->assertSee('api.qrserver.com', false);
        $this->actingAs($me)->get(route('front.tickets.show', $tickets[1]))->assertOk()->assertSee('already been scanned')->assertDontSee('api.qrserver.com', false);
        $this->actingAs($me)->get(route('front.tickets.show', $tickets[2]))->assertOk()->assertSee('was canceled')->assertDontSee('api.qrserver.com', false);
    }

    /* ------------------------------------------------------------ Ticket details + PDF */

    public function test_ticket_pages_and_pdf_follow_the_ownership_rules(): void
    {
        $me = $this->customer();
        $ticket = IssuedTicket::where('order_id', $this->paid($me, 1)->id)->firstOrFail();
        $stranger = $this->customer();
        $unclaimedOrder = $this->paid($me, 1);
        $unclaimedOrder->forceFill(['owner_unverified' => true])->save();
        $unclaimed = IssuedTicket::where('order_id', $unclaimedOrder->id)->firstOrFail();

        $this->get(route('front.tickets.show', $ticket))->assertRedirect();
        $this->actingAs($stranger)->get(route('front.tickets.show', $ticket))->assertForbidden();
        $this->actingAs($stranger)->get(route('front.tickets.download', $ticket))->assertForbidden();
        $this->actingAs($me)->get(route('front.tickets.show', $unclaimed))->assertForbidden();
        $this->actingAs($me)->get(route('front.tickets.download', $unclaimed))->assertForbidden();

        $this->actingAs($me)->get(route('front.tickets.show', $ticket))->assertOk()
            ->assertSee('Holder 1')->assertSee('#'.$ticket->ticket_number)->assertSee('Download PDF')->assertSee('Pay Night');
        $pdf = $this->actingAs($me)->get(route('front.tickets.download', $ticket))->assertOk();
        $this->assertStringContainsString('pdf', (string) $pdf->headers->get('content-type'));
    }

    public function test_a_verified_holder_can_open_a_gifted_ticket_but_an_unverified_address_cannot(): void
    {
        $buyer = $this->customer();
        $ticket = IssuedTicket::where('order_id', $this->paid($buyer, 1)->id)->firstOrFail();
        $ticket->update(['holder_email' => 'friend@example.test']);

        $friend = $this->customer(['email' => 'friend@example.test']);
        $this->actingAs($friend)->get(route('front.tickets.show', $ticket))->assertOk();
        $this->actingAs($friend)->get(route('front.account.tickets', ['when' => 'all']))->assertOk()->assertSee('Holder 1');

        $friend->forceFill(['email_verified_at' => null])->save();
        $this->actingAs($friend->fresh())->get(route('front.tickets.show', $ticket))->assertForbidden();
    }

    /* ------------------------------------------------------------ My account */

    public function test_the_account_page_supports_passwordless_accounts_and_verification_state(): void
    {
        $auto = $this->customer(['passwordless_since' => now(), 'email' => 'auto@example.test']);
        $this->actingAs($auto)->get(route('front.account.profile'))->assertOk()
            ->assertSee('auto@example.test')->assertSee('Verified')->assertSee('no password needed')->assertSee('Set a password (optional)')
            ->assertDontSee('type="password" id="cur_password"', false);

        $withPassword = $this->customer(['email' => 'pw@example.test', 'email_verified_at' => null]);
        $this->actingAs($withPassword)->get(route('front.account.profile'))->assertOk()->assertSee('Not verified')->assertSee('Current password')->assertDontSee('Set a password (optional)');
    }

    public function test_profile_update_changes_name_and_phone_but_never_the_email(): void
    {
        $me = $this->customer(['email' => 'keep@example.test', 'name' => 'Old Name']);

        $this->actingAs($me)->put(route('front.account.profile.update'), ['name' => 'New Name', 'phone' => '+201000000001', 'email' => 'evil@example.test', 'username' => 'hacker'])
            ->assertRedirect(route('front.account.profile'));

        $me->refresh();
        $this->assertSame('New Name', $me->name);
        $this->assertSame('+201000000001', $me->phone);
        $this->assertSame('keep@example.test', $me->email);
        $this->assertNotSame('hacker', $me->username);
        $this->actingAs($me)->put(route('front.account.profile.update'), ['name' => ''])->assertSessionHasErrors('name');
    }

    public function test_changing_the_email_needs_proof_of_the_person_and_of_the_new_mailbox(): void
    {
        $me = $this->customer(['email' => 'old@example.test', 'password' => Hash::make('right-password')]);
        $order = $this->makePendingOrder($me, $this->event, 1);

        // password accounts must confirm the password
        $this->actingAs($me)->postJson(route('front.account.email.request'), ['email' => 'new@example.test', 'current_password' => 'wrong'])->assertStatus(422);
        Mail::assertNothingSent();

        $this->postJson(route('front.account.email.request'), ['email' => 'new@example.test', 'current_password' => 'right-password'])->assertOk();
        Mail::assertSent(LoginOtpMail::class, fn ($m) => $m->hasTo('new@example.test'));
        Mail::assertNotSent(LoginOtpMail::class, fn ($m) => $m->hasTo('old@example.test'));
        $this->assertSame('old@example.test', $me->fresh()->email);                       // nothing changed yet

        $code = null;
        Mail::assertSent(LoginOtpMail::class, function ($m) use (&$code) { $code = $m->otpCode; return true; });

        $this->postJson(route('front.account.email.confirm'), ['code' => '000000'])->assertStatus(422);
        $this->assertSame('old@example.test', $me->fresh()->email);

        $this->postJson(route('front.account.email.confirm'), ['code' => $code])->assertOk()->assertJson(['ok' => true, 'email' => 'new@example.test']);
        $me->refresh();
        $this->assertSame('new@example.test', $me->email);
        $this->assertNotNull($me->email_verified_at);
        $this->assertSame($me->id, $order->fresh()->user_id);                             // ownership stays with the account
        $this->postJson(route('front.account.email.confirm'), ['code' => $code])->assertStatus(422);   // single use
    }

    public function test_email_change_locks_after_wrong_guesses_and_never_reveals_existing_addresses(): void
    {
        $me = $this->customer(['email' => 'old@example.test', 'password' => Hash::make('pw-123456')]);
        $this->customer(['email' => 'taken@example.test']);

        $this->actingAs($me)->postJson(route('front.account.email.request'), ['email' => 'taken@example.test', 'current_password' => 'pw-123456'])
            ->assertOk()->assertJsonPath('message', fn ($m) => str_contains($m, 'If this address can be used'));
        Mail::assertNothingSent();                                                          // taken: same answer, no mail
        $this->postJson(route('front.account.email.confirm'), ['code' => '123456'])->assertStatus(422);

        $this->postJson(route('front.account.email.request'), ['email' => 'free@example.test', 'current_password' => 'pw-123456'])->assertOk();
        $code = null;
        Mail::assertSent(LoginOtpMail::class, function ($m) use (&$code) { $code = $m->otpCode; return true; });
        foreach (range(1, 5) as $i) {
            $this->postJson(route('front.account.email.confirm'), ['code' => '111111'])->assertStatus(422);
        }
        $this->postJson(route('front.account.email.confirm'), ['code' => $code])->assertStatus(422);   // locked out even with the right code
        $this->assertSame('old@example.test', $me->fresh()->email);
    }

    public function test_passwordless_accounts_need_a_recent_code_sign_in_to_change_their_email(): void
    {
        $me = $this->customer(['email' => 'auto@example.test', 'passwordless_since' => now()]);

        $this->actingAs($me)->postJson(route('front.account.email.request'), ['email' => 'new@example.test'])->assertStatus(403);
        Mail::assertNothingSent();

        $this->withSession(['auth.otp_verified_at' => now()->timestamp])->postJson(route('front.account.email.request'), ['email' => 'new@example.test'])->assertOk();
        Mail::assertSent(LoginOtpMail::class, fn ($m) => $m->hasTo('new@example.test'));
        $this->assertSame('auto@example.test', $me->fresh()->email);
    }

    public function test_staff_accounts_cannot_use_the_customer_email_change(): void
    {
        Role::findOrCreate('admin', 'web');
        $staff = $this->customer(['email' => 'boss@example.test']);
        $staff->assignRole('admin');

        $this->actingAs($staff)->postJson(route('front.account.email.request'), ['email' => 'x@example.test', 'current_password' => 'x'])->assertStatus(403);
    }

    public function test_rate_limit_on_email_change_requests(): void
    {
        $me = $this->customer(['email' => 'old@example.test', 'passwordless_since' => now()]);
        $this->actingAs($me)->withSession(['auth.otp_verified_at' => now()->timestamp]);
        Cache::flush();

        foreach (range(1, 5) as $i) {
            $this->postJson(route('front.account.email.request'), ['email' => "n$i@example.test"])->assertOk();
        }
        $this->postJson(route('front.account.email.request'), ['email' => 'n6@example.test'])->assertStatus(429);
    }

    /* ------------------------------------------------------------ whole journey */

    public function test_the_customer_journey_login_orders_details_tickets_ticket_pdf(): void
    {
        $me = $this->customer(['email' => 'journey@example.test', 'passwordless_since' => now()]);
        $order = $this->paid($me, 2);

        // e-mail code sign-in
        $this->postJson(route('front.auth.otp.send'), ['email' => 'journey@example.test'])->assertOk();
        $code = null;
        Mail::assertSent(LoginOtpMail::class, function ($m) use (&$code) { $code = $m->otpCode; return true; });
        $this->postJson(route('front.auth.otp.verify'), ['email' => 'journey@example.test', 'code' => $code])->assertOk();

        $this->get(route('front.account.orders'))->assertOk()->assertSee($order->order_number);
        $this->get(route('front.account.orders.show', $order->order_number))->assertOk()->assertSee('Holder 1');
        $this->get(route('front.account.tickets'))->assertOk()->assertSee('Holder 2');
        $ticket = IssuedTicket::where('order_id', $order->id)->firstOrFail();
        $this->get(route('front.tickets.show', $ticket))->assertOk()->assertSee('Show this code at the entrance');

        $pdf = $this->get(route('front.tickets.download', $ticket))->assertOk();
        $this->assertStringContainsString('pdf', (string) $pdf->headers->get('content-type'));
    }
}
