<?php

namespace Tests\Feature;

use App\Http\Middleware\EnsureAdminPanelAccess;
use App\Mail\AdminTicketIssuedMail;
use App\Mail\GuestInvitationMail;
use App\Mail\LoginOtpMail;
use App\Models\Event;
use App\Models\IssuedTicket;
use App\Models\Order;
use App\Models\ScanLog;
use App\Models\Ticket;
use App\Models\User;
use App\Services\TicketIssuanceService;
use App\Services\Tickets\GuestTicketIssuer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Spatie\Permission\Models\Permission;
use Tests\Support\BuildsOrders;
use Tests\Support\PinnedSessionManager;
use Tests\Support\PinnedSessionStore;
use Tests\TestCase;

/** Guest-list digital tickets, the e-mail-code access flow (expiry, attempts, single use, isolation) and "no way around a hidden QR". */
class DigitalTicketsAccessTest extends TestCase
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
        PinnedSessionStore::$pin = substr(hash('sha256', 'access-a'), 0, 40);
        $this->app['session.store']->flush();
        $this->event = $this->makeEvent(['name' => 'Gate Night']);
    }

    private function staff(array $perms): User
    {
        $this->withoutMiddleware(EnsureAdminPanelAccess::class);
        foreach ($perms as $p) {
            Permission::findOrCreate($p, 'web');
        }

        return tap(User::factory()->create())->givePermissionTo($perms);
    }

    private function customer(array $o = []): User
    {
        return User::factory()->create(array_merge(['email_verified_at' => now()], $o));
    }

    private function paid(User $user, int $people = 1): Order
    {
        $order = $this->makePendingOrder($user, $this->event, $people);
        $order->forceFill(['status' => 'paid', 'paid_at' => now()])->save();
        app(TicketIssuanceService::class)->issueIfPaid($order->fresh());

        return $order->fresh();
    }

    private function guestTicket(?string $email = 'vip@example.test', string $name = 'Vip Guest'): IssuedTicket
    {
        $admin = $this->staff(['guest-list.create', 'guest-list.view']);
        $this->actingAs($admin)->post(route('admin.guest-list.store'), [
            'event_name' => 'Gate Night', 'guest_type' => 'VIP', 'guests' => [['name' => $name, 'email' => $email]],
        ])->assertRedirect();
        $this->app['auth']->forgetGuards();

        return IssuedTicket::whereNull('order_id')->latest('id')->firstOrFail();
    }

    /** Requests a code for $email and returns it. */
    private function code(string $email): string
    {
        $this->postJson(route('front.tickets.access.send'), ['email' => $email])->assertOk();
        $code = null;
        Mail::assertSent(LoginOtpMail::class, function ($m) use (&$code) { $code = $m->otpCode; return true; });

        return (string) $code;
    }

    private function dash(IssuedTicket $t): Ticket
    {
        return Ticket::where('ticket_number', $t->ticket_number)->firstOrFail();
    }

    /* ------------------------------------------------------------ guest list = digital tickets */

    public function test_a_guest_list_ticket_is_digital_the_invitation_links_to_it_and_carries_no_pdf_or_qr(): void
    {
        $issued = $this->guestTicket();
        $dash = $this->dash($issued);

        $this->assertNull($issued->order_id);
        $this->assertSame('vip@example.test', $issued->holder_email);
        $this->assertMatchesRegularExpression('/^TKT-[A-Z]{24}$/', $dash->qr_payload);

        Mail::assertSent(GuestInvitationMail::class, function (GuestInvitationMail $mail) use ($issued, $dash) {
            $this->assertSame([], $mail->attachments);
            $this->assertSame([], $mail->rawAttachments);
            $html = html_entity_decode($mail->render());
            $this->assertStringContainsString(route('front.tickets.show', $issued), $html);
            $this->assertStringContainsStringIgnoringCase('View your ticket', $html);
            $this->assertStringNotContainsString($dash->qr_payload, $html);
            $this->assertStringNotContainsString('data:image', $html);

            return true;
        });
    }

    public function test_the_guest_opens_their_ticket_on_a_phone_after_confirming_their_e_mail(): void
    {
        $issued = $this->guestTicket();
        $url = route('front.tickets.show', $issued);

        $this->get($url)->assertRedirect(route('front.tickets.access', ['redirect' => $url]));
        $code = $this->code('vip@example.test');            // no account yet: one is created for the confirmed address
        $this->assertNotNull(User::where('email', 'vip@example.test')->first());
        $this->postJson(route('front.auth.otp.verify'), ['email' => 'vip@example.test', 'code' => $code])->assertOk();

        $this->get($url)->assertOk()->assertSee('Vip Guest')->assertSee('Guest invitation')->assertSee('class="dt__qr-box"', false)->assertSee('Gate Night');
        $this->getJson(route('front.tickets.qr', $issued))->assertJson(['state' => 'visible']);
        $this->get(route('front.account.tickets'))->assertOk()->assertSee('Vip Guest')->assertSee('Guest invitation');
    }

    public function test_guest_tickets_obey_qr_visibility_cancellation_and_belong_to_their_holder_only(): void
    {
        $issued = $this->guestTicket();
        $holder = $this->customer(['email' => 'vip@example.test']);
        $stranger = $this->customer();

        $this->actingAs($stranger)->get(route('front.tickets.show', $issued))->assertForbidden();
        $this->actingAs($stranger)->getJson(route('front.tickets.qr', $issued))->assertForbidden();

        $this->event->forceFill(['qr_visibility_mode' => 'manual'])->save();
        $html = $this->actingAs($holder)->get(route('front.tickets.show', $issued))->assertOk()->assertSee('QR Code Locked')->getContent();
        $this->assertStringNotContainsString($this->dash($issued)->qr_payload, $html);

        $this->event->forceFill(['qr_override' => 'show'])->save();
        $this->actingAs($holder)->getJson(route('front.tickets.qr', $issued))->assertJson(['state' => 'visible']);

        $this->dash($issued)->update(['status' => 'canceled']);
        $this->actingAs($holder)->getJson(route('front.tickets.qr', $issued))->assertJson(['state' => 'canceled', 'svg' => null]);
        $this->dash($issued)->delete();   // deleted by staff
        $this->actingAs($holder)->getJson(route('front.tickets.qr', $issued))->assertJson(['state' => 'canceled', 'svg' => null]);
    }

    public function test_guest_tickets_scan_with_the_same_rules(): void
    {
        $issued = $this->guestTicket();
        $dash = $this->dash($issued);
        $scanner = $this->staff(['scanner.access']);

        $this->actingAs($scanner)->postJson(route('admin.tickets.scanner.lookup'), ['code' => $dash->qr_payload, 'source' => 'camera'])->assertOk();
        $this->actingAs($scanner)->postJson(route('admin.tickets.scanner.lookup'), ['code' => $dash->ticket_number, 'source' => 'camera'])->assertStatus(409)->assertJson(['code' => 'invalid_qr']);
        $this->actingAs($scanner)->post(route('admin.tickets.scanner.status', $dash), ['status' => 'checked_in'])->assertSessionHas('success');
        $this->actingAs($scanner)->post(route('admin.tickets.scanner.status', $dash), ['status' => 'checked_in'])->assertSessionHas('error', fn ($m) => str_contains($m, 'ALREADY CHECKED IN'));
    }

    public function test_an_older_guest_ticket_comes_online_when_sent_keeps_its_old_qr_and_gets_a_link_not_a_pdf(): void
    {
        // Created before digital tickets: bare-number QR, no issued record, maybe no e-mail.
        $old = Ticket::create(['name' => 'Gate Night - Guest VIP', 'source' => 'guest_list', 'guest_type' => 'Guest VIP', 'status' => 'not_checked_in', 'holder_name' => 'Old Guest',
            'ticket_number' => '5550001111', 'qr_payload' => '5550001111', 'event_id' => $this->event->id, 'created_by_user_id' => ($admin = $this->staff(['tickets.update']))->id]);
        $this->assertNull(IssuedTicket::where('ticket_number', '5550001111')->first());

        $this->actingAs($admin)->post(route('admin.tickets.send-email', $old), ['email' => 'old@example.test'])->assertRedirect();

        $issued = IssuedTicket::where('ticket_number', '5550001111')->firstOrFail();
        $this->assertNull($issued->order_id);
        $this->assertSame('old@example.test', $old->fresh()->holder_email);
        $this->assertSame('5550001111', $old->fresh()->qr_payload, 'the QR already in circulation is untouched');
        Mail::assertSent(AdminTicketIssuedMail::class, function ($m) use ($issued) {
            $this->assertSame([], $m->rawAttachments);
            $this->assertStringContainsString(route('front.tickets.show', $issued), html_entity_decode($m->render()));

            return true;
        });

        // The old QR still scans.
        $scanner = $this->staff(['scanner.access']);
        $this->actingAs($scanner)->postJson(route('admin.tickets.scanner.lookup'), ['code' => '5550001111', 'source' => 'camera'])->assertOk();
    }

    /* ------------------------------------------------------------ e-mail code security */

    public function test_a_code_expires_after_ten_minutes(): void
    {
        $this->paid($this->customer(['email' => 'late@example.test', 'passwordless_since' => now()]));
        $code = $this->code('late@example.test');

        $this->travel(11)->minutes();
        $this->postJson(route('front.auth.otp.verify'), ['email' => 'late@example.test', 'code' => $code])->assertStatus(422);
        $this->assertGuest();
    }

    public function test_guessing_is_limited_to_five_tries_per_code_and_a_locked_code_stays_dead(): void
    {
        $this->paid($this->customer(['email' => 'guess@example.test', 'passwordless_since' => now()]));
        $code = $this->code('guess@example.test');
        $wrong = $code === '000000' ? '111111' : '000000';

        for ($i = 0; $i < 5; $i++) {
            $this->postJson(route('front.auth.otp.verify'), ['email' => 'guess@example.test', 'code' => $wrong])->assertStatus(422);
        }
        $this->postJson(route('front.auth.otp.verify'), ['email' => 'guess@example.test', 'code' => $code])->assertStatus(422);   // even the right one now
        $this->assertGuest();
    }

    public function test_a_code_works_once(): void
    {
        $this->paid($this->customer(['email' => 'once@example.test', 'passwordless_since' => now()]));
        $code = $this->code('once@example.test');

        $this->postJson(route('front.auth.otp.verify'), ['email' => 'once@example.test', 'code' => $code])->assertOk();
        $this->post(route('front.customer.logout'));
        $this->assertGuest();
        $this->postJson(route('front.auth.otp.verify'), ['email' => 'once@example.test', 'code' => $code])->assertStatus(422);
        $this->assertGuest();
    }

    public function test_a_code_cannot_be_used_from_another_browser(): void
    {
        $this->paid($this->customer(['email' => 'other@example.test', 'passwordless_since' => now()]));
        $code = $this->code('other@example.test');

        PinnedSessionStore::$pin = substr(hash('sha256', 'attacker-browser'), 0, 40);   // somebody else's session
        $this->postJson(route('front.auth.otp.verify'), ['email' => 'other@example.test', 'code' => $code])->assertStatus(422);
        $this->assertGuest();
    }

    public function test_sending_codes_is_rate_limited_and_never_reveals_whether_an_address_has_tickets(): void
    {
        $this->paid($this->customer(['email' => 'real@example.test', 'passwordless_since' => now()]));

        $known = $this->postJson(route('front.tickets.access.send'), ['email' => 'real@example.test'])->assertOk()->json();
        $unknown = $this->postJson(route('front.tickets.access.send'), ['email' => 'nobody@example.test'])->assertOk()->json();
        $this->assertSame($known, $unknown, 'identical answers');
        $this->assertNull(User::where('email', 'nobody@example.test')->first());

        $this->travel(31)->seconds();
        for ($i = 0; $i < 4; $i++) {                       // 5 per hour per address
            $this->travel(31)->seconds();
            $this->postJson(route('front.tickets.access.send'), ['email' => 'real@example.test']);
        }
        $this->travel(31)->seconds();
        $this->postJson(route('front.tickets.access.send'), ['email' => 'real@example.test'])->assertStatus(429);
    }

    public function test_a_signed_in_customer_sees_only_their_own_tickets_even_with_another_tickets_link(): void
    {
        $alice = $this->customer(['email' => 'alice@example.test']);
        $bob = $this->customer(['email' => 'bob@example.test']);
        $bobs = IssuedTicket::where('order_id', $this->paid($bob)->id)->firstOrFail();
        $alices = IssuedTicket::where('order_id', $this->paid($alice)->id)->firstOrFail();

        $this->actingAs($alice);
        $this->get(route('front.tickets.show', $bobs))->assertForbidden();
        $this->getJson(route('front.tickets.qr', $bobs))->assertForbidden();
        $this->get(route('front.tickets.download', $bobs))->assertForbidden();
        $this->get(route('front.account.tickets', ['when' => 'all']))->assertOk()->assertSee('Holder 1')->assertDontSee($bobs->uuid);
        $this->get(route('front.tickets.show', $alices))->assertOk();

        // A holder whose e-mail is NOT verified (e.g. an account somebody registered with a ticket holder's address) sees nothing of it.
        $bobs->update(['holder_email' => 'alice@example.test']);
        $alice->forceFill(['email_verified_at' => null])->save();
        $this->actingAs($alice->fresh())->get(route('front.tickets.show', $bobs))->assertForbidden();
    }

    /* ------------------------------------------------------------ no way around a hidden QR */

    public function test_no_endpoint_or_old_link_reveals_a_hidden_qr(): void
    {
        $me = $this->customer(['email' => 'sweep@example.test']);
        $order = $this->paid($me, 2);
        $issued = IssuedTicket::where('order_id', $order->id)->orderBy('id')->get();
        $guest = $this->guestTicket('sweep@example.test', 'Sweep Guest');
        $this->event->forceFill(['qr_visibility_mode' => 'manual'])->save();
        $this->event->refresh();

        $tokens = collect([...$issued, $guest])->map(fn ($t) => $this->dash($t)->qr_payload);
        $this->actingAs($me);
        $bodies = [];
        foreach ([...$issued, $guest] as $t) {
            $bodies[] = $this->get(route('front.tickets.show', $t))->assertOk()->assertSee('QR Code Locked')->getContent();
            $bodies[] = $this->getJson(route('front.tickets.qr', $t))->assertJson(['svg' => null, 'state' => 'locked'])->getContent();
            $bodies[] = $this->get(route('front.tickets.download', $t))->assertRedirect()->getContent();
            $bodies[] = $this->get(route('front.tickets.short-download', $this->dash($t)->ticket_number))->assertNotFound()->getContent();
            $signed = URL::temporarySignedRoute('front.tickets.public-download', now()->addDay(), ['ticketNumber' => $this->dash($t)->ticket_number]);
            $bodies[] = $this->get($signed)->assertNotFound()->getContent();
        }
        $bodies[] = $this->get(route('front.account.tickets', ['when' => 'all']))->assertOk()->getContent();
        $bodies[] = $this->get(route('front.account.orders.show', $order->order_number))->assertOk()->getContent();
        $bodies[] = $this->get(route('front.account.orders'))->assertOk()->getContent();

        foreach ($bodies as $i => $body) {
            foreach ($tokens as $token) {
                $this->assertStringNotContainsString($token, $body, "body #$i leaks a QR token");
            }
            $this->assertStringNotContainsString('<svg viewBox="0 0 320', $body, "body #$i contains a QR image");
            $this->assertStringNotContainsString('qrserver', $body);
        }
    }

    public function test_the_ticket_number_alone_never_leads_to_a_qr_for_a_digital_ticket(): void
    {
        $me = $this->customer();
        $ticket = IssuedTicket::where('order_id', $this->paid($me)->id)->firstOrFail();
        $dash = $this->dash($ticket);

        // Numbers are guessable (order id + item id + seat); none of the public routes accepts one.
        $this->get(route('front.tickets.short-download', $dash->ticket_number))->assertNotFound();
        $this->get(route('front.tickets.public-download', $dash->ticket_number))->assertForbidden();
        $this->getJson('/tickets/'.$dash->ticket_number.'/qr')->assertStatus(401);
        $this->actingAs($me)->getJson('/tickets/'.$dash->ticket_number.'/qr')->assertNotFound();
        $this->assertNotSame($dash->ticket_number, $dash->qr_payload);
    }

    public function test_the_invoice_pdf_is_unaffected(): void
    {
        $me = $this->customer();
        $order = $this->paid($me);

        $res = $this->actingAs($me)->get(route('front.account.orders.invoice', $order->order_number))->assertOk();
        $this->assertStringContainsString('pdf', (string) $res->headers->get('content-type'));
        $this->assertStringStartsWith('%PDF', $res->getContent());
    }
}
