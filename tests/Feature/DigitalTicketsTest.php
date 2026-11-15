<?php

namespace Tests\Feature;

use App\Http\Middleware\EnsureAdminPanelAccess;
use App\Mail\HolderTicketsIssuedMail;
use App\Mail\LoginOtpMail;
use App\Mail\OrderTicketsIssuedMail;
use App\Models\Event;
use App\Models\IssuedTicket;
use App\Models\Order;
use App\Models\ScanLog;
use App\Models\Ticket;
use App\Models\User;
use App\Services\TicketIssuanceService;
use App\Support\TicketQr;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Permission;
use Tests\Support\BuildsOrders;
use Tests\Support\PinnedSessionManager;
use Tests\Support\PinnedSessionStore;
use Tests\TestCase;

/** Digital tickets: no ticket PDFs, QR visibility per event, access for buyers / guests / holders, and a scanner that enforces it all server-side. */
class DigitalTicketsTest extends TestCase
{
    use BuildsOrders, RefreshDatabase;

    private Event $event;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        Http::preventStrayRequests();
        $this->app->singleton('session', fn ($app) => new PinnedSessionManager($app));
        $this->app->forgetInstance('session.store');
        PinnedSessionStore::$pin = substr(hash('sha256', 'digital'), 0, 40);
        $this->app['session.store']->flush();
        $this->event = $this->makeEvent(); // starts in 5 days at 21:00
    }

    private function customer(array $o = []): User
    {
        return User::factory()->create(array_merge(['email_verified_at' => now()], $o));
    }

    private function paid(User $user, int $people = 1, ?Event $event = null): Order
    {
        $order = $this->makePendingOrder($user, $event ?? $this->event, $people);
        $order->forceFill(['status' => 'paid', 'paid_at' => now()])->save();
        app(TicketIssuanceService::class)->issueIfPaid($order->fresh());
        // These fixtures model "I bought tickets for myself": the holder is the buyer (My Tickets lists tickets assigned to you).
        IssuedTicket::where('order_id', $order->id)->update(['holder_email' => $user->email]);

        return $order->fresh();
    }

    private function first(Order $order): IssuedTicket
    {
        return IssuedTicket::where('order_id', $order->id)->orderBy('id')->firstOrFail();
    }

    private function dash(IssuedTicket $t): Ticket
    {
        return Ticket::where('ticket_number', $t->ticket_number)->firstOrFail();
    }

    private function staff(array $perms): User
    {
        $this->withoutMiddleware(EnsureAdminPanelAccess::class);
        foreach ($perms as $p) {
            Permission::findOrCreate($p, 'web');
        }

        return tap(User::factory()->create())->givePermissionTo($perms);
    }

    private function setMode(string $mode, ?int $minutes = null, ?string $override = null): void
    {
        $this->event->forceFill(['qr_visibility_mode' => $mode, 'qr_reveal_minutes_before' => $minutes, 'qr_override' => $override])->save();
    }

    /* ------------------------------------------------------------ 1 + 5: e-mails carry no ticket PDF and no QR */

    public function test_booking_e_mail_has_a_view_tickets_button_and_no_ticket_pdf_or_qr(): void
    {
        $me = $this->customer();
        $order = $this->paid($me, 2);

        Mail::assertSent(OrderTicketsIssuedMail::class, function (OrderTicketsIssuedMail $mail) use ($order) {
            $names = collect($mail->attachments())->map(fn ($a) => $a->as)->all();
            foreach ($names as $name) {
                $this->assertStringNotContainsString('tickets-', (string) $name, 'no ticket PDF is attached');
            }

            $html = $mail->render();
            $this->assertStringContainsStringIgnoringCase('View your tickets', $html);
            $this->assertStringContainsString(route('front.account.tickets', ['order' => $order->order_number, 'when' => 'all']), html_entity_decode($html));
            $this->assertStringContainsString('Pay Night', $html);                       // event details
            $this->assertStringContainsString($order->order_number, $html);              // order details
            $this->assertStringNotContainsString('data:image', $html);                   // no QR in the e-mail
            $this->assertStringNotContainsString('qrserver', $html);
            foreach ($order->issuedTickets as $t) {
                $this->assertStringNotContainsString($this->dash($t)->qr_payload, $html);
            }

            return true;
        });
    }

    public function test_a_single_ticket_e_mail_opens_that_ticket_directly_and_holder_mails_have_no_pdf(): void
    {
        $order = $this->paid($this->customer(), 1);
        $ticket = $this->first($order);

        Mail::assertSent(HolderTicketsIssuedMail::class, function (HolderTicketsIssuedMail $mail) use ($ticket) {
            $this->assertSame([], $mail->attachments);
            $this->assertSame([], $mail->rawAttachments);
            $this->assertStringContainsString(route('front.tickets.show', $ticket), html_entity_decode($mail->render()));

            return true;
        });
    }

    public function test_the_invoice_pdf_is_still_attached(): void
    {
        $order = $this->paid($this->customer(), 1);

        Mail::assertSent(OrderTicketsIssuedMail::class, function (OrderTicketsIssuedMail $mail) {
            $names = collect($mail->attachments())->map(fn ($a) => (string) $a->as)->all();
            $this->assertNotEmpty($names, 'the payment invoice stays attached');
            $this->assertStringNotContainsString('tickets-', $names[0]);

            return true;
        });
    }

    /* ------------------------------------------------------------ QR token */

    public function test_new_tickets_carry_an_unguessable_token_not_the_ticket_number(): void
    {
        $order = $this->paid($this->customer(), 3);

        $payloads = IssuedTicket::where('order_id', $order->id)->get()->map(fn ($t) => $this->dash($t)->qr_payload);
        $this->assertCount(3, $payloads->unique());
        foreach ($payloads as $p) {
            $this->assertTrue(TicketQr::isToken($p));
            $this->assertDoesNotMatchRegularExpression('/\d{8,}/', $p);
        }
    }

    /* ------------------------------------------------------------ 3 + 4: visibility modes, locked page, no leakage */

    public function test_immediate_mode_shows_the_qr(): void
    {
        $me = $this->customer();
        $ticket = $this->first($this->paid($me));

        $this->actingAs($me)->get(route('front.tickets.show', $ticket))->assertOk()->assertSee('class="dt__qr-box"', false)->assertDontSee('QR Code Locked');
    }

    public function test_a_hidden_qr_is_locked_and_nothing_about_it_reaches_the_browser(): void
    {
        $me = $this->customer();
        $ticket = $this->first($this->paid($me));
        $this->setMode('manual');
        $token = $this->dash($ticket)->qr_payload;

        $html = $this->actingAs($me)->get(route('front.tickets.show', $ticket))->assertOk()
            ->assertSee('QR Code Locked')->assertSee('Your QR code will be available closer to the event.')->assertSee('confirmed and valid')
            ->assertSee('Pay Night')->assertSee('Holder 1')->assertDontSee('class="dt__qr-box"', false)->getContent();
        $this->assertStringNotContainsString($token, $html);
        $this->assertStringNotContainsString('<svg viewBox="0 0 320', $html);

        $json = $this->actingAs($me)->getJson(route('front.tickets.qr', $ticket))->assertOk()->assertJson(['state' => 'locked', 'svg' => null]);
        $this->assertStringNotContainsString($token, $json->getContent());
        $this->actingAs($me)->get(route('front.tickets.download', $ticket))->assertRedirect(route('front.tickets.show', $ticket)); // no PDF around it either
    }

    public function test_the_locked_placeholder_is_a_fixed_decorative_picture_never_derived_from_the_ticket(): void
    {
        $me = $this->customer();
        $order = $this->paid($me, 2);
        [$a, $b] = IssuedTicket::where('order_id', $order->id)->orderBy('id')->get()->all();
        $this->setMode('manual');

        $pa = $this->actingAs($me)->get(route('front.tickets.show', $a))->assertOk()->assertSee('QR LOCKED')->assertSee('dt__ph', false)->getContent();
        $pb = $this->actingAs($me)->get(route('front.tickets.show', $b))->assertOk()->getContent();
        $grab = fn (string $h) => preg_match('/<div class="dt__ph".*?<\/svg>/s', $h, $m) ? $m[0] : null;
        $this->assertNotNull($grab($pa));
        $this->assertSame($grab($pa), $grab($pb), 'identical for every ticket');
        $this->assertNotSame(TicketQr::svg($this->dash($a)->qr_payload), TicketQr::placeholderSvg());
        $this->assertStringNotContainsString($this->dash($a)->qr_payload, $pa);
        $this->assertSame(TicketQr::placeholderSvg(), TicketQr::placeholderSvg(), 'deterministic');
    }

    public function test_scheduled_reveal_shows_a_countdown_then_the_qr_at_the_time(): void
    {
        $me = $this->customer();
        $ticket = $this->first($this->paid($me));
        $this->setMode('scheduled', 24 * 60);   // event in 5 days at 21:00 → reveal 4 days from now at 21:00

        $this->actingAs($me)->get(route('front.tickets.show', $ticket))->assertOk()->assertSee('QR Code Locked')->assertSee('data-countdown', false)->assertSee('Opens ')
            ->assertSee($this->event->qrRevealsAt()->format('D, j M Y · g:i A'));
        $this->actingAs($me)->getJson(route('front.tickets.qr', $ticket))->assertJson(['state' => 'locked'])->assertJsonPath('reveals_at', fn ($v) => $v !== null);

        $this->travelTo($this->event->fresh()->qrRevealsAt()->subMinute());
        $this->actingAs($me)->getJson(route('front.tickets.qr', $ticket))->assertJson(['state' => 'locked', 'svg' => null]);

        $this->travelTo($this->event->fresh()->qrRevealsAt()->addMinute());
        $this->actingAs($me)->get(route('front.tickets.show', $ticket))->assertOk()->assertSee('class="dt__qr-box"', false)->assertDontSee('QR Code Locked');
        $this->actingAs($me)->getJson(route('front.tickets.qr', $ticket))->assertJson(['state' => 'visible'])->assertJsonPath('svg', fn ($v) => str_contains((string) $v, '<svg'));
    }

    public function test_an_admin_can_override_any_mode_without_reissuing_tickets_and_the_ticket_is_unchanged(): void
    {
        $me = $this->customer();
        $ticket = $this->first($this->paid($me));
        $payloadBefore = $this->dash($ticket)->qr_payload;
        $this->setMode('scheduled', 60 * 24);
        $admin = $this->staff(['events.qr-visibility.manage']);

        $this->actingAs($admin)->post(route('admin.events.qr-override', $this->event), ['override' => 'show'])->assertRedirect();
        $this->actingAs($me)->getJson(route('front.tickets.qr', $ticket))->assertJson(['state' => 'visible']);

        $this->actingAs($admin)->post(route('admin.events.qr-override', $this->event), ['override' => 'hide'])->assertRedirect();
        $this->actingAs($me)->getJson(route('front.tickets.qr', $ticket))->assertJson(['state' => 'locked']);

        $this->actingAs($admin)->post(route('admin.events.qr-override', $this->event), ['override' => 'auto'])->assertRedirect();
        $this->assertNull($this->event->fresh()->qr_override);
        $this->assertSame($payloadBefore, $this->dash($ticket)->qr_payload, 'tickets are never re-issued');
    }

    public function test_status_labels_and_who_changed_what_are_recorded(): void
    {
        $admin = $this->staff(['events.qr-visibility.manage']);
        $this->assertSame('visible', $this->event->qrStatus()['state']);

        $this->actingAs($admin)->post(route('admin.events.qr-visibility', $this->event), ['qr_visibility_mode' => 'scheduled', 'qr_reveal_amount' => 12, 'qr_reveal_unit' => 'hours'])->assertRedirect();
        $e = $this->event->fresh();
        $this->assertSame(['scheduled', 720, $admin->id], [$e->qr_visibility_mode, $e->qr_reveal_minutes_before, $e->qr_visibility_changed_by]);
        $this->assertNotNull($e->qr_visibility_changed_at);
        $this->assertSame('scheduled', $e->qrStatus()['state']);

        $this->actingAs($admin)->post(route('admin.events.qr-override', $e), ['override' => 'hide']);
        $this->assertSame('overridden', $e->fresh()->qrStatus()['state']);
        $this->assertFalse($e->fresh()->qrStatus()['visible']);

        $this->assertSame(2, Activity::where('subject_type', Event::class)->where('subject_id', $e->id)->where('description', 'like', 'Ticket QR%')->where('causer_id', $admin->id)->count());

        $admin->givePermissionTo(\Spatie\Permission\Models\Permission::findOrCreate('events.update', 'web'));
        $this->actingAs($admin)->get(route('admin.events.edit', $e))->assertOk()->assertSee('Ticket QR Visibility')->assertSee('Manually overridden')->assertSee($admin->name);
    }

    public function test_saving_the_automatic_rule_ends_a_manual_override_for_both_controls(): void
    {
        $admin = $this->staff(['events.qr-visibility.manage', 'events.entry.manage', 'events.update']);
        $this->event->forceFill(['qr_override' => 'hide', 'entry_override' => 'close'])->save();

        $page = $this->actingAs($admin)->get(route('admin.events.edit', $this->event))->assertOk();
        $page->assertSee('data-seg-tab="manual"', false)->assertSee('Switch to automatic');   // manual is the active tab while an override is set
        $page->assertSee('Hide QR (current)')->assertSee('Close gates (current)');

        $this->actingAs($admin)->post(route('admin.events.qr-visibility', $this->event), ['qr_visibility_mode' => 'scheduled', 'qr_reveal_amount' => 24, 'qr_reveal_unit' => 'hours']);
        $this->actingAs($admin)->post(route('admin.events.entry-availability', $this->event), ['entry_mode' => 'always']);

        $e = $this->event->fresh();
        $this->assertNull($e->qr_override);
        $this->assertNull($e->entry_override);
        $this->assertSame('scheduled', $e->qr_visibility_mode);
        $this->actingAs($admin)->get(route('admin.events.edit', $e))->assertOk()->assertSee('Save QR visibility')->assertDontSee('Switch to automatic');
    }

    public function test_invalid_visibility_settings_are_rejected_and_the_permission_is_required(): void
    {
        $admin = $this->staff(['events.qr-visibility.manage']);
        $this->actingAs($admin)->post(route('admin.events.qr-visibility', $this->event), ['qr_visibility_mode' => 'scheduled', 'qr_reveal_amount' => 0, 'qr_reveal_unit' => 'hours'])->assertSessionHasErrors('qr_reveal_amount');
        $this->actingAs($admin)->post(route('admin.events.qr-visibility', $this->event), ['qr_visibility_mode' => 'sometimes'])->assertSessionHasErrors('qr_visibility_mode');
        $this->assertSame('immediate', $this->event->fresh()->qr_visibility_mode);

        $viewer = $this->staff(['events.update', 'events.entry.manage']);   // editing events or managing entry does not grant QR visibility control
        $this->actingAs($viewer)->post(route('admin.events.qr-override', $this->event), ['override' => 'hide'])->assertForbidden();
        $this->assertNull($this->event->fresh()->qr_override);
    }

    /* ------------------------------------------------------------ 6: My Tickets sections and statuses */

    public function test_my_tickets_sorts_into_upcoming_past_used_and_canceled_refunded(): void
    {
        $me = $this->customer();
        $this->paid($me, 1);                                                                                         // upcoming
        $this->paid($me, 1, $this->makeEvent(['name' => 'Old Night', 'status' => 'sold_out']));                      // past
        $used = $this->first($this->paid($me, 1, $this->makeEvent(['name' => 'Used Night'])));
        $this->dash($used)->update(['status' => 'checked_in']);
        $canceled = $this->first($this->paid($me, 1, $this->makeEvent(['name' => 'Canceled Night'])));
        $this->dash($canceled)->update(['status' => 'canceled']);
        $refundedOrder = $this->paid($me, 1, $this->makeEvent(['name' => 'Refunded Night']));
        $refundedOrder->forceFill(['status' => 'refunded'])->save();                                                // a fully refunded order stays visible

        $page = fn (string $when) => $this->actingAs($me)->get(route('front.account.tickets', ['when' => $when]))->assertOk();
        $page('upcoming')->assertSee('Pay Night')->assertDontSee('Old Night')->assertDontSee('Used Night')->assertDontSee('Canceled Night');
        $page('past')->assertSee('Old Night')->assertDontSee('Pay Night');
        $page('used')->assertSee('Used Night')->assertSee('Checked in')->assertDontSee('Pay Night');
        $page('canceled')->assertSee('Canceled Night')->assertSee('Refunded Night')->assertSee('Refunded')->assertDontSee('Pay Night');

        $refunded = $this->first($refundedOrder);
        $this->actingAs($me)->get(route('front.tickets.show', $refunded))->assertOk()->assertSee('This ticket was refunded')->assertDontSee('class="dt__qr-box"', false);
        $this->actingAs($me)->getJson(route('front.tickets.qr', $refunded))->assertJson(['state' => 'canceled', 'svg' => null]);
    }

    public function test_tickets_of_one_order_can_be_paged_through_and_the_order_link_lists_them(): void
    {
        $me = $this->customer();
        $order = $this->paid($me, 3);
        $tickets = IssuedTicket::where('order_id', $order->id)->orderBy('id')->get();

        $this->actingAs($me)->get(route('front.tickets.show', $tickets[1]))->assertOk()
            ->assertSee('Ticket 2 of 3')->assertSee(route('front.tickets.show', $tickets[0]), false)->assertSee(route('front.tickets.show', $tickets[2]), false);
        $this->actingAs($me)->get(route('front.account.tickets', ['order' => $order->order_number, 'when' => 'all']))->assertOk()
            ->assertSee('Holder 1')->assertSee('Holder 2')->assertSee('Holder 3')->assertSee('3 tickets');
    }

    /* ------------------------------------------------------------ 5: signed-out links, guests, tickets bought for others */

    public function test_a_signed_out_visitor_is_sent_to_the_ticket_access_page_and_returned_to_their_ticket(): void
    {
        $me = $this->customer(['email' => 'buyer@example.test', 'passwordless_since' => now()]);
        $ticket = $this->first($this->paid($me));
        $url = route('front.tickets.show', $ticket);

        $this->get($url)->assertRedirect(route('front.tickets.access', ['redirect' => $url]));
        $this->get(route('front.tickets.access', ['redirect' => $url]))->assertOk()->assertSee('Email me a code')->assertSee('no password');

        $this->postJson(route('front.tickets.access.send'), ['email' => 'buyer@example.test'])->assertOk();
        $code = null;
        Mail::assertSent(LoginOtpMail::class, function ($m) use (&$code) { $code = $m->otpCode; return true; });
        $this->postJson(route('front.auth.otp.verify'), ['email' => 'buyer@example.test', 'code' => $code])->assertOk();

        $this->get($url)->assertOk()->assertSee('Holder 1');                                        // back on the requested ticket
        $this->get(route('front.tickets.access', ['redirect' => $url]))->assertRedirect($url);      // already signed in
    }

    public function test_the_access_page_only_returns_to_this_site(): void
    {
        // (The site header's own login form echoes ?redirect= and validates it on submit; what matters is where *this* page sends people.)
        $this->get(route('front.tickets.access', ['redirect' => 'https://evil.example/steal']))->assertOk()
            ->assertSee('data-after="'.route('front.account.tickets').'"', false)->assertDontSee('data-after="https://evil.example', false);
        $this->get(route('front.tickets.access', ['redirect' => '//evil.example']))->assertOk()->assertSee('data-after="'.route('front.account.tickets').'"', false);
    }

    public function test_a_guest_buyer_who_never_signed_in_can_open_their_tickets_with_an_e_mail_code(): void
    {
        $guest = $this->customer(['email' => 'guest@example.test', 'passwordless_since' => now(), 'email_verified_at' => null]);
        $order = $this->paid($guest, 1);
        $order->forceFill(['owner_unverified' => true])->save();   // paid as a guest: the mailbox was never proven
        $ticket = $this->first($order);

        $this->postJson(route('front.tickets.access.send'), ['email' => 'guest@example.test'])->assertOk();
        $code = null;
        Mail::assertSent(LoginOtpMail::class, function ($m) use (&$code) { $code = $m->otpCode; return true; });
        $this->postJson(route('front.auth.otp.verify'), ['email' => 'guest@example.test', 'code' => $code])->assertOk();

        $this->get(route('front.tickets.show', $ticket))->assertOk()->assertSee('Holder 1');
    }

    public function test_a_holder_without_an_account_gets_in_with_a_code_and_sees_only_their_own_ticket(): void
    {
        $buyer = $this->customer();
        $order = $this->paid($buyer, 2);
        [$mine, $other] = IssuedTicket::where('order_id', $order->id)->orderBy('id')->get()->all();
        $mine->update(['holder_email' => 'friend@example.test', 'holder_name' => 'Friend']);
        $this->assertNull(User::where('email', 'friend@example.test')->first());

        // An address that holds no ticket gets no account created.
        $this->postJson(route('front.tickets.access.send'), ['email' => 'nobody@example.test'])->assertOk();
        $this->assertNull(User::where('email', 'nobody@example.test')->first());
        Mail::assertNotSent(LoginOtpMail::class);

        $this->postJson(route('front.tickets.access.send'), ['email' => 'friend@example.test'])->assertOk();
        $friend = User::where('email', 'friend@example.test')->firstOrFail();
        $this->assertNotNull($friend->passwordless_since);
        $this->assertNull($friend->email_verified_at, 'nothing is verified until the code is entered');
        $this->get(route('front.tickets.show', $mine))->assertRedirect();   // still signed out

        $code = null;
        Mail::assertSent(LoginOtpMail::class, function ($m) use (&$code) { $code = $m->otpCode; return true; });
        $this->postJson(route('front.auth.otp.verify'), ['email' => 'friend@example.test', 'code' => $code])->assertOk();

        $this->get(route('front.tickets.show', $mine))->assertOk()->assertSee('Friend')->assertDontSee('/account/orders/');   // no link into the buyer's order
        $this->get(route('front.tickets.show', $other))->assertForbidden();
    }

    /* ------------------------------------------------------------ 7: scanner */

    private function lookup(User $scanner, string $code, ?string $source = null)
    {
        return $this->actingAs($scanner)->postJson(route('admin.tickets.scanner.lookup'), array_filter(['code' => $code, 'source' => $source]));
    }

    public function test_the_scanner_admits_a_ticket_by_its_own_qr_when_the_event_shows_qr_codes(): void
    {
        $scanner = $this->staff(['scanner.access']);
        $dash = $this->dash($this->first($this->paid($this->customer())));

        $this->lookup($scanner, $dash->qr_payload, 'camera')->assertOk()->assertJson(['ok' => true]);
        $this->actingAs($scanner)->post(route('admin.tickets.scanner.status', $dash), ['status' => 'checked_in'])->assertRedirect();
        $this->assertSame('checked_in', $dash->fresh()->status);
    }

    public function test_qr_visibility_and_entry_availability_are_independent(): void
    {
        $scanner = $this->staff(['scanner.access']);
        $me = $this->customer();
        $ticket = $this->first($this->paid($me));
        $dash = $this->dash($ticket);

        // The customer sees the QR 24h ahead...
        $this->setMode('scheduled', 60 * 24);
        $this->event->forceFill(['entry_mode' => 'scheduled', 'entry_opens_minutes_before' => 60])->save();   // ...but the gates open 1h before the start
        $this->travelTo($this->event->fresh()->qrRevealsAt()->addHour());
        $this->actingAs($me)->getJson(route('front.tickets.qr', $ticket))->assertJson(['state' => 'visible']);
        $this->lookup($scanner, $dash->qr_payload, 'camera')->assertStatus(409)->assertJson(['ok' => false, 'admit' => false, 'code' => 'gates_closed']);
        $this->assertSame(1, ScanLog::where('ticket_id', $dash->id)->where('action', 'lookup_rejected_gates_closed')->count());
        $this->actingAs($scanner)->post(route('admin.tickets.scanner.status', $dash), ['status' => 'checked_in'])->assertSessionHas('error', fn ($m) => str_contains($m, 'GATES NOT OPEN'));
        $this->assertSame('not_checked_in', $dash->fresh()->status);

        // Gates open at 20:00 (start 21:00 minus 1h).
        $this->travelTo($this->event->fresh()->entryOpensAt()->addMinute());
        $this->lookup($scanner, $dash->qr_payload, 'camera')->assertOk()->assertJson(['ok' => true]);
        $this->actingAs($scanner)->post(route('admin.tickets.scanner.status', $dash), ['status' => 'checked_in'])->assertSessionHas('success');
    }

    public function test_an_open_gate_does_not_depend_on_the_qr_being_visible(): void
    {
        $scanner = $this->staff(['scanner.access']);
        $dash = $this->dash($this->first($this->paid($this->customer())));
        $this->setMode('manual');   // customers cannot see the QR, gates are always open: the scanner still works

        $this->lookup($scanner, $dash->qr_payload, 'camera')->assertOk();
    }

    public function test_gates_closed_stops_every_ticket_including_legacy_and_typed_numbers_until_an_admin_opens_them(): void
    {
        $scanner = $this->staff(['scanner.access']);
        $admin = $this->staff(['events.entry.manage']);
        $new = $this->dash($this->first($this->paid($this->customer())));
        $old = $this->dash($this->first($this->paid($this->customer())));
        $old->update(['qr_payload' => $old->ticket_number]);
        $this->event->forceFill(['entry_mode' => 'manual'])->save();

        $this->lookup($scanner, $new->qr_payload, 'camera')->assertStatus(409)->assertJson(['code' => 'gates_closed']);
        $this->lookup($scanner, $old->ticket_number, 'camera')->assertStatus(409)->assertJson(['code' => 'gates_closed']);
        $this->lookup($scanner, $new->ticket_number, 'manual')->assertStatus(409)->assertJson(['code' => 'gates_closed']);

        $this->actingAs($admin)->post(route('admin.events.entry-override', $this->event), ['override' => 'open'])->assertRedirect();
        $this->lookup($scanner, $new->qr_payload, 'camera')->assertOk();
        $this->lookup($scanner, $old->ticket_number, 'camera')->assertOk();

        $this->actingAs($admin)->post(route('admin.events.entry-override', $this->event), ['override' => 'close']);
        $this->lookup($scanner, $old->ticket_number, 'camera')->assertStatus(409);
        $this->actingAs($admin)->post(route('admin.events.entry-override', $this->event), ['override' => 'auto']);
        $this->assertSame('closed', $this->event->fresh()->entryStatus()['state']);
    }

    public function test_entry_settings_need_their_own_permission_validate_and_are_recorded(): void
    {
        $admin = $this->staff(['events.entry.manage']);
        $this->actingAs($admin)->post(route('admin.events.entry-availability', $this->event), ['entry_mode' => 'scheduled', 'entry_opens_amount' => 90, 'entry_opens_unit' => 'minutes'])->assertRedirect();
        $e = $this->event->fresh();
        $this->assertSame(['scheduled', 90, $admin->id], [$e->entry_mode, $e->entry_opens_minutes_before, $e->entry_changed_by]);
        $this->assertNotNull($e->entry_changed_at);
        $this->assertSame('scheduled', $e->entryStatus()['state']);

        $this->actingAs($admin)->post(route('admin.events.entry-availability', $e), ['entry_mode' => 'scheduled', 'entry_opens_amount' => 0, 'entry_opens_unit' => 'hours'])->assertSessionHasErrors('entry_opens_amount');
        $this->actingAs($admin)->post(route('admin.events.entry-override', $e), ['override' => 'open']);
        $this->assertSame('overridden', $e->fresh()->entryStatus()['state']);
        $this->assertSame(2, Activity::where('subject_type', Event::class)->where('subject_id', $e->id)->where('description', 'like', 'Entry %')->where('causer_id', $admin->id)->count());
        $admin->givePermissionTo(\Spatie\Permission\Models\Permission::findOrCreate('events.update', 'web'));
        $this->actingAs($admin)->get(route('admin.events.edit', $e))->assertOk()->assertSee('Entry Availability')->assertSee('Manually overridden')->assertDontSee('Ticket QR Visibility');

        $qrOnly = $this->staff(['events.qr-visibility.manage', 'events.update']);
        $this->actingAs($qrOnly)->post(route('admin.events.entry-override', $e), ['override' => 'close'])->assertForbidden();
        $this->actingAs($qrOnly)->post(route('admin.events.entry-availability', $e), ['entry_mode' => 'manual'])->assertForbidden();
        $this->assertSame('open', $e->fresh()->entry_override);
    }

    public function test_a_scanned_bare_ticket_number_is_not_a_credential_for_a_new_ticket_but_typing_it_works_for_staff(): void
    {
        $scanner = $this->staff(['scanner.access']);
        $dash = $this->dash($this->first($this->paid($this->customer())));

        $this->lookup($scanner, $dash->ticket_number, 'camera')->assertStatus(409)->assertJson(['code' => 'invalid_qr']);   // a forged QR of a guessed number
        $this->lookup($scanner, $dash->ticket_number, 'manual')->assertOk()->assertJson(['ok' => true]);                  // the gate typed it
    }

    public function test_tickets_issued_before_the_update_still_scan_with_their_old_qr(): void
    {
        $scanner = $this->staff(['scanner.access']);
        $dash = $this->dash($this->first($this->paid($this->customer())));
        $dash->update(['qr_payload' => $dash->ticket_number]);   // how every ticket looked before
        $this->setMode('manual', null, 'hide');                    // hiding QR codes never stops an old QR: the PDFs are already out there

        $this->lookup($scanner, $dash->ticket_number, 'camera')->assertOk()->assertJson(['ok' => true]);
        $this->actingAs($scanner)->post(route('admin.tickets.scanner.status', $dash), ['status' => 'checked_in'])->assertSessionHas('success');
        $this->lookup($scanner, $dash->ticket_number, 'camera')->assertOk();   // a used ticket is still looked up (shown as checked in)...
        $this->actingAs($scanner)->post(route('admin.tickets.scanner.status', $dash), ['status' => 'checked_in'])->assertSessionHas('error', fn ($m) => str_contains($m, 'ALREADY CHECKED IN'));   // ...but never admitted twice
    }

    public function test_canceled_and_refunded_tickets_never_get_in(): void
    {
        $scanner = $this->staff(['scanner.access']);
        $buyer = $this->customer();
        $canceled = $this->dash($this->first($this->paid($buyer)));
        $canceled->update(['status' => 'canceled']);
        $this->lookup($scanner, $canceled->qr_payload, 'camera')->assertStatus(409)->assertJson(['code' => 'canceled']);
        $this->actingAs($scanner)->post(route('admin.tickets.scanner.status', $canceled), ['status' => 'checked_in'])->assertRedirect()->assertSessionHas('error');
        $this->assertSame('canceled', $canceled->fresh()->status);

        $refundedOrder = $this->paid($buyer, 1, $this->makeEvent(['name' => 'Refund Night']));
        $refundedOrder->forceFill(['status' => 'refunded'])->save();
        $refunded = $this->dash($this->first($refundedOrder));
        $this->lookup($scanner, $refunded->qr_payload, 'camera')->assertStatus(409)->assertJson(['admit' => false]);
        $this->actingAs($scanner)->post(route('admin.tickets.scanner.status', $refunded), ['status' => 'checked_in'])->assertSessionHas('error');
        $this->assertSame('not_checked_in', $refunded->fresh()->status);
    }

    public function test_a_used_ticket_cannot_check_in_twice(): void
    {
        $scanner = $this->staff(['scanner.access']);
        $dash = $this->dash($this->first($this->paid($this->customer())));

        $this->actingAs($scanner)->post(route('admin.tickets.scanner.status', $dash), ['status' => 'checked_in'])->assertSessionHas('success');
        $this->actingAs($scanner)->post(route('admin.tickets.scanner.status', $dash), ['status' => 'checked_in'])->assertSessionHas('error', fn ($m) => str_contains($m, 'ALREADY CHECKED IN'));
        $this->assertSame(1, ScanLog::where('ticket_id', $dash->id)->where('action', 'status_update')->count());
    }

    /* ------------------------------------------------------------ links that could expose a QR */

    public function test_public_pdf_links_are_gone_for_digital_tickets_and_respect_visibility_for_old_ones(): void
    {
        $ticket = $this->first($this->paid($this->customer()));
        $dash = $this->dash($ticket);

        $this->get(route('front.tickets.short-download', $dash->ticket_number))->assertNotFound();
        $signed = \Illuminate\Support\Facades\URL::temporarySignedRoute('front.tickets.public-download', now()->addDay(), ['ticketNumber' => $dash->ticket_number]);
        $this->get($signed)->assertNotFound();
        $this->get(route('front.tickets.public-download', $dash->ticket_number))->assertForbidden();   // unsigned

        $dash->update(['qr_payload' => $dash->ticket_number]);   // an old ticket
        $this->setMode('manual');
        $this->get($signed)->assertForbidden();                  // hidden QR stays hidden here too
        $this->setMode('immediate');
        $this->get($signed)->assertOk();
    }

    public function test_the_qr_endpoint_and_ticket_page_are_private_to_their_owner(): void
    {
        $ticket = $this->first($this->paid($this->customer()));
        $stranger = $this->customer();

        $this->getJson(route('front.tickets.qr', $ticket))->assertUnauthorized();
        $this->actingAs($stranger)->getJson(route('front.tickets.qr', $ticket))->assertForbidden();
        $res = $this->actingAs($this->customer())->get(route('front.tickets.show', $ticket));
        $res->assertForbidden();

        $owner = User::find($ticket->order->user_id);
        $this->actingAs($owner)->get(route('front.tickets.show', $ticket))->assertOk()->assertHeader('Cache-Control', 'no-store, private');
    }

    public function test_qr_images_are_drawn_locally_and_decode_to_the_payload_inputs(): void
    {
        $svg = TicketQr::svg('TKT-ABCDEFGHIJKLMNOPQRSTUVWX');
        $this->assertStringContainsString('<svg', $svg);
        $this->assertStringStartsWith('data:image/svg+xml;base64,', TicketQr::dataUri('x'));
        Http::assertNothingSent();
    }
}
