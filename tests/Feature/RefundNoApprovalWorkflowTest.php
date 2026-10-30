<?php

namespace Tests\Feature;

use App\Http\Middleware\EnsureAdminPanelAccess;
use App\Models\Order;
use App\Models\PaymentAttempt;
use App\Models\PaymentRefund;
use App\Models\ScanLog;
use App\Models\Ticket;
use App\Models\User;
use App\Services\Admin\DashboardMetrics;
use App\Services\Payments\Refunds\RefundService;
use App\Services\Payments\Refunds\RefundTracking;
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
 * Refund workflow with "second-person approval" switched OFF: one authorized person (an agent on their own assigned order) requests,
 * opens Fawaterak, and later records the VERIFIED external completion. Approval code stays available (mode ON is tested here too).
 */
class RefundNoApprovalWorkflowTest extends TestCase
{
    use BuildsMerchantAccounts, BuildsOrders, RefreshDatabase;

    private RefundService $svc;
    private User $agentA;
    private User $agentB;
    private User $admin;

    private const AGENT_PERMS = ['payments.refunds.view', 'payments.refunds.create', 'payments.transactions.view'];

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(EnsureAdminPanelAccess::class);
        Mail::fake();
        $this->fakeProviders();
        SystemSettings::set('order_assignment_enabled', true);
        SystemSettings::set('refund_approval_required', false);
        $this->svc = app(RefundService::class);
        $this->makeMethod('fawaterak', 'fawaterak_card', ['api_key' => 'L', 'provider_key' => 'FAWATERAK.1']);
        $this->agentA = $this->staff('Order Agent', self::AGENT_PERMS);
        $this->agentB = $this->staff('Order Agent', self::AGENT_PERMS);
        $this->admin = $this->staff(null, ['orders.view', 'orders.view_all', 'orders.update', 'dashboard.view', 'settings.view', 'settings.update', 'settings.payment-accounts.view', 'settings.payment-accounts.manage', 'payments.refunds.approve', ...self::AGENT_PERMS], ['can_receive_orders' => false]);
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

    private function paid(User $agent, int $people = 2, float $unit = 100): array
    {
        $acc = $this->account('fawaterak', 'FW'.Str::random(5));
        $acc->forceFill(['refund_enabled_at' => now()])->save();
        $order = $this->makePendingOrder(User::factory()->create(), null, $people, $unit, 'fawaterak_card');
        $order->forceFill(['integration_account_id' => $acc->id, 'assigned_user_id' => $agent->id, 'assigned_at' => now()])->saveQuietly();
        $a = PaymentAttempt::create(['order_id' => $order->id, 'provider' => 'fawaterak', 'method_code' => 'fawaterak_card', 'amount' => $unit * $people, 'currency' => 'EGP', 'status' => 'paid',
            'transaction_id' => (string) random_int(100000, 999999), 'reference' => $order->order_number.'-r', 'integration_account_id' => $acc->id, 'internal_reference' => PaymentAttempt::newInternalReference(), 'paid_at' => now(), 'settled_at' => now()]);
        $order->forceFill(['status' => 'paid', 'paid_at' => now()])->saveQuietly();
        app(TicketIssuanceService::class)->issueIfPaid($order->fresh());

        return [$order->fresh(), $a];
    }

    private function submit(User $by, Order $order, string $type = 'full', array $extra = []): \Illuminate\Testing\TestResponse
    {
        $attempt = $order->paymentAttempts()->where('status', 'paid')->first();

        return $this->actingAs($by)->post($attempt ? route('admin.payments.refunds.store', $attempt) : route('admin.payments.refunds.store-order', $order), $extra + ['type' => $type, 'reason' => 'customer cannot attend any more', 'idempotency_key' => Str::uuid()->toString(), 'confirm' => 1]);
    }

    private function request(array $t, User $by, string $type = 'full', ?string $amount = null, array $opts = []): PaymentRefund
    {
        return $this->svc->request($t[1], $by, $type, $amount, 'customer cannot attend any more', Str::uuid()->toString(), $opts);
    }

    private function statuses(Order $o): array
    {
        return Ticket::where('order_id', $o->id)->orderBy('id')->pluck('status')->all();
    }

    /* ------------------------------------------------------------ the one-person workflow */

    public function test_one_agent_runs_the_whole_workflow_and_nothing_is_completed_until_verified(): void
    {
        [$order, $a] = $this->paid($this->agentA, 3, 100);
        $win = [now()->subDay(), now()->endOfDay()];
        $mine = fn () => array_values(array_intersect_key((new DashboardMetrics([], null, $this->agentA, []))->refundStats(...$win), array_flip(['amount', 'orders'])));
        $tickets = Ticket::where('order_id', $order->id)->orderBy('id')->get();

        // 1-5: the agent requests a PARTIAL refund of one ticket; no second user, immediately processing externally
        $this->actingAs($this->agentA)->get(route('admin.orders.show', $order))->assertOk()->assertSee('Record manual / external refund');
        $this->submit($this->agentA, $order->fresh(), 'partial', ['tickets' => [$tickets[0]->id], 'external_method' => 'provider_portal'])->assertSessionHasNoErrors();
        $r = PaymentRefund::firstOrFail();
        $this->assertSame([false, 'processing', $this->agentA->id, null, null], [(bool) $r->awaiting_approval, $r->status, $r->requested_by, $r->approved_by, $r->confirmed_by]);
        $this->assertSame('waiting_external', RefundTracking::state($r));
        $this->assertStringContainsString('Processing externally', RefundTracking::snapshot($r)['label']);
        $this->assertContains('waiting_external_refund', collect($r->timeline())->pluck('event')->all());
        $this->assertSame('paid', $order->fresh()->status);
        $this->assertSame(['not_checked_in', 'not_checked_in', 'not_checked_in'], $this->statuses($order));
        $this->assertSame([0.0, 0], $mine());

        // 6: Open in Fawaterak is audit-only and never completes anything
        $page = $this->actingAs($this->agentA)->get(route('admin.payments.refunds.show', $r))->assertOk()->assertSee('Open Transaction in Fawaterak')->assertSee($a->transaction_id)->assertSee('Processing externally');
        $this->assertStringNotContainsString('FKEY-', $page->getContent());
        $this->actingAs($this->agentA)->postJson(route('admin.payments.refunds.opened', $r))->assertOk();
        $this->assertSame('processing', $r->fresh()->status);

        // 7-8: the unsigned webhook is only a hint
        $this->postJson(route('front.fawaterak.webhook'), ['transactionId' => $a->transaction_id, 'amount' => '100', 'currency' => 'EGP', 'status' => 'approved', 'reason' => 'x', 'approvedAt' => '2026-10-30 12:00:00'])->assertOk();
        $this->assertSame(['processing', 'notification_received'], [$r->fresh()->status, RefundTracking::state($r->fresh())]);
        $this->assertSame(['not_checked_in', 'not_checked_in', 'not_checked_in'], $this->statuses($order));
        $this->assertSame([0.0, 0], $mine());

        // 9-10: the agent records the verified completion (reference, date, evidence) - still no second user
        $this->actingAs($this->agentA)->post(route('admin.payments.refunds.confirm', $r), ['provider_reference' => 'FAW-REF-1', 'note' => 'seen as refunded in the Fawaterak dashboard', 'refunded_at' => now()->toDateString(), 'external_method' => 'provider_portal'])->assertSessionHasNoErrors();
        $done = $r->fresh();
        $this->assertSame(['succeeded', $this->agentA->id, 'applied', 'FAW-REF-1'], [$done->status, $done->confirmed_by, $done->effects_status, $done->provider_reference]);
        $this->assertSame('partially_refunded', $order->fresh()->status);
        $this->assertSame(['canceled', 'not_checked_in', 'not_checked_in'], $this->statuses($order), 'only the selected ticket');
        $this->assertSame([100.0, 1], $mine());
        $this->assertSame([100.0, 1], array_values(array_intersect_key((new DashboardMetrics([], null, $this->admin, []))->refundStats(...$win), array_flip(['amount', 'orders']))));
        $this->assertSame([0.0, 0], array_values(array_intersect_key((new DashboardMetrics([], null, $this->agentB, []))->refundStats(...$win), array_flip(['amount', 'orders']))));

        // scanner: the refunded ticket is refused with the reason, the others still enter
        $scanner = $this->staff(null, ['scanner.access', 'tickets.view'], ['can_receive_orders' => false]);
        $this->actingAs($scanner)->postJson(route('admin.tickets.scanner.lookup'), ['code' => $tickets[0]->ticket_number])->assertStatus(409)->assertSee('customer cannot attend any more');
        $this->actingAs($scanner)->postJson(route('admin.tickets.scanner.lookup'), ['code' => $tickets[1]->ticket_number])->assertOk();

        // repeated confirmation / webhook / polling add nothing
        $this->actingAs($this->agentA)->post(route('admin.payments.refunds.confirm', $r), ['provider_reference' => 'FAW-REF-1', 'note' => 'again again again', 'refunded_at' => now()->toDateString()])->assertSessionHasErrors('refund');
        $this->postJson(route('front.fawaterak.webhook'), ['transactionId' => $a->transaction_id, 'amount' => '100', 'currency' => 'EGP', 'status' => 'approved', 'approvedAt' => '2026-10-30 12:00:00'])->assertOk();
        $this->actingAs($this->agentA)->getJson(route('admin.payments.refunds.tracking', $r))->assertOk()->assertJson(['completed' => true]);
        $this->assertSame(1, ScanLog::where('action', 'refund_canceled')->count());
        $this->assertSame([100.0, 1], $mine());

        // audit trail: requested, waiting, opened, notification, confirmed, effects - each with who
        $events = collect($done->fresh()->timeline())->pluck('event')->all();
        foreach (['requested', 'waiting_external_refund', 'opened_in_provider', 'provider_notification_received', 'confirmed_by_staff', 'effects_applied'] as $e) {
            $this->assertContains($e, $events);
        }
    }

    public function test_the_order_page_modal_gets_json_with_what_the_waiting_popup_needs_and_refusals_stay_in_the_modal(): void
    {
        config(['payments.fawaterak.dashboard_url' => 'https://dashboard.example.test/']);
        [$order] = $this->paid($this->agentA, 2, 100);
        $json = ['Accept' => 'application/json'];
        $attempt = $order->paymentAttempts()->where('status', 'paid')->first();
        $url = route('admin.payments.refunds.store', $attempt);

        $this->actingAs($this->agentA)->postJson($url, ['type' => 'full', 'reason' => 'x', 'idempotency_key' => Str::uuid()->toString(), 'confirm' => 1])->assertStatus(422);

        $ok = $this->actingAs($this->agentA)->postJson($url, ['type' => 'full', 'reason' => 'customer cannot attend any more', 'idempotency_key' => Str::uuid()->toString(), 'confirm' => 1])
            ->assertOk()->assertJsonPath('ok', true)->assertJsonPath('waiting', true)->assertJsonPath('provider', 'Fawaterak')->assertJsonPath('dashboard_url', 'https://dashboard.example.test/');
        $this->assertNotEmpty($ok->json('refund'));
        $this->assertNotEmpty($ok->json('redirect'));
        $this->assertSame('processing', PaymentRefund::first()->status);

        $again = $this->actingAs($this->agentA)->postJson($url, ['type' => 'full', 'reason' => 'customer cannot attend any more', 'idempotency_key' => Str::uuid()->toString(), 'confirm' => 1])->assertStatus(422);
        $this->assertNotEmpty($again->json('message'));
        $this->assertSame(1, PaymentRefund::count());
    }

    public function test_confirmation_stays_with_authorized_staff_of_that_order_and_the_merchant_account_is_untouched(): void
    {
        [$order, $a] = $this->paid($this->agentA);
        $this->submit($this->agentA, $order, 'full');
        $r = PaymentRefund::firstOrFail();
        $ok = ['provider_reference' => 'X-1', 'note' => 'forged confirmation attempt', 'refunded_at' => now()->toDateString()];

        $this->actingAs($this->agentB)->post(route('admin.payments.refunds.confirm', $r), $ok)->assertNotFound();
        $this->actingAs($this->agentB)->post(route('admin.payments.refunds.release', $r), ['note' => 'forged release'])->assertNotFound();
        $this->actingAs($this->agentB)->getJson(route('admin.payments.refunds.tracking', $r))->assertNotFound();
        $noRights = $this->staff('Order Agent', ['payments.refunds.view']);
        $order2 = $this->paid($noRights);
        $r2 = $this->request($order2, $this->admin);
        $this->actingAs($noRights)->post(route('admin.payments.refunds.confirm', $r2), $ok)->assertForbidden();
        $this->assertSame('processing', $r->fresh()->status);
        $this->assertSame($a->integration_account_id, $r->fresh()->integration_account_id);
        $this->assertStringNotContainsString('FKEY-', $this->actingAs($this->agentA)->get(route('admin.payments.refunds.show', $r))->getContent());
    }

    public function test_creating_a_refund_requires_access_to_the_order(): void
    {
        [$order] = $this->paid($this->agentA);
        $this->submit($this->agentB, $order)->assertNotFound();
        $this->assertSame(0, PaymentRefund::count());
        $this->submit($this->agentA, $order)->assertSessionHasNoErrors();
        $this->submit($this->agentA, $order, 'full', ['idempotency_key' => PaymentRefund::first()->idempotency_key]); // double submit is harmless
        $this->assertSame(1, PaymentRefund::count());
    }

    /* ------------------------------------------------------------ approval stays available */

    public function test_the_approval_mode_still_works_when_it_is_switched_on(): void
    {
        SystemSettings::set('refund_approval_required', true);
        [$order] = $t = $this->paid($this->agentA);
        $r = $this->request($t, $this->agentA);
        $this->assertSame(['requested', true], [$r->status, (bool) $r->awaiting_approval]);
        $this->assertSame('awaiting_approval', RefundTracking::state($r));

        try {
            $this->svc->confirmSucceeded($r, $this->agentA, 'X-1', 'confirm before approval', now()->toDateString());
            $this->fail('must wait for approval');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('waiting for approval', $e->errors()['refund'][0]);
        }
        try {
            $this->svc->releaseWithoutApproval($r, $this->admin, 'trying to bypass the policy');
            $this->fail('release is only possible once the policy is off');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('still required', $e->errors()['refund'][0]);
        }
        $this->svc->approve($r, $this->admin);
        $this->assertSame(['requested', false, $this->admin->id], [$r->fresh()->status, (bool) $r->fresh()->awaiting_approval, $r->fresh()->approved_by]);
        $this->svc->markSubmitted($r->fresh(), $this->agentA);
        $this->svc->confirmSucceeded($r->fresh(), $this->agentA, 'X-2', 'verified in dashboard', now()->toDateString());
        $this->assertSame('refunded', $order->fresh()->status);
    }

    public function test_requests_waiting_for_approval_are_never_approved_by_a_deployment_or_the_setting_and_have_an_explicit_release_path(): void
    {
        SystemSettings::set('refund_approval_required', true);
        $t = $this->paid($this->agentA);
        $r = $this->request($t, $this->agentA);
        $mine = $this->paid($this->agentB);
        $other = $this->request($mine, $this->agentB);

        // turning the policy off through the Settings UI route: audited, and approves/changes nothing
        $this->actingAs($this->admin)->put(route('admin.settings.section.update', 'payments'), [])->assertRedirect();
        $this->assertFalse($this->svc->approvalRequired());
        $this->assertDatabaseHas('setting_audits', ['key' => 'refund_approval_required', 'user_id' => $this->admin->id]);
        foreach ([$r, $other] as $x) {
            $x = $x->fresh();
            $this->assertSame(['requested', true, null], [$x->status, (bool) $x->awaiting_approval, $x->approved_by], 'nothing is approved silently');
        }
        // it cannot be confirmed either, and nobody else's agent may release it
        try {
            $this->svc->confirmSucceeded($r, $this->agentA, 'X-1', 'confirm while waiting', now()->toDateString());
            $this->fail('still waiting');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('waiting for approval', $e->errors()['refund'][0]);
        }
        $this->actingAs($this->agentB)->post(route('admin.payments.refunds.release', $r), ['note' => 'not my order here'])->assertNotFound();
        $this->actingAs($this->agentA)->post(route('admin.payments.refunds.release', $r), ['note' => 'x'])->assertSessionHasErrors('note');

        // explicit, audited release by the requester (their own assigned order) - and by an approver for the other
        $this->actingAs($this->agentA)->get(route('admin.payments.refunds.show', $r))->assertOk()->assertSee('nothing was approved automatically');
        $this->actingAs($this->agentA)->post(route('admin.payments.refunds.release', $r), ['note' => 'policy switched off, releasing my request'])->assertSessionHasNoErrors();
        $x = $r->fresh();
        $this->assertSame(['processing', false, null], [$x->status, (bool) $x->awaiting_approval, $x->approved_by], 'released, not approved and not completed');
        $this->assertContains('released_without_second_approval', collect($x->timeline())->pluck('event')->all());
        $this->assertSame('paid', $t[0]->fresh()->status);
        $this->assertSame('refunded', ($this->svc->confirmSucceeded($x, $this->agentA, 'X-3', 'verified in dashboard', now()->toDateString()) ? $t[0]->fresh() : null)->status);

        $this->svc->releaseWithoutApproval($other->fresh(), $this->admin, 'released by an approver');
        $this->assertSame('processing', $other->fresh()->status);
        $this->expectException(ValidationException::class);
        $this->svc->releaseWithoutApproval($other->fresh(), $this->admin, 'released twice by mistake'); // no longer waiting
    }

    public function test_switching_approval_back_on_does_not_disturb_refunds_already_processing(): void
    {
        $t = $this->paid($this->agentA);
        $r = $this->request($t, $this->agentA);
        SystemSettings::set('refund_approval_required', true);
        $this->assertSame(['processing', false], [$r->fresh()->status, (bool) $r->fresh()->awaiting_approval]);
        $this->svc->confirmSucceeded($r->fresh(), $this->agentA, 'X-4', 'verified in dashboard', now()->toDateString());
        $this->assertSame('refunded', $t[0]->fresh()->status);
        // a NEW request is gated again
        $t2 = $this->paid($this->agentA);
        $this->assertTrue((bool) $this->request($t2, $this->agentA)->awaiting_approval);
    }
}
