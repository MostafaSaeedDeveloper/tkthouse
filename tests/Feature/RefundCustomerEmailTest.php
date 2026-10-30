<?php

namespace Tests\Feature;

use App\Http\Middleware\EnsureAdminPanelAccess;
use App\Mail\RefundCustomerMail;
use App\Models\Order;
use App\Models\PaymentAttempt;
use App\Models\PaymentRefund;
use App\Models\Ticket;
use App\Models\User;
use App\Services\Payments\Refunds\RefundCustomerNotifier;
use App\Services\Payments\Refunds\RefundService;
use App\Services\TicketIssuanceService;
use App\Support\SystemSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Mail\Events\MessageSending;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Tests\Support\BuildsMerchantAccounts;
use Tests\Support\BuildsOrders;
use Tests\TestCase;

/** Customer refund e-mails: accurate wording, never a gateway name, exactly once, after the commit, and never able to break a refund. */
class RefundCustomerEmailTest extends TestCase
{
    use BuildsMerchantAccounts, BuildsOrders, RefreshDatabase;

    private RefundService $svc;
    private User $staff;
    private User $approver;

    private const BLOCKED = ['fawaterak', 'fawaterk', 'paymob', 'gateway', 'dashboard', 'webhook', 'merchant', 'api ', 'FAW-', 'secret internal', 'credential'];

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(EnsureAdminPanelAccess::class);
        $this->fakeProviders();
        SystemSettings::set('refund_approval_required', false);
        $this->svc = app(RefundService::class);
        $this->makeMethod('fawaterak', 'fawaterak_card', ['api_key' => 'L', 'provider_key' => 'FAWATERAK.1']);
        $perms = ['payments.refunds.create', 'payments.refunds.view', 'payments.refunds.approve', 'orders.view', 'orders.view_all'];
        foreach ($perms as $p) {
            Permission::findOrCreate($p, 'web');
        }
        $this->staff = tap(User::factory()->create())->givePermissionTo($perms);
        $this->approver = tap(User::factory()->create())->givePermissionTo($perms);
    }

    private function paid(string $kind = 'modern', int $people = 3, float $unit = 100): array
    {
        $order = $this->makePendingOrder(User::factory()->create(), $this->makeEvent(['name' => 'Summer Fest']), $people, $unit, 'fawaterak_card');
        $attempt = null;
        if ($kind === 'modern') {
            $acc = $this->account('fawaterak', 'FW'.Str::random(5));
            $acc->forceFill(['refund_enabled_at' => now()])->save();
            $order->forceFill(['integration_account_id' => $acc->id])->saveQuietly();
            $attempt = PaymentAttempt::create(['order_id' => $order->id, 'provider' => 'fawaterak', 'method_code' => 'fawaterak_card', 'amount' => $unit * $people, 'currency' => 'EGP', 'status' => 'paid',
                'transaction_id' => (string) random_int(100000, 999999), 'reference' => $order->order_number.'-r', 'integration_account_id' => $acc->id, 'internal_reference' => PaymentAttempt::newInternalReference(), 'paid_at' => now(), 'settled_at' => now()]);
        }
        $order->forceFill(['status' => 'paid', 'paid_at' => now()])->saveQuietly();
        app(TicketIssuanceService::class)->issueIfPaid($order->fresh());

        return [$order->fresh(), $attempt];
    }

    private function ask(array $t, string $type = 'full', ?string $amount = null, array $opts = [], string $reason = 'customer cannot attend any more'): PaymentRefund
    {
        $key = Str::uuid()->toString();

        return $t[1] ? $this->svc->request($t[1], $this->staff, $type, $amount, $reason, $key, $opts) : $this->svc->requestForOrder($t[0], $this->staff, $type, $amount, $reason, $key, $opts);
    }

    private function confirm(PaymentRefund $r): PaymentRefund
    {
        return $this->svc->confirmSucceeded($r, $this->approver, 'FAW-REF-9', 'verified in the provider dashboard', now()->toDateString(), 'provider_portal');
    }

    private function kinds(): array
    {
        return Mail::sent(RefundCustomerMail::class)->map(fn ($m) => $m->kind)->values()->all();
    }

    private function body(string $kind, PaymentRefund $r, ?string $locale = null): string
    {
        $m = new RefundCustomerMail($r->fresh(), $kind, $locale);

        return $m->envelope()->subject."\n".$m->render();
    }

    private function assertClean(string $html, string $why): void
    {
        foreach (self::BLOCKED as $word) {
            $this->assertStringNotContainsStringIgnoringCase($word, $html, "$why: customer e-mail must not contain \"$word\"");
        }
    }

    /* ------------------------------------------------------------ one e-mail, only when truly completed */

    public function test_the_customer_gets_no_email_for_requests_approvals_processing_or_notifications_only_one_when_completed(): void
    {
        foreach ([true, false] as $approval) {                       // second-person approval ON and OFF behave the same for the customer
            Mail::fake();
            SystemSettings::set('refund_approval_required', $approval);
            $t = $this->paid();
            $r = $this->ask($t);
            $this->assertSame([], $this->kinds(), 'a request sends nothing');
            if ($approval) {
                $this->svc->approve($r, $this->approver);
                $this->assertSame([], $this->kinds(), 'an approval sends nothing');
            }
            $this->svc->markSubmitted($r->fresh(), $this->staff);
            $this->postJson(route('front.fawaterak.webhook'), ['transactionId' => $t[1]->transaction_id, 'amount' => '300', 'currency' => 'EGP', 'status' => 'approved', 'reason' => 'x', 'approvedAt' => '2026-10-30 12:00:00'])->assertOk();
            $this->assertSame([], $this->kinds(), 'processing and an "approved" provider notification send nothing');
            $this->assertSame('paid', $t[0]->fresh()->status);

            $this->confirm($r->fresh());
            $this->assertSame(['completed'], $this->kinds(), 'exactly one e-mail, after the verified completion');
        }
        $this->assertSame(['completed'], RefundCustomerMail::KINDS, 'the early-stage e-mails no longer exist');
        $this->assertFalse(view()->exists('emails.refunds.approved'));
        $this->assertFalse(view()->exists('emails.refunds.received'));
        $this->assertArrayNotHasKey('approved', trans('refund_mail'));
        $this->assertArrayNotHasKey('received', trans('refund_mail'));
    }

    public function test_the_email_waits_for_the_tickets_and_the_order_status_not_only_for_succeeded(): void
    {
        Mail::fake();
        $notifier = app(RefundCustomerNotifier::class);

        // succeeded, but the tickets could not be matched automatically (two paid payments): held back until a person cancels them
        $t = $this->paid();
        PaymentAttempt::create(['order_id' => $t[0]->id, 'provider' => 'fawaterak', 'method_code' => 'fawaterak_card', 'amount' => 300, 'currency' => 'EGP', 'status' => 'paid', 'transaction_id' => 'DUP-'.random_int(1000, 9999),
            'reference' => $t[0]->order_number.'-dup', 'integration_account_id' => $t[1]->integration_account_id, 'internal_reference' => PaymentAttempt::newInternalReference(), 'paid_at' => now()]);
        $r = $this->svc->request($t[1], $this->staff, 'full', null, 'duplicate payment given back', 'held-1');
        $done = $this->confirm($r->fresh());
        $this->assertSame('needs_review', $done->effects_status);
        $this->assertSame([], $this->kinds(), 'tickets are not canceled yet: no e-mail');
        $this->svc->resolveTickets($done->fresh(), $this->approver, Ticket::where('order_id', $t[0]->id)->pluck('id')->all());
        $this->assertSame(['completed'], $this->kinds(), 'sent once the tickets are really canceled');
        $this->assertStringContainsString('canceled', $this->body('completed', $done));

        // an order whose status does not follow the refund (not in a refundable state) is never announced
        $t2 = $this->paid();
        $r2 = $this->ask($t2);
        $t2[0]->forceFill(['status' => 'canceled'])->saveQuietly();
        $this->confirm($r2->fresh());
        $this->assertNotSame('refunded', $t2[0]->fresh()->status);
        $notifier->completed($r2->fresh());
        $this->assertSame(['completed'], $this->kinds());
    }

    /* ------------------------------------------------------------ content */

    public function test_the_completed_mail_shows_the_confirmed_facts_the_ticket_outcome_and_no_false_money_claim(): void
    {
        Mail::fake();
        $t = $this->paid('modern', 3, 100);
        $tickets = Ticket::where('order_id', $t[0]->id)->orderBy('id')->get();
        $partial = $this->ask($t, 'partial', null, ['tickets' => [$tickets[0]->id], 'internal_notes' => 'secret internal note']);
        $done = $this->confirm($partial->fresh());
        $html = $this->body('completed', $done);

        foreach (['Your Refund Has Been Processed Successfully — TKT House', 'Buyer Test', $t[0]->order_number, 'Summer Fest', '100.00 EGP', 'Partial refund', $done->internal_reference, now()->format('d M Y'),
            'customer cannot attend any more', 'being returned through the original payment method', 'may take some additional time', 'depending on your bank or payment provider',
            '1 ticket(s) included in this refund have been canceled', 'Tickets that were not included in the refund remain valid'] as $needle) {
            $this->assertStringContainsString($needle, $html);
        }
        foreach (['has been returned to', 'already received', 'in your account', 'arrived', 'refunded to your'] as $claim) {
            $this->assertStringNotContainsStringIgnoringCase($claim, $html, "no claim that the money reached the customer: $claim");
        }
        $this->assertClean($html, 'completed partial');
        $this->assertStringNotContainsString('FAW-REF-9', $html, 'the provider reference is internal');

        // full refund of the rest: the full wording
        $full = $this->ask($t, 'partial', null, ['tickets' => [$tickets[1]->id, $tickets[2]->id]]);
        $fullDone = $this->confirm($full->fresh());
        $this->assertStringContainsString('Partial refund', $this->body('completed', $fullDone));
        $t2 = $this->paid('modern', 2, 150);
        $d2 = $this->confirm($this->ask($t2));
        $h2 = $this->body('completed', $d2);
        foreach (['Full refund', '300.00 EGP', 'The tickets included in this refund have been canceled and can no longer be used for entry.'] as $needle) {
            $this->assertStringContainsString($needle, $h2);
        }
        $this->assertStringNotContainsString('remain valid', $h2);

        // money-only partial refund: it must not claim tickets were canceled
        $t3 = $this->paid('modern', 2, 100);
        $d3 = $this->confirm($this->ask($t3, 'partial', '20', ['no_tickets' => true]));
        $h3 = $this->body('completed', $d3);
        $this->assertStringContainsString('No tickets were canceled by this refund', $h3);
        $this->assertStringNotContainsString('can no longer be used for entry', $h3);
    }

    public function test_provider_names_and_private_words_never_reach_the_customer_even_through_the_reason_or_the_timing_note(): void
    {
        $t = $this->paid();
        $r = $this->ask($t, 'full', null, ['internal_notes' => 'secret internal note', 'provider_reference' => 'FAW-REF-1'], 'Refunded from the Fawaterak dashboard, invoice 884411, see https://app.fawaterk.com');
        $r->forceFill(['provider_reference' => 'FAW-REF-9', 'provider_refund_id' => 'FAW-INTERNAL'])->save();
        SystemSettings::set('refund_email_timing_note', 'Paymob usually takes 7 days.');
        $done = $this->confirm($r->fresh());

        foreach (['completed'] as $kind) {
            $html = $this->body($kind, $done);
            $this->assertClean($html, $kind);
            $this->assertStringNotContainsString((string) $t[1]->transaction_id, $html, 'the provider transaction id is not shown');
            $this->assertStringContainsString('Refund arranged by our team', $html, 'a reason that names internal things is replaced by a generic sentence');
        }
        $this->assertStringContainsString('depending on your bank or payment provider', $this->body('completed', $done), 'an unsafe custom note is ignored');

        SystemSettings::set('refund_email_timing_note', 'It can take a little while to show on your statement.');
        $this->assertStringContainsString('It can take a little while to show on your statement.', $this->body('completed', $done), 'a clean custom note is used');
    }

    public function test_english_and_arabic_render_in_the_standard_layout(): void
    {
        $r = $this->confirm($this->ask($this->paid()));
        $en = (new RefundCustomerMail($r->fresh(), 'completed', 'en'))->render();
        $ar = (new RefundCustomerMail($r->fresh(), 'completed', 'ar'))->render();
        $this->assertStringContainsString('dir="ltr"', $en);
        $this->assertStringContainsString('lang="en"', $en);
        $this->assertStringContainsString('dir="rtl"', $ar);
        $this->assertStringContainsString('تمت معالجة الاسترداد', $ar);
        $this->assertStringContainsString('class="einfo"', $ar, 'same components as the other TKT House e-mails');
        $this->assertStringContainsString('TKT House', $ar);
        $this->assertSame('تمت معالجة الاسترداد بنجاح — TKT House', (function () use ($r) { app()->setLocale('ar'); try { return (new RefundCustomerMail($r->fresh(), 'completed', 'ar'))->envelope()->subject; } finally { app()->setLocale('en'); } })());
        foreach (['fawaterak', 'paymob'] as $w) {
            $this->assertStringNotContainsStringIgnoringCase($w, $ar);
        }
        // the same layout/identity as the existing order e-mails
        $existing = view('emails.orders.status-changed', ['order' => $r->order, 'oldStatus' => 'paid', 'newStatus' => 'refunded'])->render();
        foreach (['class="eb"', 'class="ew"', 'class="ehero"', 'class="efooter"', 'logo-light.png'] as $shared) {
            $this->assertStringContainsString($shared, $en);
            $this->assertStringContainsString($shared, $existing);
        }
    }

    /* ------------------------------------------------------------ delivery rules */

    public function test_repeated_webhooks_confirmations_and_retries_never_duplicate_an_email(): void
    {
        Mail::fake();
        $t = $this->paid();
        $r = $this->ask($t);
        for ($i = 0; $i < 3; $i++) {
            $this->postJson(route('front.fawaterak.webhook'), ['transactionId' => $t[1]->transaction_id, 'amount' => '300', 'currency' => 'EGP', 'status' => 'approved', 'reason' => 'x', 'approvedAt' => '2026-10-30 12:00:00'])->assertOk();
        }
        $this->assertSame([], $this->kinds(), 'a webhook only records a hint: no e-mail');
        $done = $this->confirm($r->fresh());
        try {
            $this->confirm($done);
        } catch (\Illuminate\Validation\ValidationException) {
        }
        $this->postJson(route('front.fawaterak.webhook'), ['transactionId' => $t[1]->transaction_id, 'amount' => '300', 'currency' => 'EGP', 'status' => 'approved', 'approvedAt' => '2026-10-30 12:00:00'])->assertOk();
        $notifier = app(RefundCustomerNotifier::class);
        $notifier->completed($done->fresh());
        $notifier->completed($done->fresh());
        $notifier->retry($done->fresh(), 'completed');
        $this->assertSame(['completed'], $this->kinds());
        // a double form submit is one refund and no e-mail
        $t2 = $this->paid();
        $key = 'dup-1';
        $this->svc->request($t2[1], $this->staff, 'full', null, 'a duplicate submit', $key);
        $this->svc->request($t2[1], $this->staff, 'full', null, 'a duplicate submit', $key);
        $this->assertSame(['completed'], $this->kinds());
    }

    public function test_no_completed_email_for_unverified_failed_rejected_unknown_or_processing_refunds(): void
    {
        Mail::fake();
        $t = $this->paid();
        $r = $this->ask($t);
        $notifier = app(RefundCustomerNotifier::class);

        $notifier->completed($r->fresh());                                   // processing
        $this->postJson(route('front.fawaterak.webhook'), ['transactionId' => $t[1]->transaction_id, 'amount' => '300', 'currency' => 'EGP', 'status' => 'approved', 'approvedAt' => '2026-10-30 12:00:00'])->assertOk(); // unsigned hint
        $notifier->completed($r->fresh());
        $r->forceFill(['status' => 'unknown'])->save();
        $notifier->completed($r->fresh());
        $r->forceFill(['status' => 'failed'])->save();
        $notifier->completed($r->fresh());
        $r->forceFill(['status' => 'rejected'])->save();
        $notifier->completed($r->fresh());
        $this->assertNotContains('completed', $this->kinds());
        $this->assertSame([], $this->kinds());
        $this->assertSame('paid', $t[0]->fresh()->status);
    }

    public function test_a_failing_mailer_never_breaks_the_refund_and_a_failed_email_can_be_resent(): void
    {
        $t = $this->paid();
        $r = $this->ask($t);
        $fail = true;
        Event::listen(MessageSending::class, function () use (&$fail) {
            if ($fail) {
                throw new \RuntimeException('smtp is down');
            }
        });

        $done = $this->confirm($r->fresh());                                  // must not throw
        $this->assertSame(['succeeded', 'applied'], [$done->status, $done->effects_status]);
        $this->assertSame('refunded', $t[0]->fresh()->status);
        $this->assertSame(['canceled', 'canceled', 'canceled'], Ticket::where('order_id', $t[0]->id)->pluck('status')->all(), 'the tickets stay canceled');
        $meta = $done->fresh()->meta;
        $this->assertNotNull($meta['customer_emails']['completed']['failed_at']);
        $this->assertSame('RuntimeException', $meta['customer_emails']['completed']['error']);
        $this->assertContains('customer_email_failed', collect($done->fresh()->timeline())->pluck('event')->all());
        $this->assertTrue(\Spatie\Activitylog\Models\Activity::where('log_name', 'order_delivery')->where('description', 'Delivery failed: refund_completed')->exists());

        // staff can resend once the mail system works; it is delivered once
        $fail = false;
        $this->actingAs($this->approver)->post(route('admin.payments.refunds.resend-email', $r), ['kind' => 'completed'])->assertSessionHasNoErrors();
        $this->assertNotNull($r->fresh()->meta['customer_emails']['completed']['sent_at']);
        $this->assertTrue(\Spatie\Activitylog\Models\Activity::where('description', 'Delivery sent: refund_completed')->exists());
        $this->actingAs($this->approver)->post(route('admin.payments.refunds.resend-email', $r), ['kind' => 'completed']);
        $this->assertSame(2, $r->fresh()->meta['customer_emails']['completed']['attempts'], 'a delivered email is never re-sent');
    }

    public function test_legacy_orders_recipient_and_missing_address(): void
    {
        Mail::fake();
        $legacy = $this->paid('legacy', 2, 150);
        $r = $this->ask($legacy);
        $done = $this->confirm($r->fresh());
        $this->assertSame(['completed'], $this->kinds());
        Mail::assertSent(RefundCustomerMail::class, fn ($m) => $m->hasTo($legacy[0]->customer->email) && ! $m->hasTo($this->staff->email) && ! $m->hasTo($this->approver->email));
        $this->assertSame(1, Mail::sent(RefundCustomerMail::class)->count());
        $html = $this->body('completed', $done);
        $this->assertStringContainsString('Full refund', $html);
        $this->assertStringContainsString('300.00 EGP', $html);
        $this->assertClean($html, 'legacy');

        // an order without a valid e-mail is skipped, recorded, and nothing else is affected
        $noMail = $this->paid('legacy', 1, 100);
        $noMail[0]->customer->forceFill(['email' => ''])->save();
        $r2 = $this->confirm($this->ask($noMail));
        $this->assertSame('no_valid_recipient', $r2->fresh()->meta['customer_emails']['completed']['skipped']);
        $this->assertSame('refunded', $noMail[0]->fresh()->status);
        $this->assertSame(1, Mail::sent(RefundCustomerMail::class)->count());
    }

    public function test_the_email_is_only_sent_after_the_transaction_commits(): void
    {
        Mail::fake();
        $t = $this->paid();
        $r = $this->ask($t);
        $seenInside = null;
        \Illuminate\Support\Facades\DB::transaction(function () use ($r, &$seenInside) {
            $this->svc->confirmSucceeded($r, $this->approver, 'FAW-REF-9', 'verified in the provider dashboard', now()->toDateString());
            $seenInside = count($this->kinds());
        });
        $this->assertNotContains('completed', array_slice($this->kinds(), 0, $seenInside), 'not dispatched while the outer transaction is still open');
        $this->assertContains('completed', $this->kinds());
    }
}
