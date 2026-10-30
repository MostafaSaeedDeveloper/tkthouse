<?php

namespace Tests\Feature;

use App\Http\Middleware\EnsureAdminPanelAccess;
use App\Models\Event;
use App\Models\IssuedTicket;
use App\Models\Order;
use App\Models\PaymentAttempt;
use App\Models\PaymentMethod;
use App\Models\PaymentRefund;
use App\Models\Ticket;
use App\Models\User;
use App\Orders\OrderAssignmentService;
use App\Services\Admin\DashboardMetrics;
use App\Services\Payments\PaymentService;
use App\Services\Payments\Refunds\RefundService;
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
 * Refunds follow the EXISTING Order Assignment access model (OrderAccess / visibleTo / assignedScope), nothing else:
 * refund visibility, amounts, dashboard figures, lists and every action are derived from "may this user access the order".
 */
class RefundAssignmentIsolationTest extends TestCase
{
    use BuildsMerchantAccounts, BuildsOrders, RefreshDatabase;

    private User $adminUser;
    private User $agentA;
    private User $agentB;
    private User $agentC;
    private RefundService $svc;

    private const REFUND_PERMS = ['payments.refunds.view', 'payments.refunds.create', 'payments.transactions.view'];

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

        $this->adminUser = $this->staff(null, ['orders.view', 'orders.view_all', 'orders.update', 'dashboard.view', 'payments.refunds.approve', ...self::REFUND_PERMS], ['can_receive_orders' => false]);
        $this->agentA = $this->staff('Order Agent', self::REFUND_PERMS);
        $this->agentB = $this->staff('Order Agent', self::REFUND_PERMS);
        $this->agentC = $this->staff('Order Agent', self::REFUND_PERMS);
    }

    private function staff(?string $role, array $perms, array $flags = ['can_receive_orders' => true]): User
    {
        $u = User::factory()->create();
        $u->forceFill($flags)->save();
        if ($role) {
            $u->assignRole($role);
        }
        foreach ($perms as $p) {
            Permission::findOrCreate($p, 'web');
        }
        $u->givePermissionTo($perms);

        return $u->fresh();
    }

    /** A paid order with issued tickets, assigned to $agent (null = unassigned). kind: manual (Fawaterak attempt) | legacy (none). */
    private function paidOrder(?User $agent, string $kind = 'manual', int $people = 2, float $unit = 100, ?Event $event = null): array
    {
        $order = $this->makePendingOrder(User::factory()->create(), $event, $people, $unit, 'fawaterak_card');
        $attempt = null;
        if ($kind === 'manual') {
            $acc = $this->account('fawaterak', 'ACC'.Str::random(6));
            $acc->forceFill(['refund_enabled_at' => now()])->save();
            $order->forceFill(['integration_account_id' => $acc->id])->saveQuietly();
            $attempt = PaymentAttempt::create(['order_id' => $order->id, 'provider' => 'fawaterak', 'method_code' => 'fawaterak_card', 'amount' => $unit * $people, 'currency' => 'EGP', 'status' => 'paid',
                'transaction_id' => (string) random_int(100000, 999999), 'reference' => $order->order_number.'-r', 'integration_account_id' => $acc->id, 'internal_reference' => PaymentAttempt::newInternalReference(), 'paid_at' => now(), 'settled_at' => now()]);
        }
        $order->forceFill(['status' => 'paid', 'paid_at' => now(), 'assigned_user_id' => $agent?->id, 'assigned_at' => $agent ? now() : null])->saveQuietly();
        app(TicketIssuanceService::class)->issueIfPaid($order->fresh());

        return [$order->fresh(), $attempt];
    }

    /** A confirmed (or other status) refund row, made directly for figure tests. */
    private function refundRow(Order $order, string $status, int $minor, $completed = null, array $o = []): PaymentRefund
    {
        return PaymentRefund::create($o + [
            'internal_reference' => 'REF-'.strtoupper(Str::random(10)), 'payment_attempt_id' => null, 'order_id' => $order->id, 'provider' => 'manual', 'mode' => 'manual',
            'type' => 'partial', 'status' => $status, 'awaiting_approval' => false, 'currency' => 'EGP', 'amount_minor' => $minor, 'reason' => 'reason-'.$order->order_number,
            'provider_reference' => 'TX-'.$order->order_number, 'idempotency_key' => Str::random(40), 'requested_at' => now(), 'requested_by' => $this->adminUser->id,
            'completed_at' => $status === 'succeeded' ? ($completed ?? now()) : null, 'effects_status' => 'not_applicable',
        ]);
    }

    private function request(array $target, ?User $by = null, string $type = 'full', ?string $amount = null, array $opts = []): PaymentRefund
    {
        [$order, $attempt] = $target;
        $key = Str::uuid()->toString();
        $by ??= $this->adminUser;

        return $attempt
            ? $this->svc->request($attempt, $by, $type, $amount, 'unique-reason-'.$order->order_number, $key, $opts)
            : $this->svc->requestForOrder($order, $by, $type, $amount, 'unique-reason-'.$order->order_number, $key, $opts);
    }

    private function confirm(PaymentRefund $r): PaymentRefund
    {
        return $this->svc->confirmSucceeded($r, $this->adminUser, 'EXT-'.$r->id, 'portal screenshot verified', now()->toDateString());
    }

    private function tokens(Order $o): array
    {
        return [$o->order_number, 'reason-'.$o->order_number, 'unique-reason-'.$o->order_number, 'TX-'.$o->order_number, $o->customer->email];
    }

    private function noneOf(string $body, array $needles, string $why): void
    {
        foreach ($needles as $n) {
            $this->assertStringNotContainsString($n, $body, "$why leaked: $n");
        }
    }

    /* ------------------------------------------------------------ visibility, URLs, actions */

    public function test_an_agent_sees_only_refunds_of_orders_they_may_access_on_every_screen_and_url(): void
    {
        $mine = $this->paidOrder($this->agentA)[0];
        $theirs = $this->paidOrder($this->agentB)[0];
        $rMine = $this->refundRow($mine, 'succeeded', 5000);
        $rTheirs = $this->refundRow($theirs, 'succeeded', 7000);
        $rTheirsPending = $this->refundRow($theirs, 'requested', 1000);

        // Refunds list: own only; filters, search and pagination cannot widen it
        $list = $this->actingAs($this->agentA)->get(route('admin.payments.refunds'));
        $list->assertOk()->assertSee($rMine->internal_reference);
        $this->noneOf($list->getContent(), [$rTheirs->internal_reference, $rTheirsPending->internal_reference, ...$this->tokens($theirs)], 'refunds list');
        foreach (['?status=succeeded', '?status=requested', '?provider=manual', '?page=2', '?status=succeeded&provider=manual&page=1'] as $q) {
            $this->noneOf($this->actingAs($this->agentA)->get(route('admin.payments.refunds').$q)->getContent(), [$rTheirs->internal_reference, $rTheirsPending->internal_reference], "refunds list $q");
        }

        // direct URLs with another agent's ids
        $this->actingAs($this->agentA)->get(route('admin.payments.refunds.show', $rMine))->assertOk();
        $this->actingAs($this->agentA)->get(route('admin.payments.refunds.show', $rTheirs))->assertNotFound();
        $this->actingAs($this->agentA)->get(route('admin.orders.show', $theirs))->assertNotFound();
        $this->actingAs($this->agentA)->get(route('admin.orders.show', $mine))->assertOk()->assertSee($rMine->internal_reference);
        $page = $this->actingAs($this->agentA)->get(route('admin.orders.show', $mine))->getContent();
        $this->noneOf($page, [$rTheirs->internal_reference, ...$this->tokens($theirs)], "own order page");

        // direct POSTs against the other agent's refund: refused and nothing changes
        $rOpen = $this->refundRow($theirs, 'requested', 2000);
        foreach (['approve', 'reject', 'submitted', 'confirm', 'fail', 'check', 'tickets'] as $action) {
            $this->actingAs($this->agentA)->post(route('admin.payments.refunds.'.$action, $rOpen), ['note' => 'forged note here', 'provider_reference' => 'X', 'refunded_at' => now()->toDateString(), 'tickets' => [1]])
                ->assertStatus(in_array($action, ['approve', 'reject', 'tickets'], true) ? 403 : 404);
        }
        $this->assertSame('requested', $rOpen->fresh()->status);

        // creating a refund for the other agent's order (modern and legacy forms)
        [$theirsModern, $att] = $this->paidOrder($this->agentB);
        $payload = ['type' => 'full', 'reason' => 'forged refund request', 'idempotency_key' => 'x-1', 'confirm' => 1];
        $this->actingAs($this->agentA)->post(route('admin.payments.refunds.store', $att), $payload)->assertNotFound();
        $this->actingAs($this->agentA)->post(route('admin.payments.refunds.store-order', $theirs), $payload)->assertNotFound();
        $this->assertSame(0, PaymentRefund::where('order_id', $theirsModern->id)->count());

        // transactions
        $this->actingAs($this->agentA)->get(route('admin.payments.transactions.show', $att))->assertNotFound();
        $this->actingAs($this->agentA)->get(route('admin.payments.index'))->assertOk();

        // the refund view of the orders list
        $href = route('admin.orders.index', ['refunded' => 1, 'stats' => 1, 'refunded_from' => now()->subDay()->toDateString(), 'refunded_to' => now()->toDateString()]);
        $res = $this->actingAs($this->agentA)->get($href);
        $this->assertSame([$mine->id], $res->viewData('orders')->getCollection()->pluck('id')->all());
        $this->noneOf($res->getContent(), $this->tokens($theirs), 'refunded orders list');
        $this->assertCount(0, $this->actingAs($this->agentA)->get($href.'&search='.$theirs->order_number)->viewData('orders')->getCollection());
        $this->assertCount(0, $this->actingAs($this->agentA)->get($href.'&reference='.$theirs->order_number.'&assignee=unassigned')->viewData('orders')->getCollection());
        $this->assertCount(0, $this->actingAs($this->agentA)->get($href.'&refund_event_id=999999')->viewData('orders')->getCollection(), 'an unknown event id cannot reveal anything');

        // global administrator keeps full visibility
        $all = $this->actingAs($this->adminUser)->get($href)->viewData('orders')->getCollection()->pluck('id')->sort()->values()->all();
        $this->assertSame(collect([$mine->id, $theirs->id])->sort()->values()->all(), $all);
        $this->actingAs($this->adminUser)->get(route('admin.payments.refunds.show', $rTheirs))->assertOk();
    }

    public function test_refund_permission_alone_never_opens_other_orders_and_missing_permissions_hide_amounts(): void
    {
        $order = $this->paidOrder($this->agentA)[0];
        $r = $this->refundRow($order, 'succeeded', 5000);

        // refund permissions but no order-handling right at all (fails closed under assignment)
        $outsider = $this->staff(null, ['orders.view', 'dashboard.view', ...self::REFUND_PERMS], ['can_receive_orders' => false]);
        $this->actingAs($outsider)->get(route('admin.payments.refunds.show', $r))->assertNotFound();
        $this->noneOf($this->actingAs($outsider)->get(route('admin.payments.refunds'))->getContent(), [$r->internal_reference], 'outsider refunds list');
        $this->actingAs($outsider)->get(route('admin.orders.show', $order))->assertNotFound();
        $this->assertSame([0.0, 0], [$m = (new DashboardMetrics([], null, $outsider, []))->refundStats(now()->subDay(), now()->endOfDay())['amount'], 0]);

        // an agent WITHOUT refund permissions: no refund screens, no amounts, no actions
        $plain = $this->staff('Order Agent', []);
        $order2 = $this->paidOrder($plain)[0];
        $r2 = $this->refundRow($order2, 'succeeded', 9000);
        $this->actingAs($plain)->get(route('admin.payments.refunds'))->assertForbidden();
        $this->actingAs($plain)->get(route('admin.payments.refunds.show', $r2))->assertForbidden();
        $this->actingAs($plain)->get(route('admin.orders.index', ['refunded' => 1]))->assertForbidden();
        $own = $this->actingAs($plain)->get(route('admin.orders.show', $order2));
        $own->assertOk();
        $this->noneOf($own->getContent(), [$r2->internal_reference, 'Confirmed refunded', 'Record manual / external refund'], 'agent without refund permission');
        $dash = $this->actingAs($plain)->get(route('admin.dashboard', ['range' => '30d']))->getContent();
        $this->assertStringContainsString('Total Refunded', $dash);
        $this->assertStringContainsString('90 EGP', $dash, 'the agent sees the refunds of their own orders');
        $this->assertStringNotContainsString('Refunded Orders', $dash);
        $this->assertStringNotContainsString('refunded=1', $dash, 'no link to the refund list without the refund permission');
        $this->actingAs($plain)->post(route('admin.payments.refunds.store-order', $order2), ['type' => 'full', 'reason' => 'no permission here', 'idempotency_key' => 'p-1', 'confirm' => 1])->assertForbidden();

        // view-only agent can see their refund but cannot act (needs create/approve + orders.manage_assigned)
        $viewer = $this->staff('Order Agent', ['payments.refunds.view']);
        $order3 = $this->paidOrder($viewer)[0];
        $r3 = $this->refundRow($order3, 'requested', 1000);
        $this->actingAs($viewer)->get(route('admin.payments.refunds.show', $r3))->assertOk();
        $this->actingAs($viewer)->post(route('admin.payments.refunds.confirm', $r3), ['provider_reference' => 'X', 'note' => 'forged confirm', 'refunded_at' => now()->toDateString()])->assertForbidden();
        $this->assertSame('requested', $r3->fresh()->status);
    }

    public function test_an_agent_cannot_select_or_cancel_another_agents_tickets(): void
    {
        $mine = $this->paidOrder($this->agentA, 'legacy')[0];
        $theirs = $this->paidOrder($this->agentB, 'legacy')[0];
        $theirTicket = Ticket::where('order_id', $theirs->id)->first();
        $agent = $this->staff('Order Agent', [...self::REFUND_PERMS, 'payments.refunds.approve']);
        $agent->assignRole('Order Agent');
        $own = $this->paidOrder($agent, 'legacy')[0];
        $ownRefund = $this->refundRow($own, 'succeeded', 10000, null, ['effects_status' => 'needs_review']);
        $theirRefund = $this->refundRow($theirs, 'succeeded', 5000, null, ['effects_status' => 'needs_review']);

        // route level: another agent's refund id is a 404/403 whatever tickets are posted
        $this->actingAs($agent)->post(route('admin.payments.refunds.tickets', $theirRefund), ['tickets' => [$theirTicket->id]])->assertStatus(404);
        $this->assertSame('not_checked_in', $theirTicket->fresh()->status);
        // own refund, but another agent's ticket id: refused by the ticket/order match
        $this->actingAs($agent)->post(route('admin.payments.refunds.tickets', $ownRefund), ['tickets' => [$theirTicket->id]])->assertSessionHasErrors('refund');
        $this->assertSame('not_checked_in', $theirTicket->fresh()->status);
        // own tickets work
        $ownTicket = Ticket::where('order_id', $own->id)->first();
        $this->actingAs($agent)->post(route('admin.payments.refunds.tickets', $ownRefund), ['tickets' => [$ownTicket->id]])->assertSessionHasNoErrors();
        $this->assertSame('canceled', $ownTicket->fresh()->status);
        // requesting a refund that names another order's tickets
        $this->expectException(\Illuminate\Validation\ValidationException::class);
        $this->svc->requestForOrder($mine, $this->adminUser, 'partial', '10', 'foreign ticket probe', 'k-f', ['tickets' => [$theirTicket->id]]);
    }

    public function test_multiple_assignees_share_visibility_exactly_like_the_order_assignment_rules(): void
    {
        $order = $this->paidOrder($this->agentA)[0];
        app(OrderAssignmentService::class)->assignMany($order->fresh(), [$this->agentA, $this->agentB], $this->adminUser);
        $r = $this->refundRow($order, 'succeeded', 5000);

        foreach ([$this->agentA, $this->agentB] as $u) {
            $this->actingAs($u)->get(route('admin.payments.refunds.show', $r))->assertOk();
            $this->assertSame(50.0, (new DashboardMetrics([], null, $u, []))->refundStats(now()->subDay(), now()->endOfDay())['amount']);
        }
        $this->actingAs($this->agentC)->get(route('admin.payments.refunds.show', $r))->assertNotFound();
        $this->assertSame(0.0, (new DashboardMetrics([], null, $this->agentC, []))->refundStats(now()->subDay(), now()->endOfDay())['amount']);
    }

    /* ------------------------------------------------------------ reassignment */

    public function test_reassignment_moves_refund_visibility_and_figures_but_not_history_or_the_gateway_account(): void
    {
        [$order, $attempt] = $this->paidOrder($this->agentA, 'manual', 2, 100);
        $accountId = $attempt->integration_account_id;
        $this->confirm($r = $this->request([$order, $attempt], $this->adminUser));
        $r = $r->fresh();
        $window = [now()->subDay(), now()->endOfDay()];

        // 1-2. Agent A sees the order, its refund and its money
        $this->actingAs($this->agentA)->get(route('admin.payments.refunds.show', $r))->assertOk();
        $this->assertSame([200.0, 1], array_values(array_intersect_key((new DashboardMetrics([], null, $this->agentA, []))->refundStats(...$window), array_flip(['amount', 'orders']))));
        $this->assertSame([0.0, 0], array_values(array_intersect_key((new DashboardMetrics([], null, $this->agentB, []))->refundStats(...$window), array_flip(['amount', 'orders']))));

        // 3. reassign to Agent B
        app(OrderAssignmentService::class)->assign($order->fresh(), $this->agentB, $this->adminUser, 'workload');

        // 4. Agent A is out
        $this->actingAs($this->agentA)->get(route('admin.payments.refunds.show', $r))->assertNotFound();
        $this->actingAs($this->agentA)->get(route('admin.orders.show', $order))->assertNotFound();
        $this->actingAs($this->agentA)->get(route('admin.payments.transactions.show', $attempt))->assertNotFound();
        $this->noneOf($this->actingAs($this->agentA)->get(route('admin.payments.refunds'))->getContent(), [$r->internal_reference, $order->order_number], 'old agent list');
        // 5. Agent B is in
        $this->actingAs($this->agentB)->get(route('admin.payments.refunds.show', $r))->assertOk()->assertSee($r->internal_reference);
        // 6. figures moved
        $this->assertSame([0.0, 0], array_values(array_intersect_key((new DashboardMetrics([], null, $this->agentA, []))->refundStats(...$window), array_flip(['amount', 'orders']))));
        $this->assertSame([200.0, 1], array_values(array_intersect_key((new DashboardMetrics([], null, $this->agentB, []))->refundStats(...$window), array_flip(['amount', 'orders']))));

        // 7. history and the original merchant account are untouched
        $after = $r->fresh();
        $this->assertSame([$r->requested_by, $r->approved_by, $r->confirmed_by, $r->integration_account_id, $r->amount_minor], [$after->requested_by, $after->approved_by, $after->confirmed_by, $after->integration_account_id, $after->amount_minor]);
        $this->assertSame($accountId, $after->integration_account_id);
        $this->assertSame($accountId, $attempt->fresh()->integration_account_id);
        $this->assertSame($accountId, $order->fresh()->integration_account_id);
        $this->assertSame('refunded', $order->fresh()->status);
    }

    /* ------------------------------------------------------------ dashboards */

    public function test_each_agents_dashboard_counts_only_their_own_refunds_and_the_kpi_links_keep_that_scope(): void
    {
        $eventX = $this->makeEvent(['name' => 'Xfest']);
        $eventY = $this->makeEvent(['name' => 'Yfest']);
        $o1 = $this->paidOrder($this->agentA, 'legacy', 2, 1000, $eventX)[0];  // refunded 1000
        $o2 = $this->paidOrder($this->agentA, 'legacy', 1, 500, $eventY)[0];   // refunded 500
        $o3 = $this->paidOrder($this->agentB, 'legacy', 5, 500, $eventX)[0];   // refunded 2000
        $hidden = $this->paidOrder($this->agentA, 'legacy', 1, 100, $eventX)[0];
        $hidden->forceFill(['exclude_from_statistics' => true])->saveQuietly();
        $this->refundRow($o1, 'succeeded', 100000);
        $this->refundRow($o2, 'succeeded', 50000, null, ['type' => 'full']);
        $this->refundRow($o3, 'succeeded', 200000);
        $this->refundRow($o3, 'requested', 30000);          // pending: not counted as refunded
        $this->refundRow($hidden, 'succeeded', 9900);       // statistics-excluded
        $this->refundRow($o1, 'succeeded', 7700, now()->subDays(90)); // outside the period

        $w = [now()->subDays(29)->startOfDay(), now()->endOfDay()];
        $stat = fn (User $u, ?Event $e = null) => array_intersect_key((new DashboardMetrics([], $e, $u, []))->refundStats(...$w), array_flip(['amount', 'orders', 'pending_amount', 'pending_count']));
        $this->assertSame(['amount' => 1500.0, 'orders' => 2, 'pending_amount' => 0.0, 'pending_count' => 0], $stat($this->agentA));
        $this->assertSame(['amount' => 2000.0, 'orders' => 1, 'pending_amount' => 300.0, 'pending_count' => 1], $stat($this->agentB));
        $this->assertSame(['amount' => 3500.0, 'orders' => 3, 'pending_amount' => 300.0, 'pending_count' => 1], $stat($this->adminUser));
        $this->assertSame(['amount' => 1000.0, 'orders' => 1, 'pending_amount' => 0.0, 'pending_count' => 0], $stat($this->agentA, $eventX), 'event filter');
        $this->assertSame(0.0, $stat($this->agentC)['amount']);

        // Net collected for a scoped agent = THEIR captured payments - THEIR confirmed refunds
        $k = (new DashboardMetrics([], null, $this->agentA, []))->kpis(...$w);
        $this->assertSame([2500.0, 1500.0, 1000.0], [$k['sales'], $k['refunded'], $k['net_collected']]);
        $kb = (new DashboardMetrics([], null, $this->agentB, []))->kpis(...$w);
        $this->assertSame([2500.0, 2000.0, 500.0], [$kb['sales'], $kb['refunded'], $kb['net_collected']]);

        // the rendered dashboards and their KPI links
        $range = ['range' => 'custom', 'from' => $w[0]->toDateString(), 'to' => $w[1]->toDateString()];
        foreach ([[$this->agentA, '1,500 EGP', [$o1->id, $o2->id]], [$this->agentB, '2,000 EGP', [$o3->id]], [$this->adminUser, '3,500 EGP', [$o1->id, $o2->id, $o3->id]]] as [$u, $text, $ids]) {
            $page = $this->actingAs($u)->get(route('admin.dashboard', $range));
            $page->assertOk()->assertSee($text);
            $href = route('admin.orders.index', ['refunded' => 1, 'stats' => 1, 'refunded_from' => $w[0]->toDateString(), 'refunded_to' => $w[1]->toDateString()]);
            $page->assertSee(e($href), false);
            $got = $this->actingAs($u)->get($href)->viewData('orders')->getCollection()->pluck('id')->sort()->values()->all();
            $this->assertSame(collect($ids)->sort()->values()->all(), $got, 'the KPI link lists exactly the orders the card counted');
        }
        // the dashboard event filter cannot reveal another agent's order
        $this->actingAs($this->agentA)->get(route('admin.dashboard', ['event_id' => $eventX->id] + $range))->assertOk()->assertSee('1,000 EGP');
    }

    /* ------------------------------------------------------------ global ledgers are closed to scoped users */

    public function test_provider_wide_ledgers_are_closed_to_order_scoped_users_even_with_payment_permissions(): void
    {
        $agent = $this->staff('Order Agent', [...self::REFUND_PERMS, 'payments.webhooks.view', 'payments.accounts.view', 'payments.transactions.import', 'payments.transactions.inquire']);
        $this->actingAs($agent)->get(route('admin.payments.webhooks'))->assertForbidden();
        $this->actingAs($agent)->get(route('admin.payments.imported'))->assertForbidden();
        $global = $this->staff(null, ['orders.view', 'orders.view_all', 'payments.webhooks.view', 'payments.accounts.view'], ['can_receive_orders' => false]);
        $this->actingAs($global)->get(route('admin.payments.webhooks'))->assertOk();
        $this->actingAs($global)->get(route('admin.payments.imported'))->assertOk();
    }

    /* ------------------------------------------------------------ webhook safety around refunds (mocked Fawaterak) */

    private function fawAttempt(User $agent, string $vendorKey = 'VENDOR-A'): PaymentAttempt
    {
        $acc = $this->account('fawaterak', 'FW'.Str::random(5), [], ['vendor_key' => $vendorKey]);
        $acc->forceFill(['refund_enabled_at' => now()])->save();
        $order = $this->makePendingOrder(User::factory()->create(), null, 2, 150, 'fawaterak_card');
        $order->forceFill(['integration_account_id' => $acc->id, 'assigned_user_id' => $agent->id, 'assigned_at' => now()])->saveQuietly();
        app(PaymentService::class)->start($order->fresh(), PaymentMethod::where('code', 'fawaterak_card')->first());

        return $order->paymentAttempts()->firstOrFail();
    }

    private function payload(PaymentAttempt $a, string $key = 'VENDOR-A'): array
    {
        $p = ['invoice_id' => $a->transaction_id, 'invoice_key' => 'IK1', 'payment_method' => 'Card', 'invoice_status' => 'paid'];

        return $p + ['hashKey' => hash_hmac('sha256', 'InvoiceId='.$p['invoice_id'].'&InvoiceKey='.$p['invoice_key'].'&PaymentMethod='.$p['payment_method'], $key)];
    }

    public function test_payment_callbacks_after_a_confirmed_refund_cannot_restore_tickets_or_the_order(): void
    {
        $a = $this->fawAttempt($this->agentA);
        $order = $a->order;
        $this->fawInvoices[$a->transaction_id]['paid'] = true;
        $this->postJson(route('front.fawaterak.webhook'), $this->payload($a))->assertOk();
        $this->assertSame('paid', $order->fresh()->status);
        $tickets = Ticket::where('order_id', $order->id)->count();
        $this->assertSame(2, $tickets);

        $this->confirm($this->request([$order->fresh(), $a->fresh()]));
        $this->assertSame(['refunded', ['canceled', 'canceled']], [$order->fresh()->status, Ticket::where('order_id', $order->id)->pluck('status')->all()]);

        // duplicate and delayed copies of the paid webhook, plus a reconciliation run
        for ($i = 0; $i < 3; $i++) {
            $this->postJson(route('front.fawaterak.webhook'), $this->payload($a))->assertOk();
        }
        app(PaymentService::class)->refreshAttempt($a->fresh());
        app(PaymentService::class)->reconcile();
        app(TicketIssuanceService::class)->issueIfPaid($order->fresh());

        $this->assertSame('refunded', $order->fresh()->status);
        $this->assertSame(['canceled', 'canceled'], Ticket::where('order_id', $order->id)->pluck('status')->all());
        $this->assertSame($tickets, Ticket::where('order_id', $order->id)->count());
        $this->assertSame(2, IssuedTicket::where('order_id', $order->id)->count());
        $this->assertSame('succeeded', PaymentRefund::where('order_id', $order->id)->first()->status);
        $this->assertSame('300.00', number_format((float) $order->fresh()->total_amount, 2, '.', ''));

        // a SECOND payment for the already refunded order is recorded for review and fulfils nothing
        $second = PaymentAttempt::create(['order_id' => $order->id, 'provider' => 'fawaterak', 'method_code' => 'fawaterak_card', 'amount' => 300, 'currency' => 'EGP', 'status' => 'initiated',
            'transaction_id' => 'INV-LATE-1', 'reference' => $order->order_number.'-late', 'integration_account_id' => $a->integration_account_id, 'internal_reference' => PaymentAttempt::newInternalReference()]);
        $this->fawInvoices['INV-LATE-1'] = ['owner' => 'FKEY-'.$a->integrationAccount->name, 'paid' => true, 'total' => 300.0];
        app(PaymentService::class)->refreshAttempt($second->fresh());
        $this->assertSame('refunded', $order->fresh()->status, 'a late payment never reopens a refunded order');
        $this->assertSame(['canceled', 'canceled'], Ticket::where('order_id', $order->id)->pluck('status')->all());
        $this->assertSame(2, Ticket::where('order_id', $order->id)->count());
    }

    public function test_a_fawaterak_refund_notification_is_never_proof_and_wrong_account_hashes_are_refused(): void
    {
        $a = $this->fawAttempt($this->agentA);
        $this->fawInvoices[$a->transaction_id]['paid'] = true;
        $this->postJson(route('front.fawaterak.webhook'), $this->payload($a))->assertOk();
        $order = $a->order->fresh();
        $r = $this->request([$order, $a->fresh()]);

        $this->postJson(route('front.fawaterak.webhook'), ['transactionId' => $a->transaction_id, 'amount' => '300', 'currency' => 'EGP', 'status' => 'approved', 'reason' => 'x', 'approvedAt' => now()->toDateString()])->assertOk();
        $this->assertSame('processing', $r->fresh()->status, 'an unauthenticated "approved" is a hint only');
        $this->assertSame('paid', $order->fresh()->status);
        $this->assertSame(['not_checked_in', 'not_checked_in'], Ticket::where('order_id', $order->id)->pluck('status')->all());

        // a paid event signed with ANOTHER merchant account's vendor key is refused
        $b = $this->fawAttempt($this->agentB, 'VENDOR-B');
        $this->fawInvoices[$b->transaction_id]['paid'] = true;
        $this->postJson(route('front.fawaterak.webhook'), $this->payload($b, 'VENDOR-A'))->assertStatus(403);
        $this->assertSame('pending_payment', $b->order->fresh()->status);
    }

    public function test_reassigning_an_order_while_its_payment_is_in_flight_keeps_the_original_merchant_account(): void
    {
        $a = $this->fawAttempt($this->agentA);
        $accountId = $a->integration_account_id;
        app(OrderAssignmentService::class)->assign($a->order->fresh(), $this->agentB, $this->adminUser, 'busy');

        $this->fawInvoices[$a->transaction_id]['paid'] = true;
        $this->postJson(route('front.fawaterak.webhook'), $this->payload($a))->assertOk();

        $this->assertSame('paid', $a->order->fresh()->status);
        $this->assertSame($accountId, $a->fresh()->integration_account_id, 'the payment stays with the account that collected it');
        $this->assertSame($this->agentB->id, (int) $a->order->fresh()->assigned_user_id);
        $r = $this->confirm($this->request([$a->order->fresh(), $a->fresh()]));
        $this->assertSame($accountId, $r->integration_account_id, 'the refund is tied to the original merchant account, not the new assignee');
        $this->actingAs($this->agentB)->get(route('admin.payments.refunds.show', $r))->assertOk();
        $this->actingAs($this->agentA)->get(route('admin.payments.refunds.show', $r))->assertNotFound();
    }
}
