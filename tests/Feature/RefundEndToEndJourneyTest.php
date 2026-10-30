<?php

namespace Tests\Feature;

use App\Http\Middleware\EnsureAdminPanelAccess;
use App\Mail\RefundCustomerMail;
use App\Models\Order;
use App\Models\PaymentAttempt;
use App\Models\PaymentMethod;
use App\Models\PaymentRefund;
use App\Models\ScanLog;
use App\Models\Ticket;
use App\Models\User;
use App\Services\Admin\DashboardMetrics;
use App\Services\Payments\PaymentService;
use App\Services\Payments\Refunds\RefundService;
use App\Services\Payments\Refunds\RefundTracking;
use App\Support\SystemSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\Support\BuildsMerchantAccounts;
use Tests\Support\BuildsOrders;
use Tests\TestCase;

/** Final end-to-end QA of the refund journey (mocked Fawaterak, no real money, no real e-mail). Run on MariaDB for the release check. */
class RefundEndToEndJourneyTest extends TestCase
{
    use BuildsMerchantAccounts, BuildsOrders, RefreshDatabase;

    private RefundService $svc;
    private User $agentA;
    private User $agentB;
    private User $super;
    private User $outsider;
    private User $scanner;
    private const PERMS = ['payments.refunds.view', 'payments.refunds.create', 'payments.transactions.view'];

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(EnsureAdminPanelAccess::class);
        $this->fakeProviders();
        Mail::fake();
        config(['payments.fawaterak.verified' => true]);
        SystemSettings::set('order_assignment_enabled', true);
        SystemSettings::set('refund_approval_required', false);
        $this->svc = app(RefundService::class);
        $this->makeMethod('fawaterak', 'fawaterak_card', ['api_key' => 'L', 'provider_key' => 'FAWATERAK.1']);
        $this->agentA = $this->staff('Order Agent', self::PERMS);
        $this->agentB = $this->staff('Order Agent', self::PERMS);
        Role::findOrCreate('super_admin', 'web');
        $this->super = $this->staff('super_admin', ['orders.view', 'dashboard.view', 'payments.refunds.approve', ...self::PERMS], ['can_receive_orders' => false]);
        $this->outsider = $this->staff(null, ['orders.view', 'dashboard.view', ...self::PERMS], ['can_receive_orders' => false]);
        $this->scanner = $this->staff(null, ['scanner.access', 'tickets.view'], ['can_receive_orders' => false]);
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

    /** A really paid order: created, paid through the (mocked) Fawaterak webhook + inquiry, tickets issued by the normal flow. */
    private function paid(User $agent, int $people = 4, float $unit = 100, string $vendor = 'VENDOR-A'): PaymentAttempt
    {
        $acc = $this->account('fawaterak', 'FW'.Str::random(5), [], ['vendor_key' => $vendor]);
        $acc->forceFill(['refund_enabled_at' => now()])->save();
        $order = $this->makePendingOrder(User::factory()->create(), $this->makeEvent(['name' => 'Summer Fest']), $people, $unit, 'fawaterak_card');
        $order->forceFill(['integration_account_id' => $acc->id, 'assigned_user_id' => $agent->id, 'assigned_at' => now()])->saveQuietly();
        app(PaymentService::class)->start($order->fresh(), PaymentMethod::where('code', 'fawaterak_card')->first());
        $a = $order->paymentAttempts()->firstOrFail();
        $this->fawInvoices[$a->transaction_id]['paid'] = true;
        $p = ['invoice_id' => $a->transaction_id, 'invoice_key' => 'IK1', 'payment_method' => 'Card', 'invoice_status' => 'paid'];
        $this->postJson(route('front.fawaterak.webhook'), $p + ['hashKey' => hash_hmac('sha256', 'InvoiceId='.$a->transaction_id.'&InvoiceKey=IK1&PaymentMethod=Card', $vendor)])->assertOk();
        $this->assertSame('paid', $order->fresh()->status);

        return $a->fresh();
    }

    private function formData(string $type, array $extra = []): array
    {
        return $extra + ['type' => $type, 'reason' => 'customer cannot attend any more', 'idempotency_key' => Str::uuid()->toString(), 'confirm' => 1];
    }

    private function hook(PaymentAttempt $a, string $amount, string $currency = 'EGP', string $status = 'approved')
    {
        return $this->postJson(route('front.fawaterak.webhook'), ['transactionId' => $a->transaction_id, 'amount' => $amount, 'currency' => $currency, 'status' => $status, 'reason' => 'x', 'approvedAt' => '2026-10-30 12:00:00']);
    }

    private function confirmUrl(User $by, PaymentRefund $r, string $ref = 'FAW-REF-1')
    {
        return $this->actingAs($by)->post(route('admin.payments.refunds.confirm', $r), ['provider_reference' => $ref, 'note' => 'seen as refunded in the provider dashboard', 'refunded_at' => now()->toDateString(), 'external_method' => 'provider_portal']);
    }

    private function statuses(Order $o): array
    {
        return Ticket::where('order_id', $o->id)->orderBy('id')->pluck('status')->all();
    }

    private function mails(): int
    {
        return Mail::sent(RefundCustomerMail::class)->count();
    }

    private function dash(User $u): array
    {
        $m = new DashboardMetrics([], null, $u, []);
        $k = $m->kpis(now()->subDays(2)->startOfDay(), now()->endOfDay());

        return ['sales' => $k['sales'], 'refunded' => $k['refunded'], 'orders' => $k['refunded_orders'], 'net' => $k['net_collected'], 'paid_orders' => $k['paid_orders'], 'tickets' => $k['tickets']];
    }

    /* ------------------------------------------------------------ the journey: full and partial */

    public function test_full_journey_paid_order_to_dashboard(): void
    {
        $a = $this->paid($this->agentA, 4, 100);
        $order = $a->order;
        $tickets = Ticket::where('order_id', $order->id)->orderBy('id')->get();
        $before = $this->dash($this->agentA);
        $this->assertSame([400.0, 0.0, 0, 400.0], [$before['sales'], $before['refunded'], $before['orders'], $before['net']]);

        // create refund (agent, no second approval): immediately waiting for the external refund
        $this->actingAs($this->agentA)->post(route('admin.payments.refunds.store', $a), $this->formData('full'))->assertSessionHasNoErrors();
        $r = PaymentRefund::firstOrFail();
        $this->assertSame(['processing', false, 'waiting_external'], [$r->status, (bool) $r->awaiting_approval, RefundTracking::state($r)]);
        $this->assertSame([], Mail::sent(RefundCustomerMail::class)->all(), 'no e-mail for a request');

        // open Fawaterak: audit only
        $this->actingAs($this->agentA)->postJson(route('admin.payments.refunds.opened', $r))->assertOk();
        $this->assertSame('processing', $r->fresh()->status);
        $this->assertSame(0, $this->mails());

        // unsigned refund webhook: a hint, needs verification, no effects
        $this->hook($a, '400')->assertOk()->assertJson(['authenticated' => false]);
        $this->assertSame('notification_received', RefundTracking::state($r->fresh()));
        $this->assertSame(['processing', 'paid'], [$r->fresh()->status, $order->fresh()->status]);
        $this->assertSame(['not_checked_in', 'not_checked_in', 'not_checked_in', 'not_checked_in'], $this->statuses($order));
        $this->assertSame(0, $this->mails(), 'no e-mail from an unverified webhook');
        $this->assertSame($before, $this->dash($this->agentA), 'the dashboard does not move before confirmation');

        // an unauthorized employee (no access to the order) cannot confirm
        $this->confirmUrl($this->outsider, $r)->assertNotFound();
        $this->confirmUrl($this->agentB, $r)->assertNotFound();
        $this->assertSame('processing', $r->fresh()->status);

        // authorized confirmation
        $this->confirmUrl($this->agentA, $r)->assertSessionHasNoErrors();
        $done = $r->fresh();
        $this->assertSame(['succeeded', 'applied', 'FAW-REF-1'], [$done->status, $done->effects_status, $done->provider_reference]);
        $this->assertSame('refunded', $order->fresh()->status);
        $this->assertSame(['canceled', 'canceled', 'canceled', 'canceled'], $this->statuses($order));
        $this->assertSame('400.00', number_format((float) $order->fresh()->total_amount, 2, '.', ''), 'the original order amount is untouched');

        // scanner rejects with the reason
        foreach ($tickets as $t) {
            $this->actingAs($this->scanner)->postJson(route('admin.tickets.scanner.lookup'), ['code' => $t->ticket_number])->assertStatus(409)->assertJsonFragment(['code' => 'refunded']);
        }

        // exactly one customer e-mail, with the right customer/amount/reference/event
        $this->assertSame(1, $this->mails());
        Mail::assertSent(RefundCustomerMail::class, function ($m) use ($order, $done) {
            $html = $m->render();

            return $m->hasTo($order->customer->email) && str_contains($html, '400.00 EGP') && str_contains($html, 'Full refund') && str_contains($html, 'Summer Fest')
                && str_contains($html, $done->internal_reference) && ! preg_match('/fawater|paymob|FAW-REF/i', $html);
        });

        // dashboard: gross unchanged, refunded money, distinct order, net reduced; list shows it
        $after = $this->dash($this->agentA);
        $this->assertSame([400.0, 400.0, 1, 0.0, 1, 4], [$after['sales'], $after['refunded'], $after['orders'], $after['net'], $after['paid_orders'], $after['tickets']]);
        $this->assertSame($after['sales'], $before['sales'], 'gross sales are unchanged by a refund');
        $this->assertSame([400.0, 1], [$this->dash($this->super)['refunded'], $this->dash($this->super)['orders']]);
        $this->assertSame([0.0, 0], [$this->dash($this->agentB)['refunded'], $this->dash($this->agentB)['orders']]);

        // repeats change nothing
        $this->confirmUrl($this->agentA, $r)->assertSessionHasErrors('refund');
        $this->hook($a, '400')->assertOk();
        $this->assertSame([1, 4], [$this->mails(), ScanLog::where('action', 'refund_canceled')->count()]);
        $this->assertSame($after, $this->dash($this->agentA));
        $this->actingAs($this->agentA)->getJson(route('admin.payments.refunds.tracking', $r))->assertOk()->assertJson(['completed' => true, 'state' => 'completed']);
    }

    public function test_partial_journey_only_selected_tickets_are_canceled_and_the_rest_keep_working(): void
    {
        $a = $this->paid($this->agentA, 4, 100);
        $order = $a->order;
        $t = Ticket::where('order_id', $order->id)->orderBy('id')->get();

        $this->actingAs($this->agentA)->post(route('admin.payments.refunds.store', $a), $this->formData('partial', ['tickets' => [$t[0]->id, $t[1]->id]]))->assertSessionHasNoErrors();
        $r = PaymentRefund::firstOrFail();
        $this->assertSame(20000, $r->amount_minor, 'defaults to the value of the selected tickets');
        $this->hook($a, '200')->assertOk();
        $this->assertSame(0, $this->mails());
        $this->confirmUrl($this->agentA, $r)->assertSessionHasNoErrors();

        $this->assertSame('partially_refunded', $order->fresh()->status);
        $this->assertSame(['canceled', 'canceled', 'not_checked_in', 'not_checked_in'], $this->statuses($order));
        $this->actingAs($this->scanner)->postJson(route('admin.tickets.scanner.lookup'), ['code' => $t[0]->ticket_number])->assertStatus(409);
        $this->actingAs($this->scanner)->postJson(route('admin.tickets.scanner.lookup'), ['code' => $t[2]->ticket_number])->assertOk()->assertJson(['ok' => true]);
        $this->actingAs($this->scanner)->post(route('admin.tickets.scanner.status', $t[2]), ['status' => 'checked_in'])->assertRedirect();
        $this->assertSame('checked_in', $t[2]->fresh()->status, 'the remaining tickets still work');
        $this->assertSame(1, $this->mails());
        Mail::assertSent(RefundCustomerMail::class, fn ($m) => str_contains($m->render(), 'Partial refund') && str_contains($m->render(), '200.00 EGP') && str_contains($m->render(), 'remain valid'));
        $d = $this->dash($this->agentA);
        $this->assertSame([400.0, 200.0, 1, 200.0], [$d['sales'], $d['refunded'], $d['orders'], $d['net']]);
    }

    /* ------------------------------------------------------------ cumulative partial refunds */

    public function test_multiple_partial_refunds_can_never_exceed_the_refundable_balance(): void
    {
        $a = $this->paid($this->agentA, 4, 100);
        $order = $a->order;
        $t = Ticket::where('order_id', $order->id)->orderBy('id')->get();
        $go = fn (array $tickets, ?string $amount = null) => $this->svc->request($a, $this->agentA, 'partial', $amount, 'cumulative partial refund', Str::uuid()->toString(), ['tickets' => $tickets]);
        $ok = fn (PaymentRefund $r) => $this->svc->confirmSucceeded($r, $this->agentA, 'FAW-'.$r->id, 'verified in the provider dashboard', now()->toDateString());

        $r1 = $go([$t[0]->id]);
        // a second request while the first is still open is refused (one in flight per payment)
        try {
            $go([$t[1]->id]);
            $this->fail('one refund in flight at a time');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('still being processed', $e->errors()['refund'][0]);
        }
        $ok($r1);
        $r2 = $go([$t[1]->id, $t[2]->id]);
        $ok($r2);
        $this->assertSame(30000, $this->svc->balance($a)['refunded']);
        $this->assertSame(10000, $this->svc->balance($a)['available']);

        // more than what is left, more than the ticket is worth, a full refund after partials, an already refunded ticket: all refused
        foreach ([[[$t[3]->id], '150'], [[$t[3]->id], '100.01'], [[$t[0]->id], null], [[$t[1]->id], '10']] as [$tix, $amt]) {
            try {
                $go($tix, $amt);
                $this->fail('must be refused');
            } catch (ValidationException) {
            }
        }
        try {
            $this->svc->request($a, $this->agentA, 'full', null, 'full after partials', 'full-x');
            $this->fail('full after partials');
        } catch (ValidationException) {
        }
        $this->assertSame(2, PaymentRefund::count());

        $r3 = $go([$t[3]->id]);
        $this->assertSame(10000, $r3->amount_minor);
        $ok($r3);
        $this->assertSame(['refunded', 40000, 0], [$order->fresh()->status, $this->svc->balance($a)['refunded'], $this->svc->balance($a)['available']]);
        $this->assertSame(['canceled', 'canceled', 'canceled', 'canceled'], $this->statuses($order));
        $this->assertSame(3, $this->mails(), 'one e-mail per completed refund, none more');
        $d = $this->dash($this->agentA);
        $this->assertSame([400.0, 400.0, 1, 0.0], [$d['sales'], $d['refunded'], $d['orders'], $d['net']], 'three refunds on one order count as ONE refunded order');
        try {
            $go([$t[3]->id], '1');
            $this->fail('nothing left');
        } catch (ValidationException) {
        }
    }

    /* ------------------------------------------------------------ approval ON and existing pending refunds */

    public function test_second_person_approval_still_works_and_pending_requests_stay_safe(): void
    {
        SystemSettings::set('refund_approval_required', true);
        $a = $this->paid($this->agentA, 2, 100);
        $this->actingAs($this->agentA)->post(route('admin.payments.refunds.store', $a), $this->formData('full'))->assertSessionHasNoErrors();
        $r = PaymentRefund::firstOrFail();
        $this->assertSame(['requested', true, 'awaiting_approval'], [$r->status, (bool) $r->awaiting_approval, RefundTracking::state($r)]);
        $this->hook($a, '200')->assertOk();
        $this->assertSame(0, Mail::sent(RefundCustomerMail::class)->count());
        $this->assertSame('needs_review', \App\Models\WebhookEvent::where('event_type', 'refund')->first()->processing_status, 'a notification before approval is not matched');
        $this->confirmUrl($this->agentA, $r)->assertSessionHasErrors('refund');                 // cannot confirm while waiting
        $this->actingAs($this->agentA)->post(route('admin.payments.refunds.approve', $r))->assertForbidden();

        SystemSettings::set('refund_approval_required', false);                                  // policy off: nothing is approved silently
        $this->assertSame(['requested', true], [$r->fresh()->status, (bool) $r->fresh()->awaiting_approval]);
        $this->actingAs($this->agentA)->post(route('admin.payments.refunds.release', $r), ['note' => 'policy is off, releasing my request'])->assertSessionHasNoErrors();
        $this->assertSame('processing', $r->fresh()->status);
        $this->confirmUrl($this->agentA, $r)->assertSessionHasNoErrors();
        $this->assertSame(1, Mail::sent(RefundCustomerMail::class)->count());
        $this->assertSame('refunded', $a->order->fresh()->status);

        SystemSettings::set('refund_approval_required', true);                                   // approval ON with a second user
        $b = $this->paid($this->agentA, 2, 100);
        $req = $this->svc->request($b, $this->agentA, 'full', null, 'needs a second person', 'k-appr');
        $this->svc->approve($req, $this->super);
        $this->svc->markSubmitted($req->fresh(), $this->agentA);
        $this->svc->confirmSucceeded($req->fresh(), $this->agentA, 'FAW-2', 'verified in the provider dashboard', now()->toDateString());
        $this->assertSame('refunded', $b->order->fresh()->status);
        $this->assertSame(2, Mail::sent(RefundCustomerMail::class)->count());
    }

    /* ------------------------------------------------------------ financial integrity and merchant matching */

    public function test_financial_integrity_exact_matching_and_net_revenue(): void
    {
        $a = $this->paid($this->agentA, 2, 100, 'VENDOR-A');
        $b = $this->paid($this->agentB, 2, 150, 'VENDOR-B');
        $legacyOrder = $this->makePendingOrder(User::factory()->create(), $this->makeEvent(['name' => 'Legacy Night']), 2, 50, 'fawaterak_card');
        $legacyOrder->forceFill(['status' => 'paid', 'paid_at' => now(), 'assigned_user_id' => $this->agentA->id])->saveQuietly();
        app(\App\Services\TicketIssuanceService::class)->issueIfPaid($legacyOrder->fresh());
        $attemptsBefore = PaymentAttempt::count();

        $rA = $this->svc->request($a, $this->agentA, 'partial', '120', 'partial money-only refund', 'fin-1', ['no_tickets' => true]);
        $rL = $this->svc->requestForOrder($legacyOrder->fresh(), $this->agentA, 'full', null, 'legacy order refund', 'fin-2', ['original_reference' => 'INV-LEGACY-1']);
        $this->assertSame($attemptsBefore, PaymentAttempt::count(), 'no payment attempt is fabricated for a legacy order');

        // exact matching: the other merchant account's transaction, wrong amount, wrong currency
        $this->hook($b, '120')->assertOk();                                       // B has no refund request: A's request is untouched
        $this->hook($a, '119.99')->assertOk();
        $this->hook($a, '120', 'USD')->assertOk();
        $this->assertSame(0, collect($rA->fresh()->timeline())->where('event', 'provider_notification_received')->count());
        $this->hook($a, '120')->assertOk();
        $this->assertSame(1, collect($rA->fresh()->timeline())->where('event', 'provider_notification_received')->count());
        $this->postJson(route('front.fawaterak.webhook'), ['transactionId' => 'INV-LEGACY-1', 'amount' => '100', 'currency' => 'EGP', 'status' => 'approved', 'approvedAt' => '2026-10-30 12:00:00'])->assertOk();
        $this->assertSame('notification_received', RefundTracking::state($rL->fresh()));
        for ($i = 0; $i < 3; $i++) {
            $this->hook($a, '120');                                                // retries add no accounting
        }

        $this->svc->confirmSucceeded($rA->fresh(), $this->agentA, 'FAW-A', 'verified in the provider dashboard', now()->toDateString());
        $this->svc->confirmSucceeded($rL->fresh(), $this->agentA, 'FAW-L', 'verified in the provider dashboard', now()->toDateString());

        $this->assertSame(['partially_refunded', 'refunded', 'paid'], [$a->order->fresh()->status, $legacyOrder->fresh()->status, $b->order->fresh()->status]);
        $this->assertSame(['not_checked_in', 'not_checked_in'], $this->statuses($a->order), 'a money-only refund leaves tickets valid');
        $this->assertSame(['canceled', 'canceled'], $this->statuses($legacyOrder));
        $this->assertSame([12000, 10000], [$this->svc->balance($a)['refunded'], PaymentRefund::where('order_id', $legacyOrder->id)->sum('amount_minor') + 0]);

        // gross sales (captured payments) include refunded orders; refunds are subtracted separately; distinct orders
        $A = $this->dash($this->agentA);
        $this->assertSame([300.0, 220.0, 2, 80.0], [$A['sales'], $A['refunded'], $A['orders'], $A['net']]);   // 200 + 100 captured, 120 + 100 refunded
        $S = $this->dash($this->super);
        $this->assertSame([600.0, 220.0, 2, 380.0], [$S['sales'], $S['refunded'], $S['orders'], $S['net']]);   // + agent B's untouched 300
        $B = $this->dash($this->agentB);
        $this->assertSame([300.0, 0.0, 0, 300.0], [$B['sales'], $B['refunded'], $B['orders'], $B['net']]);
        $this->assertSame(1, Mail::sent(RefundCustomerMail::class)->filter(fn ($m) => $m->hasTo($legacyOrder->customer->email))->count());
        $this->assertSame(2, $this->mails());
    }

    /* ------------------------------------------------------------ agent isolation matrix */

    public function test_agent_isolation_matrix_across_urls_polling_lists_search_and_reassignment(): void
    {
        $a1 = $this->paid($this->agentA, 2, 100);
        $a2 = $this->paid($this->agentA, 1, 200);                 // the agent with additional assigned orders
        $b1 = $this->paid($this->agentB, 3, 100);
        $refunds = [];
        foreach ([$a1, $a2, $b1] as $att) {
            $refunds[$att->id] = $this->svc->request($att, $att->order->assignedUser, 'full', null, 'isolation check refund', 'iso-'.$att->id);
        }
        $url = fn (string $r, $refund) => route('admin.payments.refunds.'.$r, $refund);

        // Agent A: own two, never B's - show, tracking, list, search, order page, transaction page
        foreach ([$a1, $a2] as $att) {
            $this->actingAs($this->agentA)->get($url('show', $refunds[$att->id]))->assertOk();
            $this->actingAs($this->agentA)->getJson($url('tracking', $refunds[$att->id]))->assertOk();
        }
        $this->actingAs($this->agentA)->get($url('show', $refunds[$b1->id]))->assertNotFound();
        $poll = $this->actingAs($this->agentA)->getJson($url('tracking', $refunds[$b1->id]));
        $poll->assertNotFound();
        $this->assertStringNotContainsString($b1->transaction_id, $poll->getContent());
        $list = $this->actingAs($this->agentA)->get(route('admin.payments.refunds'))->getContent();
        $this->assertStringContainsString($refunds[$a2->id]->internal_reference, $list);
        $this->assertStringNotContainsString($refunds[$b1->id]->internal_reference, $list);
        $this->assertStringNotContainsString($b1->order->order_number, $list);
        $this->assertCount(0, $this->actingAs($this->agentA)->get(route('admin.orders.index', ['search' => $b1->order->order_number]))->viewData('orders')->getCollection());
        $this->actingAs($this->agentA)->get(route('admin.payments.transactions.show', $b1))->assertNotFound();
        $this->actingAs($this->agentA)->get(route('admin.orders.show', $b1->order))->assertNotFound();
        $this->assertSame([400.0, 0.0], [$this->dash($this->agentA)['sales'], $this->dash($this->agentA)['refunded']]);

        // the unauthorized employee (no assignment access): nothing at all
        $this->actingAs($this->outsider)->get($url('show', $refunds[$a1->id]))->assertNotFound();
        $this->actingAs($this->outsider)->getJson($url('tracking', $refunds[$a1->id]))->assertNotFound();
        $this->assertSame([0.0, 0.0], [$this->dash($this->outsider)['sales'], $this->dash($this->outsider)['refunded']]);

        // super admin sees everything
        foreach ($refunds as $r) {
            $this->actingAs($this->super)->getJson($url('tracking', $r))->assertOk();
        }

        // multi-assignee: B is added to A's second order and sees it (and only it among A's)
        app(\App\Orders\OrderAssignmentService::class)->assignMany($a2->order->fresh(), [$this->agentA, $this->agentB], $this->super);
        $this->actingAs($this->agentB)->getJson($url('tracking', $refunds[$a2->id]))->assertOk();
        $this->actingAs($this->agentB)->getJson($url('tracking', $refunds[$a1->id]))->assertNotFound();

        // reassignment: A's first order moves to B
        app(\App\Orders\OrderAssignmentService::class)->assign($a1->order->fresh(), $this->agentB, $this->super, 'moved');
        $this->actingAs($this->agentA)->getJson($url('tracking', $refunds[$a1->id]))->assertNotFound();
        $this->actingAs($this->agentB)->getJson($url('tracking', $refunds[$a1->id]))->assertOk();
        $this->assertSame($a1->integration_account_id, $refunds[$a1->id]->fresh()->integration_account_id);
    }

    public function test_the_tracking_endpoint_leaks_no_secrets_and_survives_a_lost_session(): void
    {
        $a = $this->paid($this->agentA, 2, 100);
        $r = $this->svc->request($a, $this->agentA, 'full', null, 'leak check refund', 'leak-1');
        $body = $this->actingAs($this->agentA)->getJson(route('admin.payments.refunds.tracking', $r))->assertOk()->getContent();
        foreach (['vendor_key', 'VENDOR-A', 'FKEY-', 'api_key', 'secret', 'Bearer', 'idempotency', 'integration_account'] as $secret) {
            $this->assertStringNotContainsString($secret, $body);
        }
        $this->assertStringContainsString($a->transaction_id, $body, 'staff with access may see the invoice id (needed to find it in Fawaterak)');

        // expired session: guests are redirected / refused, never given data
        auth()->logout();
        $this->app['auth']->forgetGuards();
        $this->getJson(route('admin.payments.refunds.tracking', $r))->assertStatus(401);
        $this->post(route('admin.payments.refunds.opened', $r))->assertRedirect();
        $this->assertSame('processing', $r->fresh()->status);
    }

    public function test_a_customer_keeps_access_to_the_valid_tickets_of_a_partly_refunded_order_but_not_to_refunded_ones(): void
    {
        $a = $this->paid($this->agentA, 3, 100);
        $order = $a->order;
        $customer = User::find($order->user_id);
        $order->forceFill(['owner_unverified' => false])->saveQuietly();
        $t = Ticket::where('order_id', $order->id)->orderBy('id')->get();
        $issued = fn (Ticket $x) => \App\Models\IssuedTicket::where('ticket_number', $x->ticket_number)->firstOrFail();

        $r = $this->svc->request($a, $this->agentA, 'partial', null, 'one attendee cancelled', 'cust-1', ['tickets' => [$t[0]->id]]);
        $this->svc->confirmSucceeded($r, $this->agentA, 'FAW-C', 'verified in the provider dashboard', now()->toDateString());
        $this->assertSame('partially_refunded', $order->fresh()->status);

        // the remaining tickets are still reachable and downloadable; the refunded one is not
        $this->actingAs($customer)->get(route('front.tickets.show', $issued($t[1])))->assertOk();
        $this->actingAs($customer)->get(route('front.tickets.download', $issued($t[1])))->assertOk();
        $this->actingAs($customer)->get(route('front.tickets.download', $issued($t[0])))->assertStatus(410);
        $this->assertTrue(\App\Models\IssuedTicket::ownedByCustomer($customer)->whereKey($issued($t[2])->id)->exists());
        $signed = \Illuminate\Support\Facades\URL::temporarySignedRoute('front.tickets.public-download', now()->addDay(), ['ticketNumber' => $t[0]->ticket_number]);
        $this->get($signed)->assertStatus(410);
        $ok = \Illuminate\Support\Facades\URL::temporarySignedRoute('front.tickets.public-download', now()->addDay(), ['ticketNumber' => $t[1]->ticket_number]);
        $this->get($ok)->assertOk();
    }

    public function test_the_refund_button_sits_in_the_order_header_and_the_merchant_account_card_is_gone(): void
    {
        $a = $this->paid($this->agentA, 2, 100);
        $html = $this->actingAs($this->agentA)->get(route('admin.orders.show', $a->order))->assertOk()->getContent();
        $headerEnd = strpos($html, 'a-ord-summary');
        $this->assertNotFalse($headerEnd);
        $this->assertStringContainsString('</i> Refund</button>', substr($html, 0, $headerEnd), 'the refund button is in the page header next to Edit / Change Status');
        $this->assertStringNotContainsString('Merchant account', $html, 'no merchant-account card on a paid order');
        // staff without refund rights get no button; a paid legacy order (no payment attempt) gets one too
        $plain = $this->staff('Order Agent', []);
        $o2 = $this->paid($plain, 1, 100)->order;
        $this->assertStringNotContainsString('</i> Refund</button>', $this->actingAs($plain)->get(route('admin.orders.show', $o2))->getContent());
    }
}
