<?php

namespace Tests\Feature;

use App\Http\Middleware\EnsureAdminPanelAccess;
use App\Models\IntegrationAccount;
use App\Models\Order;
use App\Models\PaymentAttempt;
use App\Models\PaymentMethod;
use App\Models\User;
use App\Models\WebhookEvent;
use App\Services\Payments\PaymentService;
use App\Support\SystemSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Spatie\Permission\Models\Permission;
use Tests\Support\BuildsMerchantAccounts;
use Tests\Support\BuildsOrders;
use Tests\TestCase;

class WebhookTrackingTest extends TestCase
{
    use BuildsMerchantAccounts, BuildsOrders, RefreshDatabase;

    private User $customer;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        $this->fakeProviders();
        $this->customer = User::factory()->create();
        $this->makeMethod('paymob', 'paymob_card', ['api_key' => 'L', 'iframe_id' => '1', 'integration_id' => '1']);
        $this->makeMethod('fawaterak', 'fawaterak_card', ['api_key' => 'L', 'provider_key' => 'FAWATERAK.1']);
        config(['payments.fawaterak.verified' => true]);
    }

    private function attempt(IntegrationAccount $acc, string $method): PaymentAttempt
    {
        $order = $this->makePendingOrder($this->customer, null, 2, 150, $method);
        $order->forceFill(['integration_account_id' => $acc->id])->saveQuietly();
        app(PaymentService::class)->start($order->fresh(), PaymentMethod::where('code', $method)->first());

        return $order->paymentAttempts()->firstOrFail();
    }

    private function fawHash(array $p, string $key): string
    {
        return hash_hmac('sha256', 'InvoiceId='.$p['invoice_id'].'&InvoiceKey='.$p['invoice_key'].'&PaymentMethod='.$p['payment_method'], $key);
    }

    /* ------------------------------------------------------------ references on attempts */

    public function test_new_attempts_carry_our_reference_separate_from_provider_ids_and_timestamps(): void
    {
        $acc = $this->account('paymob', 'A');
        $a = $this->attempt($acc, 'paymob_card');

        $this->assertMatchesRegularExpression('/^PAY-[A-Z0-9]{10}$/', $a->internal_reference);
        $this->assertNotSame($a->internal_reference, $a->order->order_number);
        $this->assertNotEmpty($a->provider_order_id, 'Paymob order id stored');
        $this->assertNotEmpty($a->reference, 'merchant_order_id sent to Paymob');

        $this->paymobPays($a, 'KEY-A', 'T100');
        app(PaymentService::class)->refreshAttempt($a->fresh(), 'T100');
        $a->refresh();
        $this->assertNotNull($a->paid_at);
        $this->assertNotNull($a->last_checked_at);
        $this->assertSame('T100', $a->transaction_id);
    }

    public function test_a_retry_creates_a_new_attempt_and_never_rewrites_the_old_one(): void
    {
        $acc = $this->account('paymob', 'A');
        $first = $this->attempt($acc, 'paymob_card');
        $first->forceFill(['status' => 'failed', 'failure_reason' => 'declined'])->save();
        $order = $first->order;
        app(PaymentService::class)->start($order->fresh(), PaymentMethod::where('code', 'paymob_card')->first());

        $this->assertSame(2, $order->paymentAttempts()->count());
        $this->assertSame('failed', $first->fresh()->status);
        $this->assertSame('declined', $first->fresh()->failure_reason);
        $this->assertNotSame($first->fresh()->internal_reference, $order->paymentAttempts()->latest('id')->first()->internal_reference);
    }

    /* ------------------------------------------------------------ Paymob events */

    public function test_paymob_events_are_recorded_authenticated_deduplicated_and_free_of_card_data(): void
    {
        $acc = $this->account('paymob', 'A');
        $a = $this->attempt($acc, 'paymob_card');
        $this->paymobPays($a, 'KEY-A', 'E1');
        $obj = $this->paymobCallbackObj($a, 'E1') + ['source_data' => ['pan' => '2346', 'sub_type' => 'MasterCard', 'type' => 'card'], 'created_at' => '2026-10-16T10:00:00'];

        for ($i = 0; $i < 3; $i++) {
            $this->postJson(route('front.paymob.callback').'?hmac='.$this->paymobSign($obj, 'HMAC-A'), ['obj' => $obj])->assertOk();
        }

        $this->assertSame(1, WebhookEvent::count());
        $e = WebhookEvent::first();
        $this->assertSame([3, 'valid', 'processed', 'payment.succeeded', $acc->id, $a->id], [$e->deliveries, $e->signature_status, $e->processing_status, $e->event_type, $e->integration_account_id, $e->payment_attempt_id]);
        $this->assertArrayNotHasKey('pan', $e->payload);
        $this->assertStringNotContainsString('MasterCard', json_encode($e->payload));
        $raw = \DB::table('webhook_events')->value('payload');
        $this->assertNull(json_decode((string) $raw, true), 'payload is encrypted at rest, not JSON');
        $this->assertSame('paid', $a->order->fresh()->status);
    }

    public function test_invalid_signature_is_refused_and_logged_as_such(): void
    {
        $acc = $this->account('paymob', 'A');
        $a = $this->attempt($acc, 'paymob_card');
        $this->paymobPays($a, 'KEY-A', 'E2');
        $obj = $this->paymobCallbackObj($a, 'E2');

        $this->postJson(route('front.paymob.callback').'?hmac=bad', ['obj' => $obj])->assertStatus(403);
        $e = WebhookEvent::firstOrFail();
        $this->assertSame(['invalid', 'failed'], [$e->signature_status, $e->processing_status]);
        $this->assertSame('pending_payment', $a->order->fresh()->status);

        // an invalid delivery can be retried from the admin only when the signature was not invalid
        $this->expectException(\DomainException::class);
        app(\App\Services\Payments\Webhooks\WebhookService::class)->retry($e);
    }

    public function test_unknown_reference_wrong_amount_and_late_events_are_handled_without_money_effects(): void
    {
        $acc = $this->account('paymob', 'A');
        $a = $this->attempt($acc, 'paymob_card');

        // unknown merchant order id
        $ghost = ['id' => 'G1', 'success' => true, 'pending' => false, 'amount_cents' => 100, 'currency' => 'EGP', 'order' => ['id' => 1, 'merchant_order_id' => 'NOPE-123']];
        $this->postJson(route('front.paymob.callback'), ['obj' => $ghost])->assertOk()->assertJson(['updated' => false]);
        $this->assertSame('ignored', WebhookEvent::where('transaction_reference', 'G1')->value('processing_status'));

        // wrong amount reported by the provider inquiry
        $this->paymobPays($a, 'KEY-A', 'W1', ['amount_cents' => 100]);
        $obj = $this->paymobCallbackObj($a, 'W1');
        $this->postJson(route('front.paymob.callback').'?hmac='.$this->paymobSign($obj, 'HMAC-A'), ['obj' => $obj])->assertOk()->assertJson(['updated' => false]);
        $this->assertSame('pending_payment', $a->order->fresh()->status);

        // late event on a cancelled order: recorded, flagged for review, nothing fulfilled
        $b = $this->attempt($acc, 'paymob_card');
        $b->order->forceFill(['status' => 'canceled'])->saveQuietly();
        $this->paymobPays($b, 'KEY-A', 'L1');
        $o2 = $this->paymobCallbackObj($b, 'L1');
        $this->postJson(route('front.paymob.callback').'?hmac='.$this->paymobSign($o2, 'HMAC-A'), ['obj' => $o2])->assertOk();
        $this->assertSame('order_closed', $b->fresh()->meta['needs_review']);
        $this->assertSame(0, \App\Models\IssuedTicket::where('order_id', $b->order_id)->count());
    }

    /* ------------------------------------------------------------ Fawaterak events */

    public function test_fawaterak_paid_webhook_only_triggers_the_inquiry_and_checks_the_hash(): void
    {
        $acc = $this->account('fawaterak', 'FA', [], ['vendor_key' => 'VENDOR-A']);
        $a = $this->attempt($acc, 'fawaterak_card');
        $p = ['invoice_id' => $a->transaction_id, 'invoice_key' => 'IK1', 'payment_method' => 'Card', 'invoice_status' => 'paid', 'referenceNumber' => 'REF-9', 'pay_load' => null];

        // wrong key => 403, nothing happens
        $this->postJson(route('front.fawaterak.webhook'), $p + ['hashKey' => $this->fawHash($p, 'OTHER')])->assertStatus(403);
        $this->assertSame('pending_payment', $a->order->fresh()->status);

        // correct hash BUT the provider says unpaid => the webhook alone cannot pay the order
        $this->postJson(route('front.fawaterak.webhook'), $p + ['hashKey' => $this->fawHash($p, 'VENDOR-A')])->assertOk();
        $this->assertSame('pending_payment', $a->order->fresh()->status);

        // provider confirms via inquiry => settled; duplicates are harmless
        $this->fawInvoices[$a->transaction_id]['paid'] = true;
        for ($i = 0; $i < 2; $i++) {
            $this->postJson(route('front.fawaterak.webhook'), $p + ['hashKey' => $this->fawHash($p, 'VENDOR-A')])->assertOk()->assertJson(['authenticated' => true]);
        }
        $this->assertSame('paid', $a->order->fresh()->status);
        $this->assertSame('REF-9', $a->fresh()->provider_reference);
        $this->assertSame(2, \App\Models\IssuedTicket::where('order_id', $a->order_id)->count());
        $this->assertSame(1, WebhookEvent::where('signature_status', 'valid')->count());
    }

    public function test_fawaterak_wrong_merchant_account_cannot_validate_or_settle(): void
    {
        $A = $this->account('fawaterak', 'FA', [], ['vendor_key' => 'VENDOR-A']);
        $B = $this->account('fawaterak', 'FB', [], ['vendor_key' => 'VENDOR-B']);
        $a = $this->attempt($A, 'fawaterak_card');
        $p = ['invoice_id' => $a->transaction_id, 'invoice_key' => 'IK', 'payment_method' => 'Card', 'invoice_status' => 'paid'];

        $this->fawInvoices[$a->transaction_id]['paid'] = true;
        $this->postJson(route('front.fawaterak.webhook'), $p + ['hashKey' => $this->fawHash($p, 'VENDOR-B')])->assertStatus(403);
        $this->assertSame('pending_payment', $a->order->fresh()->status);
    }

    public function test_fawaterak_failed_cancel_and_refund_events_are_stored_without_money_effects(): void
    {
        $acc = $this->account('fawaterak', 'FA', [], ['vendor_key' => 'VENDOR-A']);
        $a = $this->attempt($acc, 'fawaterak_card');

        $this->postJson(route('front.fawaterak.webhook'), ['invoice_id' => $a->transaction_id, 'invoice_key' => 'IK', 'payment_method' => 'Card', 'amount' => 300, 'paidCurrency' => 'EGP', 'errorMessage' => '3DS failed', 'response' => ['gatewayCode' => 'X'], 'referenceNumber' => 'R1'])->assertOk();
        $this->postJson(route('front.fawaterak.webhook'), ['transactionId' => $a->transaction_id, 'amount' => '300', 'currency' => 'EGP', 'status' => 'approved', 'reason' => 'x', 'approvedAt' => '2026-10-16'])->assertOk();

        $types = WebhookEvent::pluck('signature_status', 'event_type')->all();
        $this->assertSame('unsupported', $types['payment.failed']);
        $this->assertSame('unsupported', $types['refund']);
        $this->assertSame('pending_payment', $a->order->fresh()->status, 'unauthenticated events never move money state');
        $this->assertSame('initiated', $a->fresh()->status);
    }

    public function test_unmatched_fawaterak_events_are_kept_for_review(): void
    {
        $this->postJson(route('front.fawaterak.webhook'), ['invoice_id' => 'UNKNOWN', 'invoice_key' => 'k', 'payment_method' => 'Card', 'invoice_status' => 'paid'])->assertOk()->assertJson(['matched' => false]);
        $this->assertSame('needs_review', WebhookEvent::firstOrFail()->processing_status);
    }

    /* ------------------------------------------------------------ admin */

    private function staff(array $perms): User
    {
        $this->withoutMiddleware(EnsureAdminPanelAccess::class);
        foreach ($perms as $p) {
            Permission::findOrCreate($p, 'web');
        }

        return tap(User::factory()->create())->givePermissionTo($perms);
    }

    public function test_webhook_page_separates_configured_from_verified_and_retry_is_idempotent(): void
    {
        $acc = $this->account('paymob', 'A');
        $a = $this->attempt($acc, 'paymob_card');
        $viewer = $this->staff(['payments.webhooks.view', 'payments.transactions.inquire', 'payments.accounts.manage', 'payments.accounts.view']);

        $this->actingAs($viewer)->get(route('admin.payments.webhooks'))->assertOk()->assertSee(route('front.paymob.callback'))->assertSee(route('front.fawaterak.webhook'))->assertSee('Not confirmed')->assertSee('Not verified yet');
        $this->actingAs($viewer)->post(route('admin.payments.webhooks.configured', $acc))->assertRedirect();
        $page = $this->actingAs($viewer)->get(route('admin.payments.webhooks'))->assertOk()->assertSee('Configured')->assertSee('Not verified yet');

        $this->paymobPays($a, 'KEY-A', 'RT1');
        $obj = $this->paymobCallbackObj($a, 'RT1');
        $this->postJson(route('front.paymob.callback').'?hmac='.$this->paymobSign($obj, 'HMAC-A'), ['obj' => $obj])->assertOk();
        $this->actingAs($viewer)->get(route('admin.payments.webhooks'))->assertSee('Verified');

        $e = WebhookEvent::firstOrFail();
        $e->update(['processing_status' => 'failed']);
        $this->actingAs($viewer)->post(route('admin.payments.webhooks.retry', $e))->assertRedirect();
        $this->actingAs($viewer)->post(route('admin.payments.webhooks.retry', $e))->assertRedirect();
        $this->assertSame('processed', $e->fresh()->processing_status);
        $this->assertSame(2, \App\Models\IssuedTicket::where('order_id', $a->order_id)->count(), 'retry never issues tickets twice');
        $this->actingAs($viewer)->get(route('admin.payments.webhooks'))->assertDontSee('HMAC-A')->assertDontSee('KEY-A');
    }

    public function test_transactions_screens_respect_permissions_scope_and_hide_accounts_from_unauthorised_staff(): void
    {
        $acc = $this->account('paymob', 'Secretive Account');
        $a = $this->attempt($acc, 'paymob_card');

        $plain = $this->staff(['payments.transactions.view', 'orders.view']);
        $this->actingAs($plain)->get(route('admin.payments.transactions'))->assertOk()->assertSee($a->internal_reference)->assertDontSee('Secretive Account');
        $this->actingAs($plain)->get(route('admin.payments.transactions.show', $a))->assertOk()->assertDontSee('Secretive Account');
        $this->actingAs($plain)->post(route('admin.payments.transactions.inquire', $a))->assertForbidden();

        $admin = $this->staff(['payments.transactions.view', 'payments.transactions.inquire', 'payments.accounts.view', 'orders.view']);
        $this->actingAs($admin)->get(route('admin.payments.transactions.show', $a))->assertOk()->assertSee('Secretive Account');
        $this->paymobPays($a, 'KEY-Secretive Account', 'CK1');
        $a->forceFill(['transaction_id' => 'CK1'])->saveQuietly();
        $r = $this->actingAs($admin)->post(route('admin.payments.transactions.inquire', $a));
        $r->assertRedirect();
        $this->assertSame('paid', $a->order->fresh()->status);

        $nobody = $this->staff(['orders.view']);
        $this->actingAs($nobody)->get(route('admin.payments.webhooks'))->assertForbidden();
    }

    public function test_assignment_scoping_applies_to_transactions(): void
    {
        SystemSettings::set('order_assignment_enabled', true);
        $acc = $this->account('paymob', 'A');
        $mine = $this->attempt($acc, 'paymob_card');
        $theirs = $this->attempt($acc, 'paymob_card');
        $agent = User::factory()->create();
        $agent->assignRole('Order Agent');
        $agent->givePermissionTo(Permission::findOrCreate('payments.transactions.view', 'web'));
        $mine->order->forceFill(['assigned_user_id' => $agent->id])->saveQuietly();
        $this->withoutMiddleware(EnsureAdminPanelAccess::class);

        $this->actingAs($agent)->get(route('admin.payments.transactions'))->assertOk()->assertSee($mine->internal_reference)->assertDontSee($theirs->internal_reference);
        $this->actingAs($agent)->get(route('admin.payments.transactions.show', $theirs))->assertNotFound();
    }
}
