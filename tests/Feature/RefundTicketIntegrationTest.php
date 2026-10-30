<?php

namespace Tests\Feature;

use App\Http\Middleware\EnsureAdminPanelAccess;
use App\Models\Event;
use App\Models\IssuedTicket;
use App\Models\Order;
use App\Models\PaymentAttempt;
use App\Models\PaymentRefund;
use App\Models\ScanLog;
use App\Models\Ticket;
use App\Models\User;
use App\Services\Admin\DashboardMetrics;
use App\Services\Payments\Refunds\RefundService;
use App\Services\TicketIssuanceService;
use App\Support\SystemSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Permission;
use Tests\Support\BuildsMerchantAccounts;
use Tests\Support\BuildsOrders;
use Tests\TestCase;

/**
 * Confirmed refunds -> ticket cancellation, order status, scanner and dashboard figures. Mocked gateways only: no real money moves.
 */
class RefundTicketIntegrationTest extends TestCase
{
    use BuildsMerchantAccounts, BuildsOrders, RefreshDatabase;

    private RefundService $svc;
    private User $staff;
    private User $approver;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        $this->fakeProviders();
        config(['payments.refunds.live_api_enabled' => true, 'payments.fake.enabled' => true]);
        SystemSettings::set('refund_approval_required', false);
        $this->svc = app(RefundService::class);
        $this->makeMethod('fawaterak', 'fawaterak_card', ['api_key' => 'L', 'provider_key' => 'FAWATERAK.1']);
        $this->makeMethod('fake', 'fake_card');
        $this->staff = $this->user(['payments.refunds.create', 'payments.refunds.view', 'payments.transactions.view', 'orders.view']);
        $this->approver = $this->user(['payments.refunds.create', 'payments.refunds.approve', 'payments.refunds.view', 'orders.view']);
    }

    private function user(array $perms): User
    {
        $this->withoutMiddleware(EnsureAdminPanelAccess::class);
        foreach ($perms as $p) {
            Permission::findOrCreate($p, 'web');
        }

        return tap(User::factory()->create())->givePermissionTo($perms);
    }

    /** A paid order whose tickets were really issued. kind: manual (Fawaterak) | api (fake gateway) | legacy (no payment attempt at all). */
    private function paidOrder(string $kind = 'manual', int $people = 3, float $unit = 100, ?Event $event = null): array
    {
        $method = $kind === 'api' ? 'fake_card' : 'fawaterak_card';
        $order = $this->makePendingOrder(User::factory()->create(), $event, $people, $unit, $method);
        $attempt = null;
        if ($kind !== 'legacy') {
            $acc = $this->account($kind === 'api' ? 'fake' : 'fawaterak', 'ACC'.Str::random(6));
            $acc->forceFill(['refund_enabled_at' => now()])->save();
            $order->forceFill(['integration_account_id' => $acc->id])->saveQuietly();
            $attempt = PaymentAttempt::create(['order_id' => $order->id, 'provider' => $acc->provider, 'method_code' => $method, 'amount' => $unit * $people, 'currency' => 'EGP', 'status' => 'paid',
                'transaction_id' => (string) random_int(100000, 999999), 'reference' => $order->order_number.'-r', 'integration_account_id' => $acc->id, 'internal_reference' => PaymentAttempt::newInternalReference(),
                'paid_at' => now(), 'settled_at' => now()]);
        }
        $order->forceFill(['status' => 'paid', 'paid_at' => now()])->saveQuietly();
        app(TicketIssuanceService::class)->issueIfPaid($order->fresh());

        return [$order->fresh(), $attempt];
    }

    private function request(array $target, string $type = 'full', ?string $amount = null, array $options = [], ?User $by = null, string $reason = 'venue relocation'): PaymentRefund
    {
        [$order, $attempt] = $target;
        $by ??= $this->staff;
        $key = Str::uuid()->toString();

        return $attempt
            ? $this->svc->request($attempt, $by, $type, $amount, $reason, $key, $options)
            : $this->svc->requestForOrder($order, $by, $type, $amount, $reason, $key, $options);
    }

    private function confirm(PaymentRefund $r, ?User $by = null): PaymentRefund
    {
        return $this->svc->confirmSucceeded($r, $by ?? $this->approver, 'EXT-'.Str::random(5), 'portal screenshot verified', now()->toDateString());
    }

    private function statuses(Order $order): array
    {
        return Ticket::where('order_id', $order->id)->orderBy('id')->pluck('status')->all();
    }

    /* ------------------------------------------------------------ 1-3: full / legacy / partial */

    public function test_full_manual_refund_of_a_modern_paid_order_cancels_every_ticket_once_confirmed(): void
    {
        $target = $this->paidOrder('manual');
        $order = $target[0];
        $this->assertCount(3, Ticket::where('order_id', $order->id)->get());

        $r = $this->request($target, 'full', null, ['external_method' => 'provider_portal', 'internal_notes' => 'secret internal note']);
        $this->assertSame(['manual', 'requested', 'pending'], [$r->mode, $r->status, $r->effects_status]);
        $this->assertSame(['not_checked_in', 'not_checked_in', 'not_checked_in'], $this->statuses($order), 'a request cancels nothing');

        $done = $this->confirm($r);

        $this->assertSame('succeeded', $done->status);
        $this->assertSame('applied', $done->effects_status);
        $this->assertSame('refunded', $order->fresh()->status);
        $this->assertSame(['canceled', 'canceled', 'canceled'], $this->statuses($order));
        $t = Ticket::where('order_id', $order->id)->first();
        $this->assertSame(['refund', $r->id, 'venue relocation'], [$t->cancellation_source, $t->canceled_by_refund_id, $t->cancellation_reason]);
        $this->assertNotNull($t->canceled_at);
        $this->assertTrue($t->isRefundCanceled());
        $this->assertSame(3, IssuedTicket::where('order_id', $order->id)->count(), 'ticket records are never deleted');
        $this->assertSame(3, $done->ticketLinks()->whereNotNull('canceled_at')->count());
        $this->assertSame(3, ScanLog::where('action', 'refund_canceled')->count());
        $this->assertSame($this->approver->id, $done->confirmed_by);
        $this->assertNotNull($done->refunded_at);
    }

    public function test_full_manual_refund_of_a_legacy_paid_order_without_a_payment_attempt(): void
    {
        $target = $this->paidOrder('legacy', 2, 150);
        $order = $target[0];
        $this->assertSame(0, PaymentAttempt::count());
        $this->assertTrue($this->svc->isLegacyOrder($order));

        $r = $this->request($target, 'full', null, ['external_method' => 'cash']);

        $this->assertNull($r->payment_attempt_id);
        $this->assertSame(['manual', 'manual', 30000, 30000], [$r->mode, $r->provider, $r->amount_minor, $r->captured_minor]);
        $this->assertSame(0, PaymentAttempt::count(), 'no gateway transaction is invented for a legacy order');
        $this->assertSame('paid', $order->fresh()->status);

        $this->confirm($r);
        $this->assertSame('refunded', $order->fresh()->status);
        $this->assertSame(['canceled', 'canceled'], $this->statuses($order));
        $this->assertSame(0, PaymentAttempt::count());
        $this->assertSame('300.00', $order->fresh()->total_amount === null ? '' : number_format((float) $order->fresh()->total_amount, 2, '.', ''), 'the original amount is untouched');

        $this->expectException(ValidationException::class);
        $this->request($target, 'partial', '10', ['no_tickets' => true]); // nothing left to refund
    }

    public function test_legacy_refund_through_the_order_page_form_and_permissions(): void
    {
        $target = $this->paidOrder('legacy', 2, 100);
        $order = $target[0];
        $payload = ['type' => 'full', 'reason' => 'customer cancelled the event', 'idempotency_key' => 'leg-1', 'confirm' => 1, 'external_method' => 'bank_transfer'];

        $this->actingAs($this->staff)->get(route('admin.orders.show', $order))->assertOk()->assertSee('Record manual refund')->assertSee('Remaining refundable')->assertSee('Refund method');
        $nobody = $this->user(['orders.view']);
        $this->actingAs($nobody)->post(route('admin.payments.refunds.store-order', $order), $payload)->assertForbidden();
        $this->assertSame(0, PaymentRefund::count());

        $this->actingAs($this->staff)->post(route('admin.payments.refunds.store-order', $order), $payload)->assertRedirect();
        $this->actingAs($this->staff)->post(route('admin.payments.refunds.store-order', $order), $payload)->assertRedirect(); // double submit
        $this->assertSame(1, PaymentRefund::count(), 'a repeated form submission is idempotent');
        $this->assertSame('bank_transfer', PaymentRefund::first()->external_method);

        // a payment with an online attempt must be refunded through that payment, not as a legacy order
        [$modern] = $this->paidOrder('manual');
        $this->actingAs($this->staff)->post(route('admin.payments.refunds.store-order', $modern), $payload)->assertSessionHasErrors('refund');
    }

    public function test_partial_refund_of_one_ticket_cancels_only_that_ticket_and_leaves_the_rest_scannable(): void
    {
        $target = $this->paidOrder('manual', 3, 100);
        $order = $target[0];
        $t = Ticket::where('order_id', $order->id)->orderBy('id')->get();

        try {
            $this->request($target, 'partial', '50');
            $this->fail('a partial refund must name its tickets or explicitly refuse to touch them');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('Select the ticket', $e->errors()['refund'][0]);
        }
        try {
            $this->request($target, 'partial', '150', ['tickets' => [$t[0]->id]]);
            $this->fail('more than the selected ticket was paid');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('exceeds what the selected', $e->errors()['refund'][0]);
        }

        $r = $this->request($target, 'partial', null, ['tickets' => [$t[0]->id]]); // amount defaults to the ticket's value
        $this->assertSame(10000, $r->amount_minor);
        $this->assertSame(['not_checked_in', 'not_checked_in', 'not_checked_in'], $this->statuses($order));
        $this->confirm($r);

        $this->assertSame(['canceled', 'not_checked_in', 'not_checked_in'], $this->statuses($order));
        $this->assertSame('partially_refunded', $order->fresh()->status);
        $this->assertSame(20000, $this->svc->balance($target[1])['available']);

        // the same ticket cannot be selected again; the others can, and the last refund completes the order
        try {
            $this->request($target, 'partial', '10', ['tickets' => [$t[0]->id]]);
            $this->fail('already refunded');
        } catch (ValidationException) {
        }
        $this->confirm($this->request($target, 'partial', null, ['tickets' => [$t[1]->id, $t[2]->id]]));
        $this->assertSame('refunded', $order->fresh()->status);
        $this->assertSame(['canceled', 'canceled', 'canceled'], $this->statuses($order));
    }

    public function test_money_only_partial_refund_never_touches_tickets(): void
    {
        $target = $this->paidOrder('manual', 2, 100);
        $r = $this->request($target, 'partial', '20', ['no_tickets' => true]);
        $this->assertSame('not_applicable', $r->effects_status);
        $this->confirm($r);
        $this->assertSame(['not_checked_in', 'not_checked_in'], $this->statuses($target[0]));
        $this->assertSame('partially_refunded', $target[0]->fresh()->status);
    }

    /* ------------------------------------------------------------ 4-6: only the confirmed success cancels */

    public function test_nothing_is_canceled_while_pending_processing_failed_rejected_or_unknown(): void
    {
        $target = $this->paidOrder('manual');
        $order = $target[0];
        $untouched = fn () => $this->assertSame(['not_checked_in', 'not_checked_in', 'not_checked_in'], $this->statuses($order)) ?: $this->assertSame('paid', $order->fresh()->status);

        $r = $this->request($target);
        $untouched();
        $this->svc->markSubmitted($r, $this->approver);
        $this->assertSame('processing', $r->fresh()->status);
        $untouched();

        $failed = $this->svc->confirmFailed($r->fresh(), $this->approver, 'the provider portal shows it failed');
        $this->assertSame('failed', $failed->status);
        $untouched();
        $this->assertSame(30000, $this->svc->balance($target[1])['available'], 'a failed refund frees the balance');

        // rejected (four-eyes on)
        SystemSettings::set('refund_approval_required', true);
        $r2 = $this->request($target);
        $this->assertTrue($r2->awaiting_approval);
        $this->svc->reject($r2, $this->approver, 'not allowed by policy');
        $this->assertSame('rejected', $r2->fresh()->status);
        $untouched();

        // unknown (API refund whose request timed out)
        SystemSettings::set('refund_approval_required', false);
        $api = $this->paidOrder('api');
        $r3 = $this->request($api);
        $r3->forceFill(['status' => 'unknown'])->save();
        $this->assertSame(['not_checked_in', 'not_checked_in', 'not_checked_in'], $this->statuses($api[0]));
        $this->assertSame('paid', $api[0]->fresh()->status);
    }

    /* ------------------------------------------------------------ 7-9: scanner */

    private function scanner(): User
    {
        return $this->user(['scanner.access', 'tickets.view']);
    }

    public function test_scanner_rejects_a_refunded_ticket_with_a_clear_reason_and_no_financial_data(): void
    {
        $target = $this->paidOrder('manual', 2, 123);
        $order = $target[0];
        $ticket = Ticket::where('order_id', $order->id)->first();
        $r = $this->request($target, 'full', null, ['internal_notes' => 'secret internal note'], null, 'venue relocation');
        $this->confirm($r);
        $scanner = $this->scanner();

        $res = $this->actingAs($scanner)->postJson(route('admin.tickets.scanner.lookup'), ['code' => $ticket->ticket_number]);
        $res->assertStatus(409)->assertJson(['ok' => false, 'admit' => false, 'code' => 'refunded']);
        $this->assertStringContainsString('REFUNDED / CANCELED', $res->json('message'));
        $this->assertStringContainsString('Refund confirmed — venue relocation', $res->json('message'));
        $html = $res->json('html');
        $this->assertStringContainsString('REFUNDED / CANCELED', $html);
        $this->assertStringContainsString('Refund confirmed — venue relocation', $html);
        $this->assertStringContainsString($ticket->fresh()->canceled_at->format('d M Y'), $html);
        $this->assertStringNotContainsString('Check In', $html);
        foreach (['secret internal note', $r->internal_reference, 'EXT-', '123.00', '246.00', 'fawaterak'] as $leak) {
            $this->assertStringNotContainsStringIgnoringCase($leak, $html.$res->json('message'), "scanner output must not expose: $leak");
        }

        $fresh = $ticket->fresh();
        $this->assertSame(['canceled', null], [$fresh->status, $fresh->checked_in_at]);
        $this->assertSame(1, ScanLog::where('ticket_id', $ticket->id)->where('action', 'lookup_rejected_refunded')->count(), 'the rejected scan is audited');
        $this->assertSame(0, ScanLog::where('ticket_id', $ticket->id)->whereIn('action', ['lookup_success', 'status_update'])->count(), 'it counts as neither a scan nor a check-in');

        // a status change posted straight to the server (old page, forged form) is refused too
        $this->actingAs($scanner)->post(route('admin.tickets.scanner.status', $ticket), ['status' => 'checked_in'])->assertRedirect()->assertSessionHas('error');
        $this->actingAs($scanner)->post(route('admin.tickets.scanner.status', $ticket), ['status' => 'not_checked_in'])->assertRedirect();
        $fresh = $ticket->fresh();
        $this->assertSame(['canceled', null], [$fresh->status, $fresh->checked_in_at]);
        $this->assertSame(2, ScanLog::where('ticket_id', $ticket->id)->where('action', 'status_update_rejected_refunded')->count());
        $this->assertSame(0, ScanLog::where('ticket_id', $ticket->id)->where('action', 'status_update')->count());

        // a freshly loaded page (new session) shows the banner server-side as well
        $this->actingAs($this->scanner())->get(route('admin.tickets.scanner', ['code' => $ticket->ticket_number]))->assertOk()->assertSee('REFUNDED / CANCELED')->assertSee('Refund confirmed — venue relocation')->assertDontSee('secret internal note');
        // the ordinary admin screens cannot revive or delete it either
        $admin = $this->user(['tickets.update', 'tickets.delete', 'tickets.view']);
        $this->actingAs($admin)->put(route('admin.tickets.update', $ticket), ['status' => 'not_checked_in'])->assertSessionHasErrors('status');
        $this->actingAs($admin)->delete(route('admin.tickets.destroy', $ticket))->assertSessionHas('error');
        $this->assertSame('canceled', $ticket->fresh()->status);
        $this->assertNotNull(Ticket::find($ticket->id));
    }

    public function test_scanner_still_accepts_the_other_tickets_of_a_partially_refunded_order_and_distinguishes_other_cases(): void
    {
        $target = $this->paidOrder('manual', 3, 100);
        $order = $target[0];
        [$refunded, $valid, $adminCanceled] = Ticket::where('order_id', $order->id)->orderBy('id')->get()->all();
        $this->confirm($this->request($target, 'partial', null, ['tickets' => [$refunded->id]]));
        $scanner = $this->scanner();

        $ok = $this->actingAs($scanner)->postJson(route('admin.tickets.scanner.lookup'), ['code' => $valid->ticket_number]);
        $ok->assertOk()->assertJson(['ok' => true]);
        $this->assertStringNotContainsString('REFUNDED', $ok->json('html'));
        $this->actingAs($scanner)->post(route('admin.tickets.scanner.status', $valid), ['status' => 'checked_in'])->assertRedirect();
        $this->assertSame('checked_in', $valid->fresh()->status);

        // ordinary administrative cancellation is NOT shown as a refund
        $this->actingAs($scanner)->post(route('admin.tickets.scanner.status', $adminCanceled), ['status' => 'canceled'])->assertRedirect();
        $res = $this->actingAs($scanner)->postJson(route('admin.tickets.scanner.lookup'), ['code' => $adminCanceled->ticket_number]);
        $res->assertOk();
        $this->assertStringNotContainsString('REFUNDED', $res->json('html'));
        $this->assertStringContainsString('Canceled', $res->json('html'));

        // invalid code
        $this->actingAs($scanner)->postJson(route('admin.tickets.scanner.lookup'), ['code' => '999999999999'])->assertStatus(404)->assertJson(['ok' => false]);

        // a ticket of another event is "not found" for a scanner limited to one event, never a refund message
        $eventA = $this->makeEvent(['name' => 'Alpha Night']);
        $eventB = $this->makeEvent(['name' => 'Beta Night']);
        $tb = Ticket::create(['name' => 'Beta Night - GA', 'price' => 10, 'status' => 'not_checked_in', 'ticket_number' => '5550001112', 'qr_payload' => '5550001112', 'event_id' => $eventB->id]);
        $limited = $this->scanner();
        $limited->managedEvents()->attach($eventA->id);
        $this->actingAs($limited->fresh())->postJson(route('admin.tickets.scanner.lookup'), ['code' => $tb->ticket_number])->assertStatus(404);
    }

    /* ------------------------------------------------------------ 10-12: duplicates, webhooks, over-refund */

    public function test_a_second_confirmation_or_a_repeated_provider_signal_cannot_cancel_twice_or_double_the_money(): void
    {
        $target = $this->paidOrder('manual');
        $r = $this->request($target);
        $stale = $r->fresh();
        $this->confirm($r);

        try {
            $this->confirm($stale); // a second click / a second tab holding the old page
            $this->fail('duplicate confirmation');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('already succeeded', $e->errors()['refund'][0]);
        }
        $this->assertSame(3, ScanLog::where('action', 'refund_canceled')->count());
        $this->assertSame(30000, $this->svc->balance($target[1])['refunded']);
        $this->assertSame(1, PaymentRefund::count());

        // API refund: the provider answers "succeeded" on every inquiry/webhook, the effects run once
        $api = $this->paidOrder('api');
        $a = $this->request($api);
        $a->forceFill(['meta' => array_merge($a->meta, ['fake_refund_state' => 'succeeded'])])->save();
        $this->svc->inquire($a->fresh());
        $this->svc->inquire($a->fresh());
        $this->svc->inquire($a->fresh());
        $this->assertSame(3, ScanLog::where('action', 'refund_canceled')->where('payload', 'like', $a->internal_reference.'%')->count());
        $this->assertSame('refunded', $api[0]->fresh()->status);
        $this->assertSame(30000, $this->svc->balance($api[1])['refunded']);
    }

    public function test_the_amount_can_never_exceed_what_is_left_including_pending_requests(): void
    {
        $target = $this->paidOrder('manual', 3, 100);
        $t = Ticket::where('order_id', $target[0]->id)->orderBy('id')->get();
        $this->request($target, 'partial', '100', ['tickets' => [$t[0]->id]]);
        $this->expectException(ValidationException::class);
        try {
            $this->svc->request($target[1], $this->staff, 'partial', '250', 'too much for this order', 'k-over', ['no_tickets' => true]);
        } finally {
            $this->assertSame(1, PaymentRefund::count());
        }
    }

    public function test_one_refund_in_flight_at_a_time_also_for_legacy_orders(): void
    {
        $target = $this->paidOrder('legacy', 2, 100);
        $this->request($target, 'partial', '50', ['no_tickets' => true]);
        $this->expectException(ValidationException::class);
        $this->request($target, 'partial', '50', ['no_tickets' => true]);
    }

    /* ------------------------------------------------------------ 13: checked-in tickets */

    public function test_an_already_checked_in_ticket_keeps_its_check_in_record_and_is_flagged(): void
    {
        $target = $this->paidOrder('manual', 2, 100);
        [$in, $out] = Ticket::where('order_id', $target[0]->id)->orderBy('id')->get()->all();
        $scanner = $this->scanner();
        $this->actingAs($scanner)->post(route('admin.tickets.scanner.status', $in), ['status' => 'checked_in'])->assertRedirect();
        $checkedAt = $in->fresh()->checked_in_at;
        $before = ScanLog::where('ticket_id', $in->id)->count();
        $this->assertNotNull($checkedAt);

        $this->confirm($this->request($target, 'full'));

        $in = $in->fresh();
        $this->assertSame('canceled', $in->status);
        $this->assertEquals($checkedAt, $in->checked_in_at, 'the check-in time is kept');
        $this->assertSame('refunded_after_check_in', $in->refund_flag);
        $this->assertTrue($in->refundCancellations()->first()->was_checked_in);
        $this->assertSame($before + 1, ScanLog::where('ticket_id', $in->id)->count(), 'earlier scan history intact; one audit row added');
        $this->assertSame('status_update', ScanLog::where('ticket_id', $in->id)->orderBy('id')->first()->action);
        $this->assertNull($out->fresh()->refund_flag);
    }

    /* ------------------------------------------------------------ 14-17: dashboard */

    private function refundRow(Order $order, string $status, int $minor, $completed, array $o = []): PaymentRefund
    {
        return PaymentRefund::create($o + [
            'internal_reference' => 'REF-'.strtoupper(Str::random(10)), 'payment_attempt_id' => null, 'order_id' => $order->id, 'provider' => 'manual', 'mode' => 'manual',
            'type' => 'partial', 'status' => $status, 'awaiting_approval' => false, 'currency' => 'EGP', 'amount_minor' => $minor, 'reason' => 'test reason',
            'idempotency_key' => Str::random(40), 'requested_at' => now(), 'completed_at' => $status === 'succeeded' ? $completed : null, 'effects_status' => 'not_applicable',
        ]);
    }

    private function dashboardFixture(): array
    {
        $eventA = $this->makeEvent(['name' => 'Alpha']);
        $eventB = $this->makeEvent(['name' => 'Beta']);
        [$a] = $this->paidOrder('legacy', 3, 100, $eventA);   // 300, two partial refunds
        [$b] = $this->paidOrder('legacy', 2, 100, $eventA);   // 200, one full refund
        [$c] = $this->paidOrder('legacy', 1, 100, $eventA);   // 100, refund only pending
        [$d] = $this->paidOrder('legacy', 1, 100, $eventB);   // 100, other event, refunded
        [$e] = $this->paidOrder('legacy', 1, 100, $eventA);   // hidden from statistics
        [$f] = $this->paidOrder('legacy', 1, 100, $eventA);   // refund completed long ago
        $e->forceFill(['exclude_from_statistics' => true])->saveQuietly();
        $this->refundRow($a, 'succeeded', 5000, now()->subDays(2));
        $this->refundRow($a, 'succeeded', 2500, now()->subDay());
        $this->refundRow($b, 'succeeded', 20000, now()->subDays(3), ['type' => 'full']);
        $this->refundRow($c, 'requested', 4000, null);
        $this->refundRow($d, 'succeeded', 10000, now()->subDays(1), ['type' => 'full']);
        $this->refundRow($e, 'succeeded', 9900, now()->subDays(1));
        $this->refundRow($f, 'succeeded', 7700, now()->subDays(90));
        // what a confirmed refund does to the order status (set by the service in real use)
        $b->forceFill(['status' => 'refunded'])->saveQuietly();
        $d->forceFill(['status' => 'refunded'])->saveQuietly();
        $a->forceFill(['status' => 'partially_refunded'])->saveQuietly();

        return compact('eventA', 'eventB', 'a', 'b', 'c', 'd', 'e', 'f');
    }

    public function test_dashboard_refunded_amount_counts_only_confirmed_refunds_by_completion_date_inside_the_scope(): void
    {
        $x = $this->dashboardFixture();
        $start = now()->subDays(29)->startOfDay();
        $end = now()->endOfDay();
        $admin = $this->user(['orders.view']);

        $all = (new DashboardMetrics([], null, $admin, []))->refundStats($start, $end);
        // 50 + 25 (order a) + 200 (b) + 100 (d); pending 40, hidden order e and the 90-day-old refund f are out
        $this->assertSame(375.0, $all['amount']);
        $this->assertSame(3, $all['orders'], 'distinct orders: a has two refunds but counts once');
        $this->assertSame([40.0, 1], [$all['pending_amount'], $all['pending_count']], 'pending requests are separate from refunded money');

        $alpha = (new DashboardMetrics([], $x['eventA'], $admin, []))->refundStats($start, $end);
        $this->assertSame([275.0, 2], [$alpha['amount'], $alpha['orders']]);
        $beta = (new DashboardMetrics([], $x['eventB'], $admin, []))->refundStats($start, $end);
        $this->assertSame([100.0, 1], [$beta['amount'], $beta['orders']]);

        $narrow = (new DashboardMetrics([], null, $admin, []))->refundStats(now()->subDay()->startOfDay(), $end);
        $this->assertSame([125.0, 2], [$narrow['amount'], $narrow['orders']], 'date filter uses the refund completion time');
        $old = (new DashboardMetrics([], null, $admin, []))->refundStats(now()->subDays(100), now()->subDays(60));
        $this->assertSame([77.0, 1], [$old['amount'], $old['orders']]);
        $this->assertSame([0.0, 0], [(new DashboardMetrics([], null, $admin, []))->refundStats(now()->addDay(), now()->addDays(2))['amount'], 0]);
    }

    public function test_net_collected_is_captured_minus_confirmed_refunds_and_a_refund_never_erases_the_sale(): void
    {
        $this->dashboardFixture();
        $start = now()->subDays(29)->startOfDay();
        $end = now()->endOfDay();
        $admin = $this->user(['orders.view']);
        $m = new DashboardMetrics([], null, $admin, []);
        $k = $m->kpis($start, $end);

        // captured: a 300 + b 200 + c 100 + d 100 + f 100 (e is hidden); a, b and d are now (partially) refunded orders but still count as sales
        $this->assertSame(800.0, $k['sales']);
        $this->assertSame(5, $k['paid_orders']);
        $this->assertSame(375.0, $k['refunded']);
        $this->assertSame(425.0, $k['net_collected']);
        $this->assertSame(['paid' => 800.0, 'refunded' => 375.0, 'net_collected' => 425.0], array_intersect_key($m->revenue($start, $end), array_flip(['paid', 'refunded', 'net_collected'])));
        $this->assertSame(8, $k['tickets'], 'ticket sales stay gross');

        // an order merely EDITED to "refunded" by hand (no refund record) is not a captured sale and not refunded money, exactly as before
        [$manual] = $this->paidOrder('legacy', 1, 500);
        $manual->forceFill(['status' => 'refunded'])->saveQuietly();
        $k2 = $m->kpis($start, $end);
        $this->assertSame([800.0, 375.0], [$k2['sales'], $k2['refunded']]);
        $this->assertSame(0, Order::query()->captured()->whereKey($manual->id)->count());
    }

    public function test_dashboard_renders_the_two_clickable_kpi_cards_and_the_link_opens_exactly_the_counted_orders(): void
    {
        $x = $this->dashboardFixture();
        $admin = $this->user(['orders.view', 'payments.refunds.view', 'dashboard.view']);

        $page = $this->actingAs($admin)->get(route('admin.dashboard', ['range' => '30d']));
        $page->assertOk()->assertSee('Total Refunded')->assertSee('Refunded Orders')->assertSee('375 EGP')->assertSee('1 pending request(s)', false);
        $href = route('admin.orders.index', ['refunded' => 1, 'stats' => 1, 'refunded_from' => now()->subDays(29)->toDateString(), 'refunded_to' => now()->toDateString()]);
        $page->assertSee(e($href), false);

        $list = $this->actingAs($admin)->get($href);
        $list->assertOk()->assertSee('Orders with confirmed refunds');
        $orders = $list->viewData('orders');
        $this->assertSame(3, $orders->total(), 'the list holds exactly the orders the card counted');
        $ids = $orders->getCollection()->pluck('id')->all();
        sort($ids);
        $expected = [$x['a']->id, $x['b']->id, $x['d']->id];
        sort($expected);
        $this->assertSame($expected, $ids);
        $this->assertNotContains($x['c']->id, $ids, 'a pending refund does not qualify');
        $this->assertNotContains($x['e']->id, $ids, 'statistics-excluded orders stay out');
        $this->assertNotContains($x['f']->id, $ids, 'refund completed outside the period');
        $a = $orders->getCollection()->firstWhere('id', $x['a']->id);
        $this->assertSame(7500, (int) $a->refunded_minor, 'total confirmed refunded, both refunds');
        $list->assertSee('Partial')->assertSee('Full')->assertSee('test reason')->assertSee('Remaining');

        // event filter, sorting, search and pagination keep working with the refund filter
        $byEvent = $this->actingAs($admin)->get($href.'&refund_event_id='.$x['eventB']->id)->viewData('orders');
        $this->assertSame([$x['d']->id], $byEvent->getCollection()->pluck('id')->all());
        $asc = $this->actingAs($admin)->get($href.'&sort=refunded_amount&dir=asc')->viewData('orders')->getCollection()->pluck('id')->all();
        $this->assertSame([$x['a']->id, $x['d']->id, $x['b']->id], $asc);
        $found = $this->actingAs($admin)->get($href.'&search='.$x['b']->order_number)->viewData('orders');
        $this->assertSame([$x['b']->id], $found->getCollection()->pluck('id')->all());
        $paged = $this->actingAs($admin)->get($href.'&per_page=15&page=2')->viewData('orders');
        $this->assertCount(0, $paged->getCollection());

        // empty state
        $this->actingAs($admin)->get(route('admin.orders.index', ['refunded' => 1, 'refunded_from' => '2001-01-01', 'refunded_to' => '2001-01-31']))->assertOk()->assertSee('No orders found');
        // no refund filter = the normal list is unchanged
        $this->assertGreaterThan(3, $this->actingAs($admin)->get(route('admin.orders.index'))->viewData('orders')->total());
    }

    /* ------------------------------------------------------------ 18: roles and approval */

    public function test_four_eyes_approval_and_role_checks_are_enforced_and_every_actor_is_recorded(): void
    {
        SystemSettings::set('refund_approval_required', true);
        $target = $this->paidOrder('manual');
        $r = $this->request($target, 'full', null, [], $this->approver); // the approver asks...

        try {
            $this->svc->approve($r, $this->approver); // ...and cannot approve their own request
            $this->fail('own approval');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('someone other than the requester', $e->errors()['refund'][0]);
        }
        try {
            $this->confirm($r, $this->approver);
            $this->fail('cannot confirm before approval');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('waiting for approval', $e->errors()['refund'][0]);
        }
        $this->assertSame(['not_checked_in', 'not_checked_in', 'not_checked_in'], $this->statuses($target[0]));

        $this->actingAs($this->staff)->post(route('admin.payments.refunds.approve', $r))->assertForbidden(); // create-only role
        $second = $this->user(['payments.refunds.approve', 'payments.refunds.view', 'orders.view']);
        $this->svc->approve($r, $second);
        $this->svc->markSubmitted($r->fresh(), $this->staff);
        $done = $this->confirm($r->fresh(), $this->staff);

        $this->assertSame([$this->approver->id, $second->id, $this->staff->id, $this->staff->id], [$done->requested_by, $done->approved_by, $done->submitted_by, $done->confirmed_by]);
        $events = collect($done->timeline())->pluck('event')->all();
        foreach (['requested', 'approved', 'submitted_externally', 'confirmed_by_staff', 'effects_applied'] as $e) {
            $this->assertContains($e, $events);
        }
        $this->assertSame('refunded', $target[0]->fresh()->status);
    }

    public function test_confirmation_over_http_needs_evidence_a_real_date_and_the_ticket_resolution_needs_the_approve_permission(): void
    {
        $target = $this->paidOrder('manual', 2, 100);
        $r = $this->request($target);
        $base = ['provider_reference' => 'FW-9', 'note' => 'portal screenshot 12'];

        $this->actingAs($this->staff)->post(route('admin.payments.refunds.confirm', $r), $base)->assertSessionHasErrors('refunded_at');
        $this->actingAs($this->staff)->post(route('admin.payments.refunds.confirm', $r), $base + ['refunded_at' => now()->addDay()->toDateString()])->assertSessionHasErrors('refunded_at');
        $this->assertSame('requested', $r->fresh()->status);
        $this->actingAs($this->staff)->post(route('admin.payments.refunds.confirm', $r), $base + ['refunded_at' => now()->toDateString(), 'external_method' => 'wallet'])->assertSessionHasNoErrors();
        $this->assertSame(['succeeded', 'wallet'], [$r->fresh()->status, $r->fresh()->external_method]);
        $this->actingAs($this->staff)->post(route('admin.payments.refunds.tickets', $r), ['tickets' => [1]])->assertForbidden();

        $this->actingAs($this->staff)->get(route('admin.orders.show', $target[0]))->assertOk()->assertSee('Fully refunded')->assertSee('Confirmed refunded')->assertSee('canceled:');
    }

    /* ------------------------------------------------------------ 19-21: no regression, history, recovery */

    public function test_manual_order_edits_cannot_fake_or_undo_a_refund(): void
    {
        $target = $this->paidOrder('manual', 2, 100);
        $order = $target[0];
        $editor = $this->user(['orders.update', 'orders.view', 'update_status']);

        $this->actingAs($editor)->post(route('admin.orders.status.update', $order), ['status' => 'refunded'])->assertSessionHasErrors('status');
        $this->assertSame('paid', $order->fresh()->status, 'typing "refunded" proves no money was returned');

        $r = $this->request($target, 'partial', '100', ['tickets' => [Ticket::where('order_id', $order->id)->value('id')]]);
        $this->confirm($r);
        $this->assertSame('partially_refunded', $order->fresh()->status);
        $this->actingAs($editor)->post(route('admin.orders.status.update', $order), ['status' => 'paid'])->assertSessionHasErrors('status');
        $this->assertSame('partially_refunded', $order->fresh()->status);
        $this->assertSame(['canceled', 'not_checked_in'], $this->statuses($order), 'no unrelated order action reactivates a refunded ticket');

        // re-running ticket issuance never revives or duplicates anything
        app(TicketIssuanceService::class)->issueIfPaid($order->fresh()->forceFill(['status' => 'paid']));
        $this->assertSame(['canceled', 'not_checked_in'], $this->statuses($order));
        $this->assertSame(2, Ticket::where('order_id', $order->id)->count());
    }

    public function test_a_refund_that_predates_ticket_integration_is_never_turned_into_cancellations_on_its_own(): void
    {
        $target = $this->paidOrder('manual', 3, 100);
        $order = $target[0];
        $r = $this->request($target, 'partial', '100', ['no_tickets' => true]);
        $r->forceFill(['effects_status' => null])->save(); // how a refund created before this feature looks

        $done = $this->confirm($r->fresh());
        $this->assertSame('needs_review', $done->effects_status);
        $this->assertSame(['not_checked_in', 'not_checked_in', 'not_checked_in'], $this->statuses($order), 'no unsafe backfill');
        $this->assertSame('partially_refunded', $order->fresh()->status, 'the money is a fact, the order follows it');

        $t = Ticket::where('order_id', $order->id)->orderBy('id')->get();
        try {
            $this->svc->resolveTickets($done, $this->staff, [$t[0]->id]); // create-only role may not decide
            $this->fail('permission');
        } catch (ValidationException) {
        }
        $this->svc->resolveTickets($done->fresh(), $this->approver, [$t[0]->id]);
        $this->assertSame(['canceled', 'not_checked_in', 'not_checked_in'], $this->statuses($order));
        try {
            $this->svc->resolveTickets($done->fresh(), $this->approver, [$t[1]->id]); // already applied: no second run
            $this->fail('idempotent');
        } catch (ValidationException) {
        }
        $this->assertSame(['canceled', 'not_checked_in', 'not_checked_in'], $this->statuses($order));
    }

    public function test_an_order_with_two_paid_payments_is_not_cancelled_automatically(): void
    {
        $target = $this->paidOrder('manual', 2, 100);
        [$order, $first] = $target;
        PaymentAttempt::create(['order_id' => $order->id, 'provider' => 'fawaterak', 'method_code' => 'fawaterak_card', 'amount' => 200, 'currency' => 'EGP', 'status' => 'paid', 'transaction_id' => '777001',
            'reference' => $order->order_number.'-dup', 'integration_account_id' => $first->integration_account_id, 'internal_reference' => PaymentAttempt::newInternalReference(), 'paid_at' => now()]);

        $r = $this->svc->request($first, $this->staff, 'full', null, 'duplicate payment, giving it back', 'dup-1');
        $this->assertSame('needs_review', $r->effects_status, 'ambiguous relation: no automatic cancellation');
        $this->confirm($r);
        $this->assertSame(['not_checked_in', 'not_checked_in'], $this->statuses($order));
    }

    public function test_a_failure_while_applying_the_confirmation_leaves_everything_unchanged_and_is_retryable(): void
    {
        $target = $this->paidOrder('manual', 2, 100);
        $order = $target[0];
        $r = $this->request($target);

        Ticket::saving(function (Ticket $t) {
            if ($t->status === 'canceled') {
                throw new \RuntimeException('disk full');
            }
        });
        try {
            $this->confirm($r);
            $this->fail('the confirmation must report the failure');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('nothing was changed', $e->errors()['refund'][0]);
        }
        $fresh = $r->fresh();
        $this->assertSame(['requested', 'pending'], [$fresh->status, $fresh->effects_status]);
        $this->assertSame('paid', $order->fresh()->status);
        $this->assertSame(['not_checked_in', 'not_checked_in'], $this->statuses($order));
        $this->assertSame(0, $this->svc->balance($target[1])['refunded'], 'no money is counted as refunded');
        $this->assertSame(0, ScanLog::where('action', 'refund_canceled')->count());

        Ticket::flushEventListeners();
        Ticket::clearBootedModels();
        $this->confirm($r->fresh());
        $this->assertSame('refunded', $order->fresh()->status);
        $this->assertSame(['canceled', 'canceled'], $this->statuses($order));
    }

    public function test_payment_report_keeps_legacy_refunds_in_the_totals(): void
    {
        $target = $this->paidOrder('manual', 2, 100);
        $this->confirm($this->request($target, 'partial', '50', ['no_tickets' => true]));
        $legacy = $this->paidOrder('legacy', 2, 100);
        $this->confirm($this->request($legacy, 'partial', '30', ['no_tickets' => true]));

        $admin = $this->user(['payments.transactions.view', 'orders.view']);
        $report = app(\App\Services\Payments\PaymentReport::class)->build($admin, now()->startOfMonth(), now()->endOfDay(), false);
        $this->assertSame(8000, $report['totals']['refunded'], 'legacy refunds are part of the totals');
        $this->assertSame(8000, collect($report['by_provider'])->sum('refunded'));
    }

    /* ------------------------------------------------------------ end-to-end lifecycle over HTTP */

    public function test_full_lifecycle_of_a_legacy_fawaterak_order_over_http_with_the_audit_trail(): void
    {
        SystemSettings::set('refund_approval_required', true);
        [$order] = $this->paidOrder('legacy', 2, 250);
        $ticket = Ticket::where('order_id', $order->id)->orderBy('id')->first();
        $requester = $this->user(['payments.refunds.create', 'payments.refunds.view', 'orders.view', 'dashboard.view']);
        $approver = $this->user(['payments.refunds.approve', 'payments.refunds.view', 'orders.view']);
        $scanner = $this->scanner();

        // 1. request: amount, reason, method, reference, date are recorded; nothing else changes
        $this->actingAs($requester)->post(route('admin.payments.refunds.store-order', $order), [
            'type' => 'full', 'reason' => 'event moved to another city', 'idempotency_key' => 'life-1', 'confirm' => 1,
            'external_method' => 'provider_portal', 'provider_reference' => 'FAW-INV-5521', 'refunded_at' => now()->toDateString(), 'internal_notes' => 'asked by phone',
        ])->assertRedirect();
        $r = PaymentRefund::firstOrFail();
        $this->assertSame([50000, 50000, 'requested', true, 'FAW-INV-5521', null], [$r->amount_minor, $r->captured_minor, $r->status, $r->awaiting_approval, $r->provider_reference, $r->payment_attempt_id]);
        $this->assertSame('event moved to another city', $r->reason);
        $this->assertSame('paid', $order->fresh()->status);
        $this->actingAs($scanner)->postJson(route('admin.tickets.scanner.lookup'), ['code' => $ticket->ticket_number])->assertOk();

        // 2. the requester cannot approve or confirm; a second person approves
        $this->actingAs($requester)->post(route('admin.payments.refunds.approve', $r))->assertForbidden();
        $this->actingAs($requester)->post(route('admin.payments.refunds.confirm', $r), ['provider_reference' => 'X-1', 'note' => 'portal screenshot', 'refunded_at' => now()->toDateString()])->assertSessionHasErrors('refund');
        $this->assertSame('requested', $r->fresh()->status);
        $this->actingAs($approver)->post(route('admin.payments.refunds.approve', $r), ['note' => 'verified with the organiser'])->assertSessionHasNoErrors();
        $this->assertSame($approver->id, $r->fresh()->approved_by);
        $this->assertSame('paid', $order->fresh()->status, 'approval changes nothing');

        // 3. external submission, then explicit confirmation with evidence
        $this->actingAs($requester)->post(route('admin.payments.refunds.submitted', $r))->assertSessionHasNoErrors();
        $this->assertSame('processing', $r->fresh()->status);
        $this->assertSame('paid', $order->fresh()->status, 'submitting externally is not success');
        $this->actingAs($approver)->post(route('admin.payments.refunds.confirm', $r), ['provider_reference' => 'FAW-REF-77', 'note' => 'portal screenshot 4411', 'refunded_at' => now()->toDateString(), 'external_method' => 'provider_portal'])->assertSessionHasNoErrors();

        $r = $r->fresh();
        $this->assertSame(['succeeded', 'FAW-REF-77', 'applied'], [$r->status, $r->provider_reference, $r->effects_status]);
        $this->assertSame([$requester->id, $approver->id, $requester->id, $approver->id], [$r->requested_by, $r->approved_by, $r->submitted_by, $r->confirmed_by]);
        $this->assertSame('refunded', $order->fresh()->status);
        $this->assertSame(['canceled', 'canceled'], $this->statuses($order));
        $this->assertSame('500.00', number_format((float) $order->fresh()->total_amount, 2, '.', ''), 'original amount untouched');

        // audit log: every transition has an entry naming who did it
        $log = \Spatie\Activitylog\Models\Activity::where('subject_type', PaymentRefund::class)->where('subject_id', $r->id)->orderBy('id')->get();
        $names = $log->map(fn ($a) => $a->description)->all();
        foreach (['Refund requested', 'Refund approved', 'Refund submitted externally', 'Refund confirmed by staff', 'Refund effects applied'] as $d) {
            $this->assertContains($d, $names);
        }
        $this->assertSame($approver->id, $log->firstWhere('description', 'Refund approved')->causer_id);
        $this->assertSame($requester->id, $log->firstWhere('description', 'Refund requested')->causer_id);
        $this->assertTrue(\Spatie\Activitylog\Models\Activity::where('subject_type', Order::class)->where('subject_id', $order->id)->where('description', 'Order status changed')->exists());

        // 4. scanner rejects with the reason, 5. dashboard shows the money, the link lists the order
        $this->actingAs($scanner)->postJson(route('admin.tickets.scanner.lookup'), ['code' => $ticket->ticket_number])->assertStatus(409)->assertJsonFragment(['code' => 'refunded']);
        $m = new DashboardMetrics([], null, $requester, []);
        $stats = $m->refundStats(now()->startOfDay(), now()->endOfDay());
        $this->assertSame([500.0, 1], [$stats['amount'], $stats['orders']]);
        $this->assertSame(0.0, $m->kpis(now()->startOfDay(), now()->endOfDay())['net_collected']);
        $list = $this->actingAs($requester)->get(route('admin.orders.index', ['refunded' => 1, 'stats' => 1, 'refunded_from' => now()->toDateString(), 'refunded_to' => now()->toDateString()]));
        $this->assertSame([$order->id], $list->viewData('orders')->getCollection()->pluck('id')->all());
    }

    public function test_multiple_payment_attempts_partial_refund_of_one_attempt_cancels_only_selected_tickets(): void
    {
        [$order, $first] = $this->paidOrder('manual', 2, 100);
        PaymentAttempt::create(['order_id' => $order->id, 'provider' => 'fawaterak', 'method_code' => 'fawaterak_card', 'amount' => 200, 'currency' => 'EGP', 'status' => 'paid', 'transaction_id' => '888002',
            'reference' => $order->order_number.'-dup', 'integration_account_id' => $first->integration_account_id, 'internal_reference' => PaymentAttempt::newInternalReference(), 'paid_at' => now()]);
        $t = Ticket::where('order_id', $order->id)->orderBy('id')->get();

        $r = $this->svc->request($first, $this->staff, 'partial', null, 'one attendee cancelled', 'ma-1', ['tickets' => [$t[0]->id]]);
        $this->assertSame('pending', $r->effects_status, 'an explicit selection is not ambiguous');
        $this->confirm($r);
        $this->assertSame(['canceled', 'not_checked_in'], $this->statuses($order));
        $this->assertSame('partially_refunded', $order->fresh()->status);
        $this->assertSame(10000, $this->svc->balance($first)['refunded']);
    }

    public function test_an_unknown_api_outcome_cancels_nothing_until_a_person_confirms_it(): void
    {
        $this->makeMethod('paymob', 'paymob_card', ['api_key' => 'L', 'iframe_id' => '1', 'integration_id' => '1']);
        $acc = $this->account('paymob', 'PMX');
        $acc->forceFill(['refund_enabled_at' => now()])->save();
        $order = $this->makePendingOrder(User::factory()->create(), null, 2, 100, 'paymob_card');
        $order->forceFill(['integration_account_id' => $acc->id])->saveQuietly();
        $a = PaymentAttempt::create(['order_id' => $order->id, 'provider' => 'paymob', 'method_code' => 'paymob_card', 'amount' => 200, 'currency' => 'EGP', 'status' => 'paid', 'transaction_id' => '445566',
            'reference' => $order->order_number.'-p', 'integration_account_id' => $acc->id, 'internal_reference' => PaymentAttempt::newInternalReference(), 'paid_at' => now(), 'settled_at' => now()]);
        $order->forceFill(['status' => 'paid', 'paid_at' => now()])->saveQuietly();
        app(TicketIssuanceService::class)->issueIfPaid($order->fresh());
        $this->paymobPays($a, 'KEY-PMX', '445566');

        $this->paymobRefundMode = 'timeout';
        $r = $this->svc->request($a->fresh(), $this->staff, 'full', null, 'api refund that times out', 'unk-1');
        $this->assertSame('unknown', $r->status);
        $this->assertSame('paid', $order->fresh()->status);
        $this->assertSame(['not_checked_in', 'not_checked_in'], $this->statuses($order));
        $this->assertSame(0, (new DashboardMetrics([], null, $this->staff, []))->refundStats(now()->startOfDay(), now()->endOfDay())['orders'], 'unknown is not money returned');

        $this->confirm($r->fresh()); // verified in the provider dashboard by a person
        $this->assertSame('refunded', $order->fresh()->status);
        $this->assertSame(['canceled', 'canceled'], $this->statuses($order));
    }

    public function test_refund_figures_and_the_refund_order_list_need_the_refund_permission(): void
    {
        $this->dashboardFixture();
        $plain = $this->user(['orders.view', 'dashboard.view']);

        $this->actingAs($plain)->get(route('admin.dashboard', ['range' => '30d']))->assertOk()->assertDontSee('Refunded Orders')->assertDontSee('Total Refunded');
        $this->actingAs($plain)->get(route('admin.orders.index', ['refunded' => 1]))->assertForbidden();
        $this->actingAs($plain)->get(route('admin.orders.index'))->assertOk();
    }
}
