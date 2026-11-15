<?php

namespace Tests\Feature;

use App\Http\Middleware\EnsureAdminPanelAccess;
use App\Mail\HolderTicketsIssuedMail;
use App\Mail\LoginOtpMail;
use App\Mail\OrderTicketsIssuedMail;
use App\Models\Event;
use App\Models\IssuedTicket;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Ticket;
use App\Models\User;
use App\Services\TicketIssuanceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Permission;
use Tests\Support\BuildsOrders;
use Tests\Support\PinnedSessionManager;
use Tests\Support\PinnedSessionStore;
use Tests\TestCase;

/** Buyer vs ticket holder: one order, several people, each with their own ticket; the buyer keeps the order. */
class MultiTicketOwnershipTest extends TestCase
{
    use BuildsOrders, RefreshDatabase;

    private Event $event;
    private User $ahmed;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        Http::preventStrayRequests();
        $this->app->singleton('session', fn ($app) => new PinnedSessionManager($app));
        $this->app->forgetInstance('session.store');
        PinnedSessionStore::$pin = substr(hash('sha256', 'multi'), 0, 40);
        $this->app['session.store']->flush();
        $this->event = $this->makeEvent(['name' => 'Gate Night']);
        $this->ahmed = User::factory()->create(['name' => 'Ahmed', 'email' => 'ahmed@example.com', 'email_verified_at' => now(), 'passwordless_since' => now()]);
    }

    /** One paid order of $holders = [[name, email], …] bought by $buyer. */
    private function order(array $holders, ?User $buyer = null, ?Event $event = null): Order
    {
        $buyer ??= $this->ahmed;
        $order = $this->makePendingOrder($buyer, $event ?? $this->event, count($holders));
        $order->customer->update(['email' => $buyer->email]);
        foreach ($order->items()->orderBy('id')->get() as $i => $item) {
            $item->update(['holder_name' => $holders[$i][0], 'holder_email' => $holders[$i][1]]);
        }
        $order->forceFill(['status' => 'paid', 'paid_at' => now()])->save();
        app(TicketIssuanceService::class)->issueIfPaid($order->fresh());

        return $order->fresh();
    }

    private function threeTickets(): array
    {
        $order = $this->order([['Ahmed', 'ahmed@example.com'], ['Mohamed', 'mohamed@example.com'], ['Omar', 'omar@example.com']]);
        $t = IssuedTicket::where('order_id', $order->id)->orderBy('id')->get();

        return [$order, $t[0], $t[1], $t[2]];
    }

    private function dash(IssuedTicket $t): Ticket
    {
        return Ticket::where('ticket_number', $t->ticket_number)->firstOrFail();
    }

    private function codeLogin(string $email): void
    {
        $this->postJson(route('front.tickets.access.send'), ['email' => $email])->assertOk();
        $code = null;
        Mail::assertSent(LoginOtpMail::class, function ($m) use (&$code, $email) { if ($m->hasTo($email)) { $code = $m->otpCode; } return true; });
        $this->postJson(route('front.auth.otp.verify'), ['email' => $email, 'code' => $code])->assertOk();
    }

    private function staff(array $perms): User
    {
        $this->withoutMiddleware(EnsureAdminPanelAccess::class);
        foreach ($perms as $p) {
            Permission::findOrCreate($p, 'web');
        }

        return tap(User::factory()->create())->givePermissionTo($perms);
    }

    /* ------------------------------------------------------------ e-mails */

    public function test_the_buyer_gets_one_confirmation_and_each_other_holder_a_private_e_mail_without_order_or_payment_data(): void
    {
        [$order] = $this->threeTickets();

        Mail::assertSent(OrderTicketsIssuedMail::class, 1);
        Mail::assertSent(OrderTicketsIssuedMail::class, fn ($m) => $m->hasTo('ahmed@example.com'));
        Mail::assertSent(HolderTicketsIssuedMail::class, 2);          // Mohamed and Omar; Ahmed's own ticket is covered by his confirmation e-mail
        Mail::assertNotSent(HolderTicketsIssuedMail::class, fn ($m) => $m->hasTo('ahmed@example.com'));

        foreach (['mohamed@example.com' => 'Mohamed', 'omar@example.com' => 'Omar'] as $to => $name) {
            Mail::assertSent(HolderTicketsIssuedMail::class, function (HolderTicketsIssuedMail $m) use ($to, $name, $order) {
                if (! $m->hasTo($to)) {
                    return false;
                }
                $html = html_entity_decode($m->render());
                $this->assertSame(1, $m->tickets->count(), 'only their own ticket');
                $this->assertSame([], $m->attachments);
                $this->assertSame([], $m->rawAttachments);
                $this->assertStringContainsStringIgnoringCase('View my ticket', $html);
                $this->assertStringContainsString(route('front.tickets.show', $m->tickets->first()), $html);
                foreach ([$order->order_number, 'INV-', 'Invoice', 'EGP', 'Ahmed', 'ahmed@example.com'] as $private) {
                    $this->assertStringNotContainsString($private, $html, "holder e-mail leaks: $private");
                }
                $this->assertStringNotContainsString('data:image', $html);

                return true;
            });
        }
        $this->assertNotNull(IssuedTicket::where('holder_email', 'mohamed@example.com')->first()->sent_at);
    }

    public function test_tickets_with_the_same_holder_e_mail_travel_in_one_message(): void
    {
        $this->order([['Ahmed', 'ahmed@example.com'], ['Omar', 'omar@example.com'], ['Omar Two', 'OMAR@example.com']]);

        Mail::assertSent(HolderTicketsIssuedMail::class, 1);
        Mail::assertSent(HolderTicketsIssuedMail::class, fn ($m) => $m->hasTo('omar@example.com') && $m->tickets->count() === 2);
    }

    /* ------------------------------------------------------------ buyer view vs holder view */

    public function test_the_buyer_sees_the_whole_order_and_only_their_own_ticket_under_my_tickets(): void
    {
        [$order, $mine, $mohamed, $omar] = $this->threeTickets();
        $this->actingAs($this->ahmed);

        $this->get(route('front.account.orders.show', $order->order_number))->assertOk()
            ->assertSee('Mohamed')->assertSee('mohamed@example.com')->assertSee('Omar')->assertSee('omar@example.com')->assertSee('Resend link')->assertSee('Edit holder')
            ->assertSee(route('front.tickets.show', $mohamed), false)->assertSee('Link sent');

        // My Tickets: just Ahmed's, never listed twice, with a pointer to the two bought for others.
        $page = $this->get(route('front.account.tickets', ['when' => 'all']))->assertOk()->assertSee('Ahmed')->assertSee('bought', false)->assertSee('for other people');
        $this->assertSame(1, substr_count($page->getContent(), 'tkt-tcard"'));
        $this->assertStringNotContainsString('Mohamed', $page->getContent());

        // The order's own list (the e-mail button) shows all three.
        $this->get(route('front.account.tickets', ['order' => $order->order_number, 'when' => 'all']))->assertOk()->assertSee('Ahmed')->assertSee('Mohamed')->assertSee('Omar');
        // The buyer can open every ticket of the order and sees its order link.
        $this->get(route('front.tickets.show', $omar))->assertOk()->assertSee('Omar')->assertSee('#'.$order->order_number);
    }

    public function test_a_holder_sees_only_their_ticket_and_nothing_of_the_buyers_order_or_payment(): void
    {
        [$order, $ahmedT, $mohamed, $omar] = $this->threeTickets();
        $this->codeLogin('mohamed@example.com');    // no account existed: a passwordless one is created once the code proves the mailbox

        $page = $this->get(route('front.tickets.show', $mohamed))->assertOk()->assertSee('Mohamed')->getContent();
        $this->assertStringNotContainsString($order->order_number, $page);
        $this->assertStringNotContainsString('/account/orders/', $page);
        $this->assertStringNotContainsString('Ahmed', $page);
        $this->assertStringNotContainsString('Ticket 1 of', $page);

        $this->get(route('front.tickets.show', $omar))->assertForbidden();                 // somebody else's ticket, even with its link
        $this->get(route('front.tickets.show', $ahmedT))->assertForbidden();
        $this->getJson(route('front.tickets.qr', $omar))->assertForbidden();
        $this->get(route('front.account.orders.show', $order->order_number))->assertNotFound();
        $this->get(route('front.account.orders.invoice', $order->order_number))->assertNotFound();
        $this->get(route('front.account.orders'))->assertOk()->assertDontSee($order->order_number);
        $list = $this->get(route('front.account.tickets', ['when' => 'all']))->assertOk()->assertSee('Mohamed')->getContent();
        $this->assertStringNotContainsString('Omar', $list);
        $this->get(route('front.account.tickets', ['order' => $order->order_number, 'when' => 'all']))->assertOk()->assertDontSee('Omar')->assertDontSee('Ahmed');
        $this->post(route('front.account.tickets.resend', [$order->order_number, $mohamed->uuid]))->assertNotFound();     // a holder cannot manage the order
        $this->post(route('front.account.tickets.holder.update', [$order->order_number, $mohamed->uuid]), ['holder_name' => 'X', 'holder_email' => 'x@example.com'])->assertNotFound();
    }

    public function test_one_holder_with_tickets_from_two_orders_sees_each_once(): void
    {
        $this->order([['Ahmed', 'ahmed@example.com'], ['Mohamed', 'mohamed@example.com']]);
        $other = User::factory()->create(['email' => 'sara@example.com', 'email_verified_at' => now()]);
        $this->order([['Sara', 'sara@example.com'], ['Mohamed', 'mohamed@example.com']], $other);
        $this->codeLogin('mohamed@example.com');

        $html = $this->get(route('front.account.tickets', ['when' => 'all']))->assertOk()->getContent();
        $this->assertSame(2, substr_count($html, 'tkt-tcard"'));
    }

    public function test_a_guest_buyer_who_bought_everything_for_others_manages_the_order_after_confirming_their_e_mail(): void
    {
        $guest = User::factory()->create(['email' => 'guest@example.com', 'email_verified_at' => null, 'passwordless_since' => now()]);
        $order = $this->order([['Mohamed', 'mohamed@example.com'], ['Omar', 'omar@example.com']], $guest);
        $order->forceFill(['owner_unverified' => true])->save();
        $mohamed = IssuedTicket::where('holder_email', 'mohamed@example.com')->first();

        $this->codeLogin('mohamed@example.com');                                            // the holder gets in on their own...
        $this->get(route('front.tickets.show', $mohamed))->assertOk();
        $this->get(route('front.account.orders.show', $order->order_number))->assertNotFound();   // ...and still never sees the order
        $this->post(route('front.customer.logout'));

        $this->codeLogin('guest@example.com');                                              // the buyer verifies once and owns it
        $this->get(route('front.account.orders.show', $order->order_number))->assertOk()->assertSee('Mohamed')->assertSee('Omar');
        $this->get(route('front.account.tickets', ['when' => 'all']))->assertOk()->assertSee('bought', false);   // none is theirs
    }

    /* ------------------------------------------------------------ editing holders */

    public function test_changing_the_e_mail_moves_the_ticket_revokes_the_old_address_invites_the_new_one_and_keeps_the_qr(): void
    {
        [$order, $ahmedT, $mohamed] = $this->threeTickets();
        $qr = $this->dash($mohamed)->qr_payload;
        $old = User::where('email', 'mohamed@example.com')->first() ?? $this->codeLoginAndUser('mohamed@example.com');
        $this->get(route('front.tickets.show', $mohamed))->assertOk();
        $this->post(route('front.customer.logout'));
        Mail::fake();

        $this->actingAs($this->ahmed)->post(route('front.account.tickets.holder.update', [$order->order_number, $mohamed->uuid]), ['holder_name' => 'Mohamed Ali', 'holder_email' => 'New@Example.com'])
            ->assertRedirect()->assertSessionHas('success');

        $m = $mohamed->fresh();
        $this->assertSame(['Mohamed Ali', 'new@example.com', 1], [$m->holder_name, $m->holder_email, $m->holder_email_changes]);
        $d = $this->dash($m);
        $this->assertSame(['Mohamed Ali', 'new@example.com'], [$d->holder_name, $d->holder_email]);
        $this->assertSame($qr, $d->qr_payload, 'no new QR is created');
        Mail::assertSent(HolderTicketsIssuedMail::class, 1);
        Mail::assertSent(HolderTicketsIssuedMail::class, fn ($mail) => $mail->hasTo('new@example.com') && $mail->tickets->count() === 1);
        $this->assertNotNull($m->sent_at);
        $this->assertSame(1, Activity::where('description', 'Ticket holder changed (e-mail moved)')->where('causer_id', $this->ahmed->id)->count());

        // The previous address lost access (even in a session that had it); the new one can get in.
        $this->actingAs($old->fresh())->get(route('front.tickets.show', $mohamed))->assertForbidden();
        $this->actingAs($old->fresh())->getJson(route('front.tickets.qr', $mohamed))->assertForbidden();
        $this->actingAs($old->fresh())->get(route('front.account.tickets', ['when' => 'all']))->assertOk()->assertDontSee('Mohamed Ali');
        $this->app['auth']->forgetGuards();
        $this->codeLogin('new@example.com');
        $this->get(route('front.tickets.show', $mohamed))->assertOk()->assertSee('Mohamed Ali');
        $this->assertSame($qr, $this->dash($mohamed)->qr_payload);
    }

    private function codeLoginAndUser(string $email): User
    {
        $this->codeLogin($email);

        return User::where('email', $email)->firstOrFail();
    }

    public function test_a_name_change_needs_no_e_mail_and_does_not_use_up_a_move(): void
    {
        [$order, , $mohamed] = $this->threeTickets();
        Mail::fake();

        $this->actingAs($this->ahmed)->post(route('front.account.tickets.holder.update', [$order->order_number, $mohamed->uuid]), ['holder_name' => 'Mo Ali', 'holder_email' => 'mohamed@example.com'])->assertSessionHas('success');

        $this->assertSame(['Mo Ali', 0], [$mohamed->fresh()->holder_name, (int) $mohamed->fresh()->holder_email_changes]);
        Mail::assertNothingSent();
    }

    public function test_a_ticket_can_be_moved_three_times_then_the_buyer_must_contact_support(): void
    {
        [$order, , $mohamed] = $this->threeTickets();
        $this->actingAs($this->ahmed);
        foreach (['a@example.com', 'b@example.com', 'c@example.com'] as $e) {
            $this->post(route('front.account.tickets.holder.update', [$order->order_number, $mohamed->uuid]), ['holder_name' => 'M', 'holder_email' => $e])->assertSessionHas('success');
        }
        $this->post(route('front.account.tickets.holder.update', [$order->order_number, $mohamed->uuid]), ['holder_name' => 'M', 'holder_email' => 'd@example.com'])->assertSessionHasErrors('holder_email');
        $this->assertSame('c@example.com', $mohamed->fresh()->holder_email);
    }

    public function test_used_canceled_and_refunded_tickets_cannot_be_changed_or_re_sent(): void
    {
        [$order, , $mohamed, $omar] = $this->threeTickets();
        $this->dash($mohamed)->update(['status' => 'checked_in']);
        $this->dash($omar)->update(['status' => 'canceled', 'canceled_at' => now(), 'cancellation_source' => 'refund']);
        Mail::fake();
        $this->actingAs($this->ahmed);

        foreach ([$mohamed, $omar] as $t) {
            $this->post(route('front.account.tickets.holder.update', [$order->order_number, $t->uuid]), ['holder_name' => 'X', 'holder_email' => 'x@example.com'])->assertSessionHasErrors('holder');
            $this->post(route('front.account.tickets.resend', [$order->order_number, $t->uuid]))->assertSessionHas('error');
        }
        $this->assertSame(['mohamed@example.com', 'omar@example.com'], [$mohamed->fresh()->holder_email, $omar->fresh()->holder_email]);
        Mail::assertNothingSent();

        $this->get(route('front.account.orders.show', $order->order_number))->assertOk()->assertSee('already used')->assertSee('refunded');
    }

    public function test_resend_sends_the_link_again_with_a_limit_and_only_the_buyer_can_do_it(): void
    {
        [$order, , $mohamed] = $this->threeTickets();
        Mail::fake();
        $stranger = User::factory()->create(['email_verified_at' => now()]);

        $this->actingAs($stranger)->post(route('front.account.tickets.resend', [$order->order_number, $mohamed->uuid]))->assertNotFound();
        $this->actingAs($this->ahmed);
        for ($i = 0; $i < 3; $i++) {
            $this->post(route('front.account.tickets.resend', [$order->order_number, $mohamed->uuid]))->assertSessionHas('success');
        }
        $this->post(route('front.account.tickets.resend', [$order->order_number, $mohamed->uuid]))->assertSessionHas('error');
        Mail::assertSent(HolderTicketsIssuedMail::class, 3);
        Mail::assertSent(HolderTicketsIssuedMail::class, fn ($m) => $m->hasTo('mohamed@example.com'));
        $this->assertSame(3, Activity::where('description', 'Ticket link re-sent to the holder')->count());
    }

    public function test_invalid_holder_data_is_rejected(): void
    {
        [$order, , $mohamed] = $this->threeTickets();
        $this->actingAs($this->ahmed)->post(route('front.account.tickets.holder.update', [$order->order_number, $mohamed->uuid]), ['holder_name' => '', 'holder_email' => 'not-an-email'])->assertSessionHasErrors(['holder_name', 'holder_email']);
        $this->assertSame('mohamed@example.com', $mohamed->fresh()->holder_email);
    }

    /* ------------------------------------------------------------ one QR, one entry; refunds; visibility */

    public function test_the_first_check_in_wins_for_buyer_and_holder_and_both_then_see_used(): void
    {
        [$order, , $mohamed] = $this->threeTickets();
        $scanner = $this->staff(['scanner.access']);
        $dash = $this->dash($mohamed);

        $this->actingAs($scanner)->post(route('admin.tickets.scanner.status', $dash), ['status' => 'checked_in'])->assertSessionHas('success');
        $this->actingAs($scanner)->post(route('admin.tickets.scanner.status', $dash), ['status' => 'checked_in'])->assertSessionHas('error', fn ($m) => str_contains($m, 'ALREADY CHECKED IN'));

        $this->actingAs($this->ahmed)->getJson(route('front.tickets.qr', $mohamed))->assertJson(['state' => 'used', 'svg' => null]);
        $this->app['auth']->forgetGuards();
        $this->codeLogin('mohamed@example.com');
        $this->getJson(route('front.tickets.qr', $mohamed))->assertJson(['state' => 'used', 'svg' => null]);
        $this->get(route('front.tickets.show', $mohamed))->assertOk()->assertSee('already been scanned');
    }

    public function test_partial_refund_cancels_only_that_ticket_for_buyer_and_holder_while_the_others_keep_working(): void
    {
        [$order, $ahmedT, $mohamed, $omar] = $this->threeTickets();
        $this->dash($omar)->update(['status' => 'canceled', 'canceled_at' => now(), 'cancellation_source' => 'refund']);
        $order->forceFill(['status' => 'partially_refunded'])->save();

        $this->actingAs($this->ahmed)->getJson(route('front.tickets.qr', $omar))->assertJson(['state' => 'canceled', 'refunded' => true, 'svg' => null]);
        $this->getJson(route('front.tickets.qr', $mohamed))->assertJson(['state' => 'visible']);
        $this->getJson(route('front.tickets.qr', $ahmedT))->assertJson(['state' => 'visible']);
        $this->app['auth']->forgetGuards();
        $this->codeLogin('omar@example.com');
        $this->getJson(route('front.tickets.qr', $omar))->assertJson(['state' => 'canceled', 'svg' => null]);
        $this->get(route('front.tickets.show', $omar))->assertOk()->assertSee('refunded');
    }

    public function test_qr_visibility_applies_to_buyer_and_holder_alike(): void
    {
        [, $ahmedT, $mohamed] = $this->threeTickets();
        $this->event->forceFill(['qr_visibility_mode' => 'manual'])->save();

        foreach ([$ahmedT, $mohamed] as $t) {
            $this->actingAs($this->ahmed)->getJson(route('front.tickets.qr', $t))->assertJson(['state' => 'locked', 'svg' => null]);
        }
        $this->app['auth']->forgetGuards();
        $this->codeLogin('mohamed@example.com');
        $this->getJson(route('front.tickets.qr', $mohamed))->assertJson(['state' => 'locked', 'svg' => null]);
        $this->event->forceFill(['qr_override' => 'show'])->save();
        $this->getJson(route('front.tickets.qr', $mohamed))->assertJson(['state' => 'visible']);
    }

    public function test_the_invoice_goes_only_to_the_buyer_and_stays_a_pdf(): void
    {
        [$order] = $this->threeTickets();
        Mail::assertSent(OrderTicketsIssuedMail::class, function ($m) {
            $names = collect($m->attachments())->map(fn ($a) => (string) $a->as)->all();
            $this->assertCount(1, $names);
            $this->assertStringStartsWith('invoice-', $names[0]);

            return true;
        });
        $res = $this->actingAs($this->ahmed)->get(route('front.account.orders.invoice', $order->order_number))->assertOk();
        $this->assertStringStartsWith('%PDF', $res->getContent());
    }
}
