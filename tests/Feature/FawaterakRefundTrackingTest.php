<?php

namespace Tests\Feature;

use App\Http\Middleware\EnsureAdminPanelAccess;
use App\Models\Order;
use App\Models\PaymentAttempt;
use App\Models\PaymentMethod;
use App\Models\PaymentRefund;
use App\Models\Ticket;
use App\Models\User;
use App\Models\WebhookEvent;
use App\Services\Admin\DashboardMetrics;
use App\Services\Payments\PaymentService;
use App\Services\Payments\Refunds\RefundService;
use App\Services\Payments\Refunds\RefundTracking;
use App\Services\TicketIssuanceService;
use App\Support\SystemSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Tests\Support\BuildsMerchantAccounts;
use Tests\Support\BuildsOrders;
use Tests\TestCase;

/**
 * Fawaterak external refunds: tracking, the unsigned refund webhook (a hint, never a completion), and exactly-once confirmation.
 * Mocked provider only; no refund API is called because none is documented.
 */
class FawaterakRefundTrackingTest extends TestCase
{
    use BuildsMerchantAccounts, BuildsOrders, RefreshDatabase;

    private RefundService $svc;
    private User $admin;
    private User $agentA;
    private User $agentB;

    private const PERMS = ['payments.refunds.view', 'payments.refunds.create', 'payments.transactions.view'];

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(EnsureAdminPanelAccess::class);
        Mail::fake();
        $this->fakeProviders();
        config(['payments.fawaterak.verified' => true]);
        SystemSettings::set('order_assignment_enabled', true);
        SystemSettings::set('refund_approval_required', false);
        $this->svc = app(RefundService::class);
        $this->makeMethod('fawaterak', 'fawaterak_card', ['api_key' => 'L', 'provider_key' => 'FAWATERAK.1']);
        $this->admin = $this->staff(null, ['orders.view', 'orders.view_all', 'orders.update', 'dashboard.view', 'payments.refunds.approve', ...self::PERMS], ['can_receive_orders' => false]);
        $this->agentA = $this->staff('Order Agent', self::PERMS);
        $this->agentB = $this->staff('Order Agent', self::PERMS);
    }

    private function staff(?string $role, array $perms, array $flags = ['can_receive_orders' => true]): User
    {
        $u = User::factory()->create();
        $u->forceFill($flags)->save();
        $role && $u->assignRole($role);
        foreach ($perms as $p) {
            Permission::findOrCreate($p, 'web');
        }
        $u->givePermissionTo($perms);

        return $u->fresh();
    }

    /** A paid, ticketed order through the real payment flow (mocked Fawaterak), assigned to $agent. */
    private function paid(User $agent, int $people = 2, float $unit = 100, string $vendor = 'VENDOR-A'): PaymentAttempt
    {
        $acc = $this->account('fawaterak', 'FW'.Str::random(5), [], ['vendor_key' => $vendor]);
        $acc->forceFill(['refund_enabled_at' => now()])->save();
        $order = $this->makePendingOrder(User::factory()->create(), null, $people, $unit, 'fawaterak_card');
        $order->forceFill(['integration_account_id' => $acc->id, 'assigned_user_id' => $agent->id, 'assigned_at' => now()])->saveQuietly();
        app(PaymentService::class)->start($order->fresh(), PaymentMethod::where('code', 'fawaterak_card')->first());
        $a = $order->paymentAttempts()->firstOrFail();
        $this->fawInvoices[$a->transaction_id]['paid'] = true;
        $p = ['invoice_id' => $a->transaction_id, 'invoice_key' => 'IK1', 'payment_method' => 'Card', 'invoice_status' => 'paid'];
        $hash = hash_hmac('sha256', 'InvoiceId='.$p['invoice_id'].'&InvoiceKey=IK1&PaymentMethod=Card', $vendor);
        $this->postJson(route('front.fawaterak.webhook'), $p + ['hashKey' => $hash])->assertOk();
        $this->assertSame('paid', $order->fresh()->status);

        return $a->fresh();
    }

    private function refund(PaymentAttempt $a, string $type = 'full', ?string $amount = null, array $opts = []): PaymentRefund
    {
        return $this->svc->request($a, $this->admin, $type, $amount, 'customer cancelled the event', Str::uuid()->toString(), $opts);
    }

    private function note(PaymentAttempt|string $tx, string $amount, string $currency = 'EGP', string $status = 'approved'): \Illuminate\Testing\TestResponse
    {
        $id = $tx instanceof PaymentAttempt ? $tx->transaction_id : $tx;

        return $this->postJson(route('front.fawaterak.webhook'), ['transactionId' => $id, 'amount' => $amount, 'currency' => $currency, 'status' => $status, 'reason' => 'x', 'approvedAt' => '2026-10-30 12:00:00']);
    }

    private function confirm(PaymentRefund $r): PaymentRefund
    {
        return $this->svc->confirmSucceeded($r, $this->admin, 'FAW-REF-'.$r->id, 'verified in the Fawaterak dashboard', now()->toDateString(), 'provider_portal');
    }

    private function alerts(User $u): int
    {
        return $u->notifications()->whereIn('data->type', ['refund.notification', 'refund.unmatched'])->count();
    }

    private function statuses(Order $o): array
    {
        return Ticket::where('order_id', $o->id)->orderBy('id')->pluck('status')->all();
    }

    /* ------------------------------------------------------------ opening Fawaterak, references */

    public function test_the_modal_opens_with_the_stored_reference_creates_nothing_and_never_invents_a_link(): void
    {
        SystemSettings::set('refund_approval_required', true);
        $a = $this->paid($this->agentA);
        $r = $this->refund($a);
        $page = fn () => $this->actingAs($this->admin)->get(route('admin.payments.refunds.show', $r));

        $page()->assertOk()->assertSee('Available once the refund is approved')->assertDontSee('Open Transaction in Fawaterak');
        $this->svc->approve($r, $this->staff(null, ['payments.refunds.approve', 'payments.refunds.view', 'orders.view', 'orders.view_all'], ['can_receive_orders' => false]));

        config(['payments.fawaterak.dashboard_url' => null]);
        // no documented deep link: the dashboard address is only ever the configured one
        $res = $page()->assertOk()->assertSee('Open Transaction in Fawaterak')->assertSee($a->transaction_id)->assertSee('link is not configured')->assertSee('Copy invoice / transaction ID');
        $this->assertStringNotContainsString($a->order->payment_link_token ?: 'no-token', $res->getContent(), 'the customer payment link is never the admin link');
        $this->assertStringNotContainsString('FKEY-', $res->getContent(), 'no API key');
        $this->assertStringNotContainsString('Open Fawaterak dashboard', $res->getContent());

        foreach (['http://insecure.example/x', 'https://user:pw@dash.example/', 'https://dash.example/?token=abc', 'javascript:alert(1)', ''] as $bad) {
            config(['payments.fawaterak.dashboard_url' => $bad]);
            $this->assertStringNotContainsString('Open Fawaterak dashboard', $page()->getContent(), "unsafe dashboard url refused: $bad");
        }
        config(['payments.fawaterak.dashboard_url' => 'https://dashboard.example.test/']);
        $page()->assertSee('href="https://dashboard.example.test/"', false)->assertSee('Open Fawaterak dashboard');

        // clicking "open" is audit-only; refreshing and reopening never create refunds or move money
        $before = [PaymentRefund::count(), $r->fresh()->status, $this->svc->balance($a)['held']];
        for ($i = 0; $i < 3; $i++) {
            $this->actingAs($this->admin)->postJson(route('admin.payments.refunds.opened', $r))->assertOk();
            $this->actingAs($this->admin)->getJson(route('admin.payments.refunds.tracking', $r))->assertOk()->assertJson(['completed' => false, 'state' => 'waiting_external', 'verifiable_through_api' => false]);
        }
        $this->assertSame($before, [PaymentRefund::count(), $r->fresh()->status, $this->svc->balance($a)['held']]);
        $this->assertSame(1, collect($r->fresh()->timeline())->where('event', 'opened_in_provider')->count(), 'one audit entry per user per 5 minutes');
        $this->assertSame('paid', $a->order->fresh()->status);
    }

    public function test_an_operator_supplied_transaction_pattern_builds_the_exact_link_and_unsafe_patterns_are_refused(): void
    {
        $L = \App\Services\Payments\Refunds\FawaterakRefundLinks::class;
        config(['payments.fawaterak.dashboard_url' => null, 'payments.fawaterak.transaction_url' => 'https://merchant.example.test/invoices/{id}']);
        $this->assertSame('https://merchant.example.test/invoices/3331818773', $L::transactionUrl('3331818773'));
        $this->assertSame('https://merchant.example.test/invoices/a%2Fb%20c', $L::transactionUrl('a/b c'), 'the id is encoded');
        $this->assertNull($L::transactionUrl(''));
        foreach (['http://merchant.example.test/{id}', 'https://u:p@merchant.example.test/{id}', 'https://merchant.example.test/', 'https://merchant.example.test/{id}/{id}', 'javascript:alert({id})'] as $bad) {
            config(['payments.fawaterak.transaction_url' => $bad]);
            $this->assertNull($L::transactionUrl('1'), "refused: $bad");
        }
        config(['payments.fawaterak.transaction_url' => null]);
        $this->assertFalse($L::anyConfigured());
    }

    public function test_a_legacy_payment_without_a_stored_id_gets_an_actionable_message_and_a_recorded_reference_is_used(): void
    {
        $order = $this->makePendingOrder(User::factory()->create(), null, 2, 100, 'fawaterak_card');
        $order->forceFill(['status' => 'paid', 'paid_at' => now(), 'assigned_user_id' => $this->agentA->id])->saveQuietly();
        app(TicketIssuanceService::class)->issueIfPaid($order->fresh());
        $r = $this->svc->requestForOrder($order->fresh(), $this->admin, 'full', null, 'legacy order refund', 'leg-1', ['external_method' => 'provider_portal']);

        $this->actingAs($this->admin)->get(route('admin.payments.refunds.show', $r))->assertOk()->assertSee('holds no original Fawaterak invoice')->assertSee('Record original id');
        $this->assertNull(RefundTracking::snapshot($r)['invoice_id']);
        $this->assertSame('none', RefundTracking::snapshot($r)['reference_source']);

        $this->actingAs($this->admin)->post(route('admin.payments.refunds.original-reference', $r), ['original_reference' => 'has space'])->assertSessionHasErrors('refund');
        $this->actingAs($this->admin)->post(route('admin.payments.refunds.original-reference', $r), ['original_reference' => 'INV-LEGACY-42'])->assertSessionHasNoErrors();
        $this->assertSame(['INV-LEGACY-42', 'recorded'], [RefundTracking::snapshot($r->fresh())['invoice_id'], RefundTracking::snapshot($r->fresh())['reference_source']]);
        $this->assertSame(0, PaymentAttempt::count(), 'no fake attempt is created');

        // the unsigned notification now matches the legacy refund by that exact recorded id, and only hints
        $this->note('INV-LEGACY-42', '200')->assertOk();
        $this->assertSame('notification_received', RefundTracking::state($r->fresh()));
        $this->assertSame('paid', $order->fresh()->status);
        $this->assertSame(['not_checked_in', 'not_checked_in'], $this->statuses($order));

        // a payment whose id TKT House already holds cannot be overwritten
        $a = $this->paid($this->agentA);
        $r2 = $this->refund($a);
        $this->actingAs($this->admin)->post(route('admin.payments.refunds.original-reference', $r2), ['original_reference' => 'INV-FAKE'])->assertSessionHasErrors('refund');
    }

    public function test_the_legacy_form_stores_the_original_reference_from_the_request(): void
    {
        $order = $this->makePendingOrder(User::factory()->create(), null, 1, 100, 'fawaterak_card');
        $order->forceFill(['status' => 'paid', 'paid_at' => now()])->saveQuietly();
        $r = $this->svc->requestForOrder($order->fresh(), $this->admin, 'full', null, 'legacy with id', 'leg-2', ['original_reference' => 'INV-OLD-7']);
        $this->assertSame('INV-OLD-7', $r->meta['original_transaction_ref']);
    }

    /* ------------------------------------------------------------ the unsigned refund webhook */

    public function test_a_matching_unsigned_notification_only_marks_it_for_verification_and_alerts_authorized_staff(): void
    {
        $a = $this->paid($this->agentA, 2, 100);
        $order = $a->order;
        $r = $this->refund($a);
        $this->note($a, '200')->assertOk()->assertJson(['authenticated' => false]);

        $fresh = $r->fresh();
        $this->assertSame('processing', $fresh->status, 'a notification never changes the money state');
        $this->assertSame('notification_received', RefundTracking::state($fresh));
        $this->assertTrue($fresh->meta['provider_reported_approval']);
        $this->assertFalse($fresh->meta['provider_notification']['authenticated']);
        $this->assertSame('paid', $order->fresh()->status);
        $this->assertSame(['not_checked_in', 'not_checked_in'], $this->statuses($order));
        $this->assertSame([0.0, 0], array_values(array_intersect_key((new DashboardMetrics([], null, $this->admin, []))->refundStats(now()->subDay(), now()->endOfDay()), array_flip(['amount', 'orders']))));
        $snap = RefundTracking::snapshot($fresh);
        $this->assertFalse($snap['completed']);
        $this->assertStringContainsString('needs verification', $snap['label']);

        // only staff who may act on this order are told; nobody else
        $this->assertSame([1, 1, 0], [$this->alerts($this->agentA), $this->alerts($this->admin), $this->alerts($this->agentB)]);

        // modal closed or open: the same stored state; repeating the webhook changes and alerts nothing more
        for ($i = 0; $i < 4; $i++) {
            $this->note($a, '200')->assertOk();
        }
        $this->assertSame(1, collect($r->fresh()->timeline())->where('event', 'provider_notification_received')->count());
        $this->assertSame([1, 1], [$this->alerts($this->agentA), $this->alerts($this->admin)]);
        $this->assertSame(1, WebhookEvent::where('event_type', 'refund')->count(), 'recorded idempotently');
        $this->assertSame(1, PaymentRefund::count());
    }

    public function test_forged_wrong_account_wrong_amount_currency_status_and_ambiguous_notifications_are_flagged_and_change_nothing(): void
    {
        $a = $this->paid($this->agentA, 2, 100);
        $b = $this->paid($this->agentB, 1, 100, 'VENDOR-B');
        $r = $this->refund($a);   // 200 on A's account

        // forged: unknown transaction id, malformed bodies
        $this->note('INV-NOT-OURS', '200')->assertOk();
        $this->postJson(route('front.fawaterak.webhook'), ['transactionId' => $a->transaction_id, 'amount' => 'abc', 'currency' => 'EGP', 'status' => 'approved'])->assertOk();
        $this->postJson(route('front.fawaterak.webhook'), ['transactionId' => $a->transaction_id, 'amount' => '200', 'currency' => 'EG', 'status' => 'approved'])->assertOk();
        // wrong amount / currency / status for the right payment
        $this->note($a, '150')->assertOk();
        $this->note($a, '200', 'USD')->assertOk();
        $this->note($a, '200', 'EGP', 'rejected')->assertOk();
        $this->note($a, '200', 'EGP', 'pending')->assertOk();
        // another merchant account's transaction has no refund request of its own: A's refund is untouched
        $this->note($b, '200')->assertOk();

        $this->assertSame('waiting_external', RefundTracking::state($r->fresh()));
        $this->assertSame(['paid', 'paid'], [$a->order->fresh()->status, $b->order->fresh()->status]);
        $this->assertSame(0, collect($r->fresh()->timeline())->where('event', 'provider_notification_received')->count());
        $reviews = WebhookEvent::where('event_type', 'refund')->get();
        $this->assertGreaterThanOrEqual(7, $reviews->count());
        $this->assertTrue($reviews->every(fn ($e) => $e->processing_status === 'needs_review'), 'every unmatched / mismatching event is retained for review');
        $this->assertSame('unsupported', $reviews->first()->signature_status, 'unsigned is recorded as such');
        $this->assertTrue($this->alerts($this->admin) > 0, 'global staff are alerted');
        $this->assertSame(0, $this->alerts($this->agentB), 'an agent is not told about transactions of other agents');

        // ambiguity: two outstanding requests with the SAME amount on one payment => never applied
        $a2 = $this->paid($this->agentA, 4, 100, 'VENDOR-C');
        $p1 = $this->refund($a2, 'partial', '100', ['no_tickets' => true]);
        $p1->forceFill(['status' => 'requested'])->save();
        $second = PaymentRefund::create(['internal_reference' => 'REF-DUPAMOUNT1', 'payment_attempt_id' => $a2->id, 'order_id' => $a2->order_id, 'integration_account_id' => $a2->integration_account_id, 'provider' => 'fawaterak', 'mode' => 'manual',
            'type' => 'partial', 'status' => 'requested', 'awaiting_approval' => false, 'currency' => 'EGP', 'amount_minor' => 10000, 'reason' => 'second same amount', 'idempotency_key' => Str::random(40), 'requested_at' => now()]);
        $this->note($a2, '100')->assertOk();
        $this->assertSame(0, collect($p1->fresh()->timeline())->where('event', 'provider_notification_received')->count());
        $this->assertSame(0, collect($second->fresh()->timeline())->where('event', 'provider_notification_received')->count());
        $this->assertSame('needs_review', WebhookEvent::where('event_type', 'refund')->latest('id')->first()->processing_status);

        // a refund that still waits for approval is not matched
        SystemSettings::set('refund_approval_required', true);
        $a3 = $this->paid($this->agentA, 1, 100, 'VENDOR-D');
        $waiting = $this->refund($a3);
        $this->note($a3, '100')->assertOk();
        $this->assertTrue($waiting->fresh()->awaiting_approval);
        $this->assertSame(0, collect($waiting->fresh()->timeline())->where('event', 'provider_notification_received')->count());
    }

    public function test_several_outstanding_requests_are_told_apart_by_exact_amount_and_partial_refunds_work(): void
    {
        $a = $this->paid($this->agentA, 4, 100);
        $t = Ticket::where('order_id', $a->order_id)->orderBy('id')->get();
        $p1 = $this->refund($a, 'partial', null, ['tickets' => [$t[0]->id]]);                    // 100
        $this->svc->markSubmitted($p1, $this->admin);
        $this->confirm($p1->fresh());
        $p2 = $this->refund($a, 'partial', null, ['tickets' => [$t[1]->id, $t[2]->id]]);        // 200

        $this->note($a, '200')->assertOk();
        $this->assertSame('notification_received', RefundTracking::state($p2->fresh()));
        $this->assertSame('completed', RefundTracking::state($p1->fresh()), 'the earlier, final refund is untouched');
        $this->assertSame(1, collect($p1->fresh()->timeline())->where('event', 'confirmed_by_staff')->count());
        $this->assertSame(['canceled', 'not_checked_in', 'not_checked_in', 'not_checked_in'], $this->statuses($a->order), 'the second refund cancels nothing yet');
    }

    /* ------------------------------------------------------------ completion only through the audited confirmation */

    public function test_completion_needs_independent_confirmation_and_then_updates_everything_exactly_once(): void
    {
        $a = $this->paid($this->agentA, 2, 100);
        $order = $a->order;
        $ticket = Ticket::where('order_id', $order->id)->first();
        $r = $this->refund($a);
        $this->note($a, '200')->assertOk();
        $win = [now()->subDay(), now()->endOfDay()];
        $stat = fn (User $u) => array_values(array_intersect_key((new DashboardMetrics([], null, $u, []))->refundStats(...$win), array_flip(['amount', 'orders'])));

        // approval without completion: nothing is claimed
        $this->assertFalse(RefundTracking::snapshot($r->fresh())['completed']);
        $this->assertSame([0.0, 0], $stat($this->agentA));

        // a person confirms with provider evidence (existing audited service)
        $done = $this->confirm($r->fresh());
        $this->assertSame(['succeeded', 'FAW-REF-'.$r->id, 'applied'], [$done->status, $done->provider_reference, $done->effects_status]);
        $this->assertSame('refunded', $order->fresh()->status);
        $this->assertSame(['canceled', 'canceled'], $this->statuses($order));
        $snap = RefundTracking::snapshot($done);
        $this->assertTrue($snap['completed']);
        $this->assertSame('Refund completed', $snap['label']);
        $this->assertSame([200.0, 1], $stat($this->agentA));
        $this->assertSame([200.0, 1], $stat($this->admin));
        $this->assertSame([0.0, 0], $stat($this->agentB));

        // scanner rejects with the reason
        $scanner = $this->staff(null, ['scanner.access', 'tickets.view'], ['can_receive_orders' => false]);
        $this->actingAs($scanner)->postJson(route('admin.tickets.scanner.lookup'), ['code' => $ticket->ticket_number])->assertStatus(409)->assertJsonFragment(['code' => 'refunded']);

        // exactly once: repeated webhook, repeated confirmation, polling
        $this->note($a, '200')->assertOk();
        $this->assertSame('ignored', WebhookEvent::where('event_type', 'refund')->latest('id')->first()->processing_status);
        try {
            $this->confirm($done);
            $this->fail('second confirmation');
        } catch (\Illuminate\Validation\ValidationException) {
        }
        $this->actingAs($this->admin)->getJson(route('admin.payments.refunds.tracking', $r))->assertOk()->assertJson(['completed' => true]);
        $this->assertSame(2, \App\Models\ScanLog::where('action', 'refund_canceled')->count());
        $this->assertSame([200.0, 1], $stat($this->admin));
        $this->assertSame('refunded', $order->fresh()->status);

        // a late/duplicate PAYMENT webhook cannot undo it
        $p = ['invoice_id' => $a->transaction_id, 'invoice_key' => 'IK1', 'payment_method' => 'Card', 'invoice_status' => 'paid'];
        $this->postJson(route('front.fawaterak.webhook'), $p + ['hashKey' => hash_hmac('sha256', 'InvoiceId='.$a->transaction_id.'&InvoiceKey=IK1&PaymentMethod=Card', 'VENDOR-A')])->assertOk();
        $this->assertSame(['canceled', 'canceled'], $this->statuses($order));
        $this->assertSame('refunded', $order->fresh()->status);
    }

    /* ------------------------------------------------------------ authorization of tracking and polling */

    public function test_tracking_and_polling_follow_order_assignment_and_permissions(): void
    {
        $a = $this->paid($this->agentA);
        $r = $this->refund($a);
        $this->note($a, '200');

        $own = $this->actingAs($this->agentA)->getJson(route('admin.payments.refunds.tracking', $r))->assertOk()->json();
        $this->assertSame($a->transaction_id, $own['invoice_id']);
        $this->actingAs($this->agentB)->getJson(route('admin.payments.refunds.tracking', $r))->assertNotFound();
        $this->actingAs($this->agentB)->postJson(route('admin.payments.refunds.opened', $r))->assertNotFound();
        $this->actingAs($this->agentB)->post(route('admin.payments.refunds.original-reference', $r), ['original_reference' => 'X'])->assertNotFound();
        $page = $this->actingAs($this->agentB)->get(route('admin.payments.refunds.show', $r));
        $page->assertNotFound();
        $this->assertStringNotContainsString($a->transaction_id, $page->getContent());

        $noPerm = $this->staff('Order Agent', []);
        $noPerm->forceFill([])->save();
        $this->actingAs($noPerm)->getJson(route('admin.payments.refunds.tracking', $r))->assertForbidden();

        // an agent with view-only rights can poll their own but cannot act
        $viewer = $this->staff('Order Agent', ['payments.refunds.view']);
        $own2 = $this->paid($viewer);
        $r2 = $this->refund($own2);
        $this->actingAs($viewer)->getJson(route('admin.payments.refunds.tracking', $r2))->assertOk();
        $this->actingAs($viewer)->postJson(route('admin.payments.refunds.opened', $r2))->assertForbidden();

        // reassignment moves the access, never the payment reference or merchant account
        app(\App\Orders\OrderAssignmentService::class)->assign($a->order->fresh(), $this->agentB, $this->admin, 'moved');
        $this->actingAs($this->agentA)->getJson(route('admin.payments.refunds.tracking', $r))->assertNotFound();
        $moved = $this->actingAs($this->agentB)->getJson(route('admin.payments.refunds.tracking', $r))->assertOk()->json();
        $this->assertSame($a->transaction_id, $moved['invoice_id']);
        $this->assertSame($a->integration_account_id, $r->fresh()->integration_account_id);
    }
}
