<?php

namespace Tests\Feature;

use App\Http\Middleware\EnsureAdminPanelAccess;
use App\Models\IntegrationAccount;
use App\Models\IssuedTicket;
use App\Models\Order;
use App\Models\PaymentAttempt;
use App\Models\PaymentMethod;
use App\Models\PaymentRefund;
use App\Models\User;
use App\Models\WebhookEvent;
use App\Services\Payments\PaymentService;
use App\Services\Payments\Refunds\RefundService;
use App\Support\SystemSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Permission;
use Tests\Support\BuildsMerchantAccounts;
use Tests\Support\BuildsOrders;
use Tests\TestCase;

/** Refund workflow: balance, idempotency, provider API (mocked), unknown outcomes, manual workflow, permissions. No real money moves. */
class RefundTest extends TestCase
{
    use BuildsMerchantAccounts, BuildsOrders, RefreshDatabase;

    private RefundService $svc;
    private User $staff;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        $this->fakeProviders();
        config(['payments.refunds.live_api_enabled' => true, 'payments.fake.enabled' => true]);
        $this->svc = app(RefundService::class);
        $this->makeMethod('paymob', 'paymob_card', ['api_key' => 'L', 'iframe_id' => '1', 'integration_id' => '1']);
        $this->makeMethod('fawaterak', 'fawaterak_card', ['api_key' => 'L', 'provider_key' => 'FAWATERAK.1']);
        $this->makeMethod('fake', 'fake_card');
        $this->staff = $this->user(['payments.refunds.create', 'payments.refunds.view', 'payments.transactions.view', 'orders.view']);
    }

    private function user(array $perms): User
    {
        $this->withoutMiddleware(EnsureAdminPanelAccess::class);
        foreach ($perms as $p) {
            Permission::findOrCreate($p, 'web');
        }

        return tap(User::factory()->create())->givePermissionTo($perms);
    }

    private function refundable(IntegrationAccount $acc, string $method, float $total = 300, string $tx = null): PaymentAttempt
    {
        $acc->forceFill(['refund_enabled_at' => now()])->save();
        $order = $this->makePendingOrder(User::factory()->create(), null, 2, $total / 2, $method);
        $order->forceFill(['integration_account_id' => $acc->id])->saveQuietly();
        $tx ??= (string) random_int(100000, 999999);

        $attempt = PaymentAttempt::create(['order_id' => $order->id, 'provider' => $acc->provider, 'method_code' => $method, 'amount' => $total, 'currency' => 'EGP', 'status' => 'paid',
            'transaction_id' => $tx, 'reference' => $order->order_number.'-r'.$tx, 'integration_account_id' => $acc->id, 'internal_reference' => PaymentAttempt::newInternalReference(),
            'paid_at' => now(), 'settled_at' => now()]);
        $order->forceFill(['status' => 'paid', 'paid_at' => now()])->saveQuietly();
        if ($acc->provider === 'paymob') {
            $this->paymobPays($attempt, 'KEY-'.$acc->name, $tx);
        }

        return $attempt->fresh();
    }

    private function request(PaymentAttempt $a, string $type = 'partial', ?string $amount = '100', ?User $by = null, string $key = null): PaymentRefund
    {
        return $this->svc->request($a, $by ?? $this->staff, $type, $amount, 'customer cancelled the event', $key ?? \Illuminate\Support\Str::uuid()->toString());
    }

    /* ---------------------------------------------------------------- capabilities */

    public function test_capabilities_per_provider_and_environment(): void
    {
        $fa = $this->account('fawaterak', 'FA');
        $fawaterak = $this->refundable($fa, 'fawaterak_card');
        $this->assertSame('manual', $this->svc->capability($fawaterak)->mode);
        $this->assertStringContainsString('no documented refund API', $this->svc->capability($fawaterak)->note);

        $acc = $this->account('paymob', 'A');
        $paymob = $this->refundable($acc, 'paymob_card');
        $this->assertSame('api', $this->svc->capability($paymob)->mode);

        config(['payments.refunds.live_api_enabled' => false]);
        $this->assertSame('manual', $this->svc->capability($paymob)->mode, 'API refunds are off until the environment flag is set');
        config(['payments.refunds.live_api_enabled' => true]);

        $acc->forceFill(['refund_enabled_at' => null])->save();
        $this->assertSame('manual', $this->svc->capability($paymob->fresh())->mode, 'not verified for this account');
        $acc->forceFill(['refund_enabled_at' => now(), 'enabled' => false])->save();
        $this->assertSame('manual', $this->svc->capability($paymob->fresh())->mode, 'a disabled account cannot send API refunds');
        $acc->forceFill(['enabled' => true])->save();

        $pending = PaymentAttempt::create(['order_id' => $paymob->order_id, 'provider' => 'paymob', 'method_code' => 'paymob_card', 'amount' => 10, 'currency' => 'EGP', 'status' => 'initiated']);
        $this->assertSame('none', $this->svc->capability($pending)->mode, 'only paid payments can be refunded');

        $legacy = PaymentAttempt::create(['order_id' => $paymob->order_id, 'provider' => 'paymob', 'method_code' => 'paymob_card', 'amount' => 10, 'currency' => 'EGP', 'status' => 'paid', 'transaction_id' => '777']);
        $this->assertSame('manual', $this->svc->capability($legacy)->mode);
        $this->assertStringContainsString('legacy', $this->svc->capability($legacy)->note);
    }

    /* ---------------------------------------------------------------- Paymob API */

    public function test_paymob_refund_is_sent_once_with_the_attempts_own_secret_and_integer_cents_and_confirmed_by_inquiry(): void
    {
        $A = $this->account('paymob', 'A');
        $B = $this->account('paymob', 'B');
        $a = $this->refundable($A, 'paymob_card', 300);
        $this->httpLog = [];

        $r = $this->request($a, 'partial', '100.50');
        $calls = array_values(array_filter($this->httpLog, fn ($l) => isset($l['refund_call'])));

        $this->assertCount(1, $calls);
        $this->assertSame('Token SECRET-A', $calls[0]['refund_call']['authorization'], 'the original merchant account\'s secret key');
        $this->assertSame(10050, $calls[0]['refund_call']['body']['amount_cents']);
        $this->assertSame((int) $a->transaction_id, $calls[0]['refund_call']['body']['transaction_id']);
        $this->assertNotContains('SECRET-B', array_map(fn ($l) => $l['refund_call']['authorization'] ?? '', $this->httpLog));
        $this->assertSame(['processing', 'api', $A->id], [$r->status, $r->mode, $r->integration_account_id]);
        $this->assertNotNull($r->provider_refund_id);

        // accepted != money returned: it only becomes succeeded through the refund transaction's own evidence
        $done = $this->svc->inquire($r);
        $this->assertSame('succeeded', $done->status);
        $this->assertNotNull($done->completed_at);
        $this->assertSame(10050, $this->svc->balance($a)['refunded']);
    }

    public function test_provider_rejection_timeout_and_server_errors_never_resend_automatically(): void
    {
        $A = $this->account('paymob', 'A');

        $a = $this->refundable($A, 'paymob_card');
        $this->paymobRefundMode = 'reject';
        $r = $this->request($a, 'full', null);
        $this->assertSame('rejected', $r->status);
        $this->assertStringContainsString('Amount exceeds', $r->failure_reason);
        $this->assertSame(30000, $this->svc->balance($a)['available'], 'rejected releases the balance');

        $this->paymobRefundMode = 'server_error';
        $r2 = $this->request($a, 'full', null);
        $this->assertSame('unknown', $r2->status);

        $this->paymobRefundMode = 'accept'; // the provider is healthy again, but nothing may be re-sent for the unknown refund
        $n = count(array_filter($this->httpLog, fn ($l) => isset($l['refund_call'])));
        $this->svc->submit($r2->fresh());
        $this->svc->inquire($r2->fresh());
        $this->assertSame($n, count(array_filter($this->httpLog, fn ($l) => isset($l['refund_call']))), 'no automatic re-send after an unknown outcome');
        $this->assertSame('unknown', $r2->fresh()->status, 'an inquiry that learns nothing changes nothing');

        // while unknown, the amount stays reserved and a new request is refused
        $this->expectException(ValidationException::class);
        $this->request($a, 'partial', '10');
    }

    public function test_connection_timeout_after_sending_is_unknown_and_resolved_only_by_a_person_or_evidence(): void
    {
        $A = $this->account('paymob', 'A');
        $a = $this->refundable($A, 'paymob_card');
        $this->paymobRefundMode = 'timeout';
        $r = $this->request($a, 'full', null);
        $this->assertSame('unknown', $r->status);
        $this->assertStringContainsString('may or may not', $r->failure_reason);

        $approver = $this->user(['payments.refunds.approve']);
        $done = $this->svc->confirmFailed($r, $approver, 'Paymob dashboard shows no refund');
        $this->assertSame('failed', $done->status);
        $this->assertSame(30000, $this->svc->balance($a)['available']);
        $retry = $this->request($a->fresh(), 'full', null); // a NEW, explicit request (still timing out here)
        $this->assertNotSame($r->id, $retry->id);
    }

    public function test_void_is_card_only_and_confirmed_by_the_voided_flag(): void
    {
        $A = $this->account('paymob', 'A');
        $a = $this->refundable($A, 'paymob_card');
        $r = $this->request($a, 'void', null);
        $this->assertSame('processing', $r->status);
        $this->assertSame('succeeded', $this->svc->inquire($r)->status);

        $wallet = $this->account('paymob', 'W');
        $this->makeMethod('paymob', 'paymob_wallet', ['api_key' => 'x', 'iframe_id' => '1', 'integration_id' => '1']);
        $w = $this->refundable($wallet, 'paymob_wallet');
        $this->expectException(ValidationException::class);
        $this->request($w, 'void', null);
    }

    public function test_reassignment_and_agent_account_changes_never_move_a_refund_to_another_merchant(): void
    {
        $A = $this->account('paymob', 'A');
        $B = $this->account('paymob', 'B');
        $a = $this->refundable($A, 'paymob_card');
        $newAgent = User::factory()->create();
        $newAgent->forceFill(['payment_routing_mode' => 'custom', 'payment_account_id' => $B->id, 'payment_routing_enabled' => true])->save();
        $a->order->forceFill(['assigned_user_id' => $newAgent->id])->saveQuietly();
        SystemSettings::set('payment_routing_enabled', true);
        $this->httpLog = [];

        $r = $this->request($a, 'partial', '50');
        $this->assertSame($A->id, $r->integration_account_id);
        $auths = array_map(fn ($l) => $l['refund_call']['authorization'] ?? null, array_filter($this->httpLog, fn ($l) => isset($l['refund_call'])));
        $this->assertSame(['Token SECRET-A'], array_values($auths));
    }

    /* ---------------------------------------------------------------- balance, idempotency, minor units */

    public function test_balance_rules_over_refund_in_flight_and_multiple_partials(): void
    {
        $acc = $this->account('fake', 'F');
        $a = $this->refundable($acc, 'fake_card', 300);

        $first = $this->request($a, 'partial', '100');                    // fake: accepted => processing (in flight)
        try {
            $this->request($a, 'partial', '10');
            $this->fail('second refund while one is in flight');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('still being processed', $e->errors()['refund'][0]);
        }

        $first->forceFill(['meta' => array_merge($first->meta, ['fake_refund_state' => 'succeeded'])])->save();
        $this->svc->inquire($first->fresh());
        $second = $this->request($a, 'partial', '150');
        $second->forceFill(['meta' => array_merge($second->meta, ['fake_refund_state' => 'succeeded'])])->save();
        $this->svc->inquire($second->fresh());

        $bal = $this->svc->balance($a);
        $this->assertSame([30000, 25000, 25000, 5000], [$bal['captured'], $bal['held'], $bal['refunded'], $bal['available']]);

        foreach (['50.01', '1000'] as $tooMuch) {
            try {
                $this->request($a, 'partial', $tooMuch);
                $this->fail("over-refund $tooMuch");
            } catch (ValidationException $e) {
                $this->assertStringContainsString('exceeds', $e->errors()['refund'][0]);
            }
        }
        $this->assertSame(2, PaymentRefund::count());
        try {
            $this->request($a, 'full', null);
            $this->fail('full refund after a partial one');
        } catch (ValidationException) {
        }
    }

    public function test_minor_units_are_exact_and_malformed_amounts_are_refused(): void
    {
        $this->assertSame(10, PaymentRefund::decimalToMinor('0.1'));
        $this->assertSame(30, PaymentRefund::decimalToMinor('0.30'));
        $this->assertSame(1999, PaymentRefund::decimalToMinor('19.99'));
        $this->assertSame('19.99', PaymentRefund::minorToDecimal(1999));
        $this->assertSame('0.05', PaymentRefund::minorToDecimal(5));
        foreach (['-5', '1e3', '10.999', 'abc', '', '1,5', '0'] as $bad) {
            $r = PaymentRefund::decimalToMinor($bad);
            $this->assertTrue($r === null || $r === 0, "'$bad' must not be a valid amount");
        }

        $acc = $this->account('fake', 'F');
        $a = $this->refundable($acc, 'fake_card', 0.30);
        $r = $this->request($a, 'partial', '0.10');
        $this->assertSame(10, $r->amount_minor);
    }

    public function test_double_submit_with_the_same_key_creates_one_refund_and_one_provider_call(): void
    {
        $A = $this->account('paymob', 'A');
        $a = $this->refundable($A, 'paymob_card');
        $this->httpLog = [];

        $one = $this->request($a, 'partial', '100', null, 'form-key-1');
        $two = $this->request($a, 'partial', '100', null, 'form-key-1');

        $this->assertSame($one->id, $two->id);
        $this->assertSame(1, PaymentRefund::count());
        $this->assertCount(1, array_filter($this->httpLog, fn ($l) => isset($l['refund_call'])));
    }

    public function test_refund_never_changes_order_status_or_tickets_and_late_signals_never_overwrite_a_final_state(): void
    {
        $acc = $this->account('fake', 'F');
        $a = $this->refundable($acc, 'fake_card');
        $a->order->forceFill(['status' => 'paid'])->saveQuietly();
        $tickets = IssuedTicket::where('order_id', $a->order_id)->count();

        $r = $this->request($a, 'full', null);
        $r->forceFill(['meta' => array_merge($r->meta, ['fake_refund_state' => 'succeeded'])])->save();
        $this->svc->inquire($r->fresh());

        $this->assertSame('paid', $a->order->fresh()->status);
        $this->assertSame($tickets, IssuedTicket::where('order_id', $a->order_id)->count());

        $r = $r->fresh();
        $r->forceFill(['meta' => array_merge($r->meta, ['fake_refund_state' => 'failed'])])->save();
        $this->svc->inquire($r->fresh()); // processing only: succeeded is final, inquire() ignores finals
        $this->assertSame('succeeded', $r->fresh()->status);
    }

    /* ---------------------------------------------------------------- approval */

    public function test_approve_flow_by_a_second_person(): void
    {
        SystemSettings::set('refund_approval_required', true);
        $A = $this->account('paymob', 'A');
        $a = $this->refundable($A, 'paymob_card');
        $r = $this->request($a, 'partial', '100');
        $approver = $this->user(['payments.refunds.approve']);

        $sameUser = $this->user(['payments.refunds.create', 'payments.refunds.approve']);
        try {
            $mine = PaymentRefund::create(['internal_reference' => 'REF-SELF', 'payment_attempt_id' => $a->id, 'order_id' => $a->order_id, 'provider' => 'paymob', 'mode' => 'api', 'type' => 'partial',
                'status' => 'requested', 'awaiting_approval' => true, 'amount_minor' => 100, 'reason' => 'x', 'idempotency_key' => 'k2', 'requested_by' => $sameUser->id, 'meta' => ['timeline' => []]]);
            $this->svc->approve($mine, $sameUser);
            $this->fail('self approval');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('someone other', $e->errors()['refund'][0]);
        }
        $mine->delete();

        $this->httpLog = [];
        $done = $this->svc->approve($r, $approver);
        $this->assertSame('processing', $done->status);
        $this->assertSame($approver->id, $done->approved_by);
        $this->assertCount(1, array_filter($this->httpLog, fn ($l) => isset($l['refund_call'])));
    }

    /* ---------------------------------------------------------------- manual / external (Fawaterak) */

    public function test_manual_workflow_requires_evidence_and_the_fawaterak_refund_webhook_is_only_a_hint(): void
    {
        $fa = $this->account('fawaterak', 'FA', [], ['vendor_key' => 'V']);
        $a = $this->refundable($fa, 'fawaterak_card');
        $a->forceFill(['transaction_id' => 'INV-77'])->saveQuietly();
        $this->httpLog = [];

        $r = $this->request($a->fresh(), 'partial', '120');
        $this->assertSame(['manual', 'requested'], [$r->mode, $r->status]);
        $this->assertEmpty($this->httpLog, 'nothing is sent to Fawaterak: no refund API is documented');

        $this->postJson(route('front.fawaterak.webhook'), ['transactionId' => 'INV-77', 'amount' => '120', 'currency' => 'EGP', 'status' => 'approved', 'reason' => 'x', 'approvedAt' => '2026-10-16'])->assertOk();
        $this->assertSame('requested', $r->fresh()->status, 'an unauthenticated "approved" notification does not complete the refund');
        $this->assertTrue($r->fresh()->meta['provider_reported_approval']);
        $this->assertSame(0, $this->svc->balance($a)['refunded']);

        $approver = $this->user(['payments.refunds.approve']);
        try {
            $this->svc->confirmSucceeded($r, $approver, '', 'verified in portal');
            $this->fail('reference required');
        } catch (ValidationException) {
        }
        try {
            $this->svc->confirmSucceeded($r, $approver, 'FW-REF-1', 'x');
            $this->fail('note required');
        } catch (ValidationException) {
        }

        $this->svc->markSubmitted($r, $approver);
        $done = $this->svc->confirmSucceeded($r->fresh(), $approver, 'FW-REF-1', 'portal screenshot 4411');
        $this->assertSame(['succeeded', 'FW-REF-1'], [$done->status, $done->provider_reference]);
        $this->assertSame(12000, $this->svc->balance($a)['refunded']);
        $this->assertTrue(collect($done->timeline())->pluck('event')->contains('confirmed_by_staff'));
    }

    /* ---------------------------------------------------------------- Paymob refund webhook */

    public function test_paymob_refund_webhook_triggers_an_inquiry_only_when_authentic(): void
    {
        $A = $this->account('paymob', 'A');
        $a = $this->refundable($A, 'paymob_card');
        $r = $this->request($a, 'partial', '100');

        $obj = ['id' => $r->provider_refund_id, 'success' => true, 'pending' => false, 'amount_cents' => 10000, 'currency' => 'EGP', 'is_refunded' => false, 'is_voided' => false, 'has_parent_transaction' => true,
            'order' => ['id' => 5, 'merchant_order_id' => $a->reference]];

        $this->postJson(route('front.paymob.callback').'?hmac=wrong', ['obj' => $obj])->assertStatus(403);
        $this->assertSame('processing', $r->fresh()->status);

        $this->postJson(route('front.paymob.callback').'?hmac='.$this->paymobSign($obj, 'HMAC-A'), ['obj' => $obj])->assertOk();
        $this->assertSame('succeeded', $r->fresh()->status);
        $e = WebhookEvent::where('event_type', 'refund')->where('signature_status', 'valid')->firstOrFail();
        $this->assertSame('processed', $e->processing_status);
        $this->assertSame('paid', $a->order->fresh()->status, 'payment state is untouched by the refund event');

        $this->postJson(route('front.paymob.callback').'?hmac='.$this->paymobSign($obj, 'HMAC-A'), ['obj' => $obj])->assertOk(); // duplicate
        $this->assertSame(1, PaymentRefund::count());
        $this->assertSame('succeeded', $r->fresh()->status);
    }

    /* ---------------------------------------------------------------- permissions and scoping */

    public function test_http_permissions_scoping_and_confirmation_checkbox(): void
    {
        $acc = $this->account('fake', 'F');
        $a = $this->refundable($acc, 'fake_card');
        $payload = ['type' => 'partial', 'amount' => '50', 'reason' => 'because of reasons', 'idempotency_key' => 'abc', 'confirm' => 1];

        $viewer = $this->user(['payments.refunds.view', 'payments.transactions.view', 'orders.view']);
        $this->actingAs($viewer)->post(route('admin.payments.refunds.store', $a), $payload)->assertForbidden();

        $this->actingAs($this->staff)->post(route('admin.payments.refunds.store', $a), ['confirm' => null] + $payload)->assertSessionHasErrors('confirm');
        $this->assertSame(0, PaymentRefund::count());
        $this->actingAs($this->staff)->post(route('admin.payments.refunds.store', $a), $payload)->assertRedirect();
        $r = PaymentRefund::firstOrFail();
        $this->actingAs($this->staff)->get(route('admin.payments.refunds.show', $r))->assertOk()->assertSee($r->internal_reference);
        $this->actingAs($this->staff)->get(route('admin.orders.show', $a->order))->assertOk()->assertSee('Refund History')->assertSee($r->internal_reference);

        // an assignment-scoped agent cannot touch a refund on someone else's order
        SystemSettings::set('order_assignment_enabled', true);
        $agent = User::factory()->create();
        $agent->assignRole('Order Agent');
        $agent->givePermissionTo(['payments.refunds.create', 'payments.refunds.view', 'payments.transactions.view', 'payments.refunds.approve']);
        $this->actingAs($agent)->get(route('admin.payments.refunds.show', $r))->assertNotFound();
        $this->actingAs($agent)->post(route('admin.payments.transactions.refund-dummy' === '' ? 'admin.payments.refunds.store' : 'admin.payments.refunds.store', $a), $payload)->assertNotFound();
        $this->actingAs($agent)->post(route('admin.payments.refunds.check', $r))->assertNotFound();
    }

    public function test_the_refund_button_exists_only_when_it_can_really_work(): void
    {
        $acc = $this->account('fake', 'F');
        $a = $this->refundable($acc, 'fake_card');
        $page = fn (User $u) => $this->actingAs($u)->get(route('admin.orders.show', $a->order))->assertOk();

        $page($this->staff)->assertSee('Refund payment')->assertSee('I confirm this refund');
        $page($this->user(['orders.view', 'payments.transactions.view']))->assertDontSee('Refund payment');

        $fa = $this->account('fawaterak', 'FA');
        $f = $this->refundable($fa, 'fawaterak_card');
        $this->actingAs($this->staff)->get(route('admin.orders.show', $f->order))->assertOk()->assertSee('Record manual refund')->assertSee('no documented refund API')->assertDontSee('>Refund<', false);
    }

    public function test_legacy_orders_without_attempts_or_accounts_still_render_and_can_use_the_manual_workflow(): void
    {
        $order = $this->makePendingOrder(User::factory()->create(), null, 2, 100, 'paymob_card');
        $legacy = PaymentAttempt::create(['order_id' => $order->id, 'provider' => 'paymob', 'method_code' => 'paymob_card', 'amount' => 200, 'currency' => 'EGP', 'status' => 'paid', 'transaction_id' => '555001', 'paid_at' => now()]);

        $this->actingAs($this->staff)->get(route('admin.orders.show', $order))->assertOk()->assertSee('Record manual refund');
        $r = $this->request($legacy, 'partial', '50');
        $this->assertSame(['manual', null], [$r->mode, $r->integration_account_id]);
    }
}
