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

    public function test_two_fawaterak_accounts_share_one_webhook_url_and_each_is_verified_with_its_own_vendor_key(): void
    {
        $A = $this->account('fawaterak', 'FA', [], ['vendor_key' => 'VENDOR-A']);
        $B = $this->account('fawaterak', 'FB', [], ['vendor_key' => 'VENDOR-B']);
        $a = $this->attempt($A, 'fawaterak_card');
        $b = $this->attempt($B, 'fawaterak_card');
        $this->fawInvoices[$a->transaction_id]['paid'] = true;
        $this->fawInvoices[$b->transaction_id]['paid'] = true;

        $pa = ['invoice_id' => $a->transaction_id, 'invoice_key' => 'IKA', 'payment_method' => 'Card', 'invoice_status' => 'paid'];
        $pb = ['invoice_id' => $b->transaction_id, 'invoice_key' => 'IKB', 'payment_method' => 'Card', 'invoice_status' => 'paid'];

        $this->postJson(route('front.fawaterak.webhook'), $pa + ['hashKey' => $this->fawHash($pa, 'VENDOR-A')])->assertOk()->assertJson(['authenticated' => true]);
        $this->postJson(route('front.fawaterak.webhook'), $pb + ['hashKey' => $this->fawHash($pb, 'VENDOR-B')])->assertOk()->assertJson(['authenticated' => true]);

        $this->assertSame('paid', $a->order->fresh()->status);
        $this->assertSame('paid', $b->order->fresh()->status);
        $this->assertSame($A->id, WebhookEvent::where('transaction_reference', $a->transaction_id)->value('integration_account_id'));
        $this->assertSame($B->id, WebhookEvent::where('transaction_reference', $b->transaction_id)->value('integration_account_id'));
    }

    public function test_fawaterak_paid_webhook_without_a_hash_is_rejected_when_a_vendor_key_is_stored(): void
    {
        $acc = $this->account('fawaterak', 'FA', [], ['vendor_key' => 'VENDOR-A']);
        $a = $this->attempt($acc, 'fawaterak_card');
        $this->fawInvoices[$a->transaction_id]['paid'] = true;

        $this->postJson(route('front.fawaterak.webhook'), ['invoice_id' => $a->transaction_id, 'invoice_key' => 'IK', 'payment_method' => 'Card', 'invoice_status' => 'paid'])->assertStatus(403);

        $this->assertSame('pending_payment', $a->order->fresh()->status);
    }

    public function test_fawaterak_cancel_webhook_checks_its_hash_and_never_pays_the_order(): void
    {
        $acc = $this->account('fawaterak', 'FA', [], ['vendor_key' => 'VENDOR-A']);
        $a = $this->attempt($acc, 'fawaterak_card');
        PaymentAttempt::whereKey($a->id)->update(['provider_reference' => 'FAW-REF-1']);
        $p = ['referenceId' => 'FAW-REF-1', 'paymentMethod' => 'Fawry', 'status' => 'EXPIRED'];
        $hash = fn (string $key) => hash_hmac('sha256', 'referenceId=FAW-REF-1&PaymentMethod=Fawry', $key);

        $this->postJson(route('front.fawaterak.webhook'), $p + ['hashKey' => $hash('WRONG')])->assertStatus(403);
        $this->postJson(route('front.fawaterak.webhook'), $p + ['hashKey' => $hash('VENDOR-A')])->assertOk()->assertJson(['authenticated' => true]);

        $this->assertSame('valid', WebhookEvent::where('event_type', 'payment.cancelled')->latest('id')->value('signature_status'));
        $this->assertNotSame('paid', $a->order->fresh()->status);
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

    public function test_a_failure_reported_only_by_webhook_reads_failed_on_every_screen_but_never_for_a_paid_attempt(): void
    {
        $acc = $this->account('fawaterak', 'FA', [], ['vendor_key' => 'VENDOR-A']);
        $a = $this->attempt($acc, 'fawaterak_card');
        $viewer = $this->staff(['payments.transactions.view', 'orders.view', 'payments.reports.view']);

        $this->postJson(route('front.fawaterak.webhook'), ['invoice_id' => $a->transaction_id, 'invoice_key' => 'IK', 'payment_method' => 'Card', 'amount' => 300, 'errorMessage' => '3DS failed', 'response' => ['gatewayCode' => 'X']])->assertOk();
        $this->assertSame('initiated', $a->fresh()->status, 'the stored attempt is untouched');
        $this->assertSame('failed', $a->fresh()->displayStatus());

        $this->actingAs($viewer)->get(route('admin.payments.transactions.show', $a))->assertOk()->assertSee('<dt>Status</dt><dd><span class="a-badge a-badge--danger a-badge--dot">Failed</span>', false);
        $this->actingAs($viewer)->get(route('admin.payments.transactions'))->assertOk()->assertSee('a-badge--danger', false)->assertSee('Failed');
        $this->actingAs($viewer)->get(route('admin.orders.show', $a->order))->assertOk()->assertSee('Failed');

        // a payment that went through afterwards is paid, whatever an earlier webhook said
        $a->forceFill(['status' => 'paid'])->saveQuietly();
        $this->assertSame('paid', $a->fresh()->displayStatus());
    }

    public function test_when_the_provider_does_not_confirm_a_payment_its_answer_is_kept_and_shown(): void
    {
        $acc = $this->account('fawaterak', 'FA', [], ['vendor_key' => 'VENDOR-A']);
        $a = $this->attempt($acc, 'fawaterak_card');
        $viewer = $this->staff(['payments.transactions.view', 'orders.view']);
        $p = ['invoice_id' => $a->transaction_id, 'invoice_key' => 'IK1', 'payment_method' => 'Card', 'invoice_status' => 'paid'];

        // a valid paid webhook, but the provider's own record still says unpaid => nothing is settled, and we remember what it said
        $this->postJson(route('front.fawaterak.webhook'), $p + ['hashKey' => $this->fawHash($p, 'VENDOR-A')])->assertOk();

        $this->assertSame('pending_payment', $a->order->fresh()->status);
        $li = $a->fresh()->meta['last_inquiry'] ?? null;
        $this->assertNotNull($li);
        $this->assertSame('pending', $li['result']);
        $this->assertArrayHasKey('data_fields', $li);
        $this->actingAs($viewer)->get(route('admin.payments.transactions.show', $a))->assertOk()->assertSee('Provider answer')->assertSee('fields:');

        // once the provider confirms, the attempt is paid and the note is not shown any more
        $this->fawInvoices[$a->transaction_id]['paid'] = true;
        $this->postJson(route('front.fawaterak.webhook'), $p + ['hashKey' => $this->fawHash($p, 'VENDOR-A')])->assertOk();
        $this->assertSame('paid', $a->order->fresh()->status);
        $this->actingAs($viewer)->get(route('admin.payments.transactions.show', $a))->assertOk()->assertDontSee('Provider answer');
    }

    public function test_the_invoice_inquiry_sends_the_json_content_type_fawaterak_requires(): void
    {
        $acc = $this->account('fawaterak', 'FA');
        $a = $this->attempt($acc, 'fawaterak_card');
        $this->fawInvoices[$a->transaction_id]['paid'] = true;

        app(PaymentService::class)->refresh($a->order, null, 'fawaterak');

        \Illuminate\Support\Facades\Http::assertSent(fn ($r) => str_contains($r->url(), 'getInvoiceData') && $r->hasHeader('Content-Type', 'application/json') && $r->hasHeader('Accept', 'application/json'));
    }

    public function test_a_flagged_payment_says_why_it_needs_review(): void
    {
        $acc = $this->account('fawaterak', 'FA');
        $a = $this->attempt($acc, 'fawaterak_card');
        $viewer = $this->staff(['payments.transactions.view', 'orders.view', 'payments.reports.view']);
        $a->forceFill(['meta' => ['needs_review' => 'duplicate_payment']])->saveQuietly();

        $this->assertSame('Paid twice', $a->fresh()->reviewReason()[0]);
        $this->actingAs($viewer)->get(route('admin.payments.transactions.show', $a))->assertOk()->assertSee('Needs review')->assertSee('Paid twice')->assertSee('charged twice');
        $this->actingAs($viewer)->get(route('admin.payments.index'))->assertOk()->assertSee('Needs review')->assertSee('Paid twice');
        $this->assertNull($this->attempt($acc, 'fawaterak_card')->reviewReason());
    }

    public function test_a_reviewed_payment_loses_its_flag_without_touching_the_payment_or_the_order(): void
    {
        $acc = $this->account('fawaterak', 'FA');
        $a = $this->attempt($acc, 'fawaterak_card');
        $a->forceFill(['status' => 'paid', 'meta' => ['needs_review' => 'duplicate_payment', 'flagged_at' => now()->toIso8601String()]])->saveQuietly();
        $orderStatus = $a->order->fresh()->status;
        $viewer = $this->staff(['payments.transactions.view', 'orders.view', 'payments.reports.view']);
        $ops = $this->staff(['payments.transactions.view', 'payments.transactions.inquire', 'orders.view', 'payments.reports.view']);
        $url = route('admin.payments.transactions.review.dismiss', $a);

        // the review filter finds it; people without the inquire permission cannot clear it (and do not see the button)
        $this->actingAs($ops)->get(route('admin.payments.index', ['status' => 'review']))->assertOk()->assertSee($a->order->order_number)->assertSee('Mark as reviewed');
        $this->actingAs($viewer)->get(route('admin.payments.index'))->assertOk()->assertDontSee('Mark as reviewed');
        $this->actingAs($viewer)->post($url)->assertForbidden();
        $this->assertSame('duplicate_payment', data_get($a->fresh()->meta, 'needs_review'));

        $this->actingAs($ops)->post($url)->assertRedirect()->assertSessionHas('success');

        $fresh = $a->fresh();
        $this->assertNull(data_get($fresh->meta, 'needs_review'));
        $this->assertSame('duplicate_payment', data_get($fresh->meta, 'review_dismissed.reason'));
        $this->assertSame($ops->id, data_get($fresh->meta, 'review_dismissed.by_id'));
        $this->assertSame('paid', $fresh->status);
        $this->assertSame($orderStatus, $a->order->fresh()->status);
        $this->actingAs($ops)->get(route('admin.payments.index'))->assertOk()->assertDontSee('Paid twice')->assertDontSee('Mark as reviewed');   // (the filter menu itself still lists "Needs review")
        $this->actingAs($ops)->get(route('admin.payments.index', ['status' => 'review']))->assertOk()->assertDontSee($a->order->order_number);
    }

    public function test_the_confirmation_page_polls_and_learns_the_result_straight_away(): void
    {
        $acc = $this->account('fawaterak', 'FA');
        $a = $this->attempt($acc, 'fawaterak_card');
        $number = $a->order->order_number;
        $status = fn () => $this->actingAs($this->customer)->getJson(route('front.checkout.confirmation.status', $number));

        // the customer is back but the provider has not marked the invoice paid yet
        $status()->assertOk()->assertJson(['state' => 'pending']);
        $this->actingAs($this->customer)->get(route('front.checkout.confirmation', $number))->assertOk()->assertSee('data-poll-url', false);

        // the moment the provider confirms, the next poll says paid (no page reload needed to ask)
        $this->fawInvoices[$a->transaction_id]['paid'] = true;
        \Illuminate\Support\Facades\Cache::forget('order-refresh:'.$a->order_id);
        $status()->assertOk()->assertJson(['state' => 'paid']);
        $this->assertSame('paid', $a->order->fresh()->status);
        $this->actingAs($this->customer)->get(route('front.checkout.confirmation', $number))->assertOk()->assertDontSee('data-poll-url', false)->assertSee('Your payment was confirmed');

        // somebody else's browser cannot poll an order that is not theirs
        $this->actingAs(User::factory()->create())->getJson(route('front.checkout.confirmation.status', $number))->assertNotFound();
    }

    public function test_a_failure_reported_only_by_webhook_ends_the_pending_page_with_a_failed_state(): void
    {
        $acc = $this->account('fawaterak', 'FA');
        $a = $this->attempt($acc, 'fawaterak_card');
        $this->postJson(route('front.fawaterak.webhook'), ['invoice_id' => $a->transaction_id, 'invoice_key' => 'IK', 'payment_method' => 'Card', 'amount' => 300, 'errorMessage' => '3DS failed', 'response' => ['gatewayCode' => 'X']])->assertOk();

        $this->actingAs($this->customer)->getJson(route('front.checkout.confirmation.status', $a->order->order_number))->assertOk()->assertJson(['state' => 'failed']);
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
