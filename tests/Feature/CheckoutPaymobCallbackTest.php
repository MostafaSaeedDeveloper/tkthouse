<?php

namespace Tests\Feature;

use App\Mail\OrderTicketsIssuedMail;
use App\Models\IssuedTicket;
use App\Models\Order;
use App\Models\PaymentAttempt;
use App\Models\User;
use App\Services\PaymobService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\Support\BuildsOrders;
use Tests\TestCase;

/** Paymob + Fawaterak callbacks. Gateways are faked at the HTTP layer: nothing real is contacted. */
class CheckoutPaymobCallbackTest extends TestCase
{
    use BuildsOrders, RefreshDatabase;

    private User $user;
    private Order $order;
    private PaymentAttempt $attempt;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        $this->user = User::factory()->create();
    }

    private function paymobOrder(): void
    {
        $this->makeMethod('paymob', 'paymob_card', ['api_key' => 'k', 'iframe_id' => '1', 'integration_id' => '2']);
        $this->order = $this->makePendingOrder($this->user, null, 2, 150, 'paymob_card'); // total 300.00
        $this->attempt = PaymentAttempt::create([
            'order_id' => $this->order->id, 'provider' => 'paymob', 'method_code' => 'paymob_card',
            'reference' => $this->order->order_number.'-retryabcd', 'amount' => 300, 'status' => 'initiated',
        ]);
    }

    /** @param array<string, mixed>|array<int|string, array<string, mixed>> $tx one transaction, or [transactionId => transaction] */
    private function fakePaymob(array $tx): void
    {
        Http::fake([
            'accept.paymob.com/api/auth/tokens' => Http::response(['token' => 'tok']),
            'accept.paymob.com/api/acceptance/transactions/*' => fn ($request) => Http::response(
                isset($tx['id']) ? $tx : ($tx[(int) basename($request->url())] ?? ['detail' => 'Not found'])
            ),
        ]);
    }

    private function tx(array $o = []): array
    {
        return array_merge([
            'id' => 555, 'success' => true, 'pending' => false, 'is_voided' => false, 'is_refunded' => false,
            'amount_cents' => 30000, 'currency' => 'EGP',
            'order' => ['id' => 9, 'merchant_order_id' => $this->order->order_number.'-retryabcd'],
        ], $o);
    }

    private function callbackBody(array $obj = []): array
    {
        return ['obj' => array_merge(['id' => 555, 'success' => true, 'order' => ['merchant_order_id' => $this->order->order_number.'-retryabcd']], $obj)];
    }

    public function test_paymob_callback_maps_suffixed_merchant_order_id_and_settles_after_server_verification(): void
    {
        $this->paymobOrder();
        $this->fakePaymob($this->tx());

        $this->postJson(route('front.paymob.callback'), $this->callbackBody())
            ->assertOk()->assertJson(['received' => true, 'updated' => true]);

        $this->assertSame('paid', $this->order->fresh()->status);
        $this->assertSame('555', $this->attempt->fresh()->transaction_id);
        $this->assertSame(2, IssuedTicket::count());
    }

    public function test_forged_success_callback_does_not_mark_the_order_paid(): void
    {
        $this->paymobOrder();
        // Attacker posts success=true, but Paymob says the transaction did not succeed.
        $this->fakePaymob($this->tx(['success' => false, 'pending' => false]));

        $this->postJson(route('front.paymob.callback'), $this->callbackBody())->assertOk()->assertJson(['updated' => false]);

        $this->assertSame('pending_payment', $this->order->fresh()->status);
        $this->assertSame(0, IssuedTicket::count());
    }

    public function test_callback_with_an_unknown_transaction_id_cannot_settle(): void
    {
        $this->paymobOrder();
        Http::fake([
            'accept.paymob.com/api/auth/tokens' => Http::response(['token' => 'tok']),
            'accept.paymob.com/api/acceptance/transactions/*' => Http::response(['detail' => 'Not found'], 404),
        ]);

        $this->postJson(route('front.paymob.callback'), $this->callbackBody(['id' => 999999]))->assertOk()->assertJson(['updated' => false]);

        $this->assertSame('pending_payment', $this->order->fresh()->status);
    }

    public function test_someone_elses_successful_transaction_cannot_pay_this_order(): void
    {
        $this->paymobOrder();
        $this->fakePaymob($this->tx(['order' => ['id' => 9, 'merchant_order_id' => '1234567890-other']]));

        $this->postJson(route('front.paymob.callback'), $this->callbackBody())->assertOk()->assertJson(['updated' => false]);

        $this->assertSame('pending_payment', $this->order->fresh()->status);
        $this->assertSame('initiated', $this->attempt->fresh()->status);
    }

    public function test_wrong_amount_is_rejected(): void
    {
        $this->paymobOrder();
        $this->fakePaymob($this->tx(['amount_cents' => 100]));

        $this->postJson(route('front.paymob.callback'), $this->callbackBody())->assertOk()->assertJson(['updated' => false]);

        $this->assertSame('pending_payment', $this->order->fresh()->status);
        $this->assertSame('amount_mismatch', $this->attempt->fresh()->failure_reason);
    }

    public function test_voided_or_refunded_transactions_are_not_paid(): void
    {
        $this->paymobOrder();
        $this->fakePaymob($this->tx(['is_refunded' => true]));

        $this->postJson(route('front.paymob.callback'), $this->callbackBody())->assertJson(['updated' => false]);

        $this->assertSame('pending_payment', $this->order->fresh()->status);
    }

    public function test_hmac_is_enforced_when_the_secret_is_configured(): void
    {
        config(['payments.paymob.hmac_secret' => 'topsecret']);
        $this->paymobOrder();
        $this->fakePaymob($this->tx());

        $this->postJson(route('front.paymob.callback').'?hmac=deadbeef', $this->callbackBody())->assertStatus(403);
        $this->assertSame('pending_payment', $this->order->fresh()->status);

        // A correctly signed body is accepted (and still verified server-to-server).
        $obj = [
            'amount_cents' => 30000, 'created_at' => '2026-01-01T00:00:00', 'currency' => 'EGP', 'error_occured' => false,
            'has_parent_transaction' => false, 'id' => 555, 'integration_id' => 2, 'is_3d_secure' => true, 'is_auth' => false,
            'is_capture' => false, 'is_refunded' => false, 'is_standalone_payment' => true, 'is_voided' => false,
            'order' => ['id' => 9, 'merchant_order_id' => $this->order->order_number.'-retryabcd'], 'owner' => 1, 'pending' => false,
            'source_data' => ['pan' => '2346', 'sub_type' => 'MasterCard', 'type' => 'card'], 'success' => true,
        ];
        $concat = '30000'.'2026-01-01T00:00:00'.'EGP'.'false'.'false'.'555'.'2'.'true'.'false'.'false'.'false'.'true'.'false'.'9'.'1'.'false'.'2346'.'MasterCard'.'card'.'true';
        $hmac = hash_hmac('sha512', $concat, 'topsecret');
        $this->assertTrue(PaymobService::hmacIsValid($obj, $hmac, 'topsecret'));

        $this->postJson(route('front.paymob.callback').'?hmac='.$hmac, ['obj' => $obj])->assertOk()->assertJson(['updated' => true]);
        $this->assertSame('paid', $this->order->fresh()->status);
    }

    public function test_redirect_before_callback_and_callback_after_issue_tickets_once(): void
    {
        $this->paymobOrder();
        $this->fakePaymob($this->tx());

        // Customer browser returns first (GET) ...
        $this->actingAs($this->user)
            ->get(route('front.paymob.callback', ['id' => 555, 'success' => 'true', 'merchant_order_id' => $this->order->order_number.'-retryabcd']))
            ->assertRedirect(route('front.checkout.confirmation', $this->order->order_number));
        $this->assertSame('paid', $this->order->fresh()->status);

        // ... then the server-to-server callback (twice) arrives.
        $this->postJson(route('front.paymob.callback'), $this->callbackBody())->assertJson(['updated' => true]);
        $this->postJson(route('front.paymob.callback'), $this->callbackBody())->assertJson(['updated' => true]);

        $this->assertSame(2, IssuedTicket::count());
        Mail::assertSent(OrderTicketsIssuedMail::class, 1);
    }

    public function test_callback_before_redirect_then_redirect_is_a_noop(): void
    {
        $this->paymobOrder();
        $this->fakePaymob($this->tx());

        $this->postJson(route('front.paymob.callback'), $this->callbackBody())->assertJson(['updated' => true]);
        $this->get(route('front.paymob.callback', ['id' => 555, 'merchant_order_id' => $this->order->order_number.'-retryabcd']))
            ->assertRedirect(route('front.checkout.confirmation', $this->order->order_number));

        $this->assertSame(2, IssuedTicket::count());
        Mail::assertSent(OrderTicketsIssuedMail::class, 1);
    }

    public function test_failed_transaction_marks_the_attempt_failed_but_a_later_success_still_settles(): void
    {
        $this->paymobOrder();
        $this->fakePaymob([556 => $this->tx(['success' => false, 'id' => 556]), 557 => $this->tx(['id' => 557])]);
        $this->postJson(route('front.paymob.callback'), $this->callbackBody(['id' => 556, 'success' => false]))->assertJson(['updated' => false]);
        $this->assertSame('failed', $this->attempt->fresh()->status);
        $this->assertSame('pending_payment', $this->order->fresh()->status);

        // The customer retried inside the same Paymob session and paid.
        $this->postJson(route('front.paymob.callback'), $this->callbackBody(['id' => 557]))->assertJson(['updated' => true]);

        $this->assertSame('paid', $this->order->fresh()->status);
    }

    public function test_callback_for_unknown_orders_is_ignored(): void
    {
        $this->postJson(route('front.paymob.callback'), ['obj' => ['id' => 1, 'success' => true, 'order' => ['merchant_order_id' => '0000-nope']]])
            ->assertOk()->assertJson(['received' => true, 'updated' => false]);
        $this->postJson(route('front.paymob.callback'), [])->assertOk()->assertJson(['updated' => false]);
    }

    /* ---------------- Fawaterak ---------------- */

    private function fawaterakOrder(): void
    {
        $this->makeMethod('fawaterak', 'fawaterak_card', ['api_key' => 'k', 'provider_key' => '1']);
        $this->order = $this->makePendingOrder($this->user, null, 2, 150, 'fawaterak_card');
        $this->attempt = PaymentAttempt::create([
            'order_id' => $this->order->id, 'provider' => 'fawaterak', 'method_code' => 'fawaterak_card',
            'transaction_id' => 'INV-77', 'amount' => 300, 'status' => 'initiated',
        ]);
    }

    /** Shape of the official "Get Transaction Data" example response (fawaterak-api.readme.io). */
    private function invoice(array $data = []): array
    {
        return ['status' => 'success', 'data' => array_merge([
            'invoice_id' => 'INV-77', 'invoice_key' => 'abc', 'currency' => 'EGP', 'total' => 300, 'paid' => 1, 'paid_at' => '2026-10-16 10:00:00',
        ], $data)];
    }

    private function fawaterakUrl(string $result = 'success', array $extra = []): string
    {
        return route('front.fawaterak.callback', ['order' => $this->order, 'token' => $this->order->payment_link_token, 'result' => $result] + $extra);
    }

    public function test_fawaterak_success_url_alone_does_not_pay_the_order(): void
    {
        $this->fawaterakOrder();
        Http::fake(['app.fawaterk.com/*' => Http::response($this->invoice(['paid' => 0, 'paid_at' => null]))]);

        $this->get($this->fawaterakUrl('success'))->assertRedirect(route('front.checkout.confirmation', $this->order->order_number));

        $this->assertSame('pending_payment', $this->order->fresh()->status);
        $this->assertSame(0, IssuedTicket::count());
    }

    public function test_fawaterak_payment_is_settled_only_when_the_invoice_is_paid(): void
    {
        $this->fawaterakOrder();
        Http::fake(['app.fawaterk.com/*' => Http::response($this->invoice())]);

        $this->get($this->fawaterakUrl('success'));
        $this->get($this->fawaterakUrl('success')); // duplicate redirect

        $this->assertSame('paid', $this->order->fresh()->status);
        $this->assertSame(2, IssuedTicket::count());
        Mail::assertSent(OrderTicketsIssuedMail::class, 1);
        Http::assertSent(fn ($request) => str_contains($request->url(), 'getInvoiceData/INV-77'));
    }

    public function test_fawaterak_ignores_a_browser_supplied_invoice_id_when_one_is_stored(): void
    {
        $this->fawaterakOrder();
        Http::fake(['app.fawaterk.com/*' => Http::response($this->invoice(['paid' => 0, 'paid_at' => null]))]);

        $this->get($this->fawaterakUrl('success', ['invoice_id' => 'SOMEONE-ELSES-PAID-INVOICE']));

        Http::assertSent(fn ($request) => str_contains($request->url(), 'INV-77') && ! str_contains($request->url(), 'SOMEONE-ELSES'));
        $this->assertSame('pending_payment', $this->order->fresh()->status);
    }

    public function test_fawaterak_amount_mismatch_never_pays(): void
    {
        $this->fawaterakOrder();
        Http::fake(['app.fawaterk.com/*' => Http::response($this->invoice(['total' => 5]))]);

        $this->get($this->fawaterakUrl());

        $this->assertSame('pending_payment', $this->order->fresh()->status);
        $this->assertSame('amount_mismatch', $this->attempt->fresh()->failure_reason);
    }

    public function test_fawaterak_api_errors_leave_the_payment_pending_for_reconciliation(): void
    {
        $this->fawaterakOrder();
        Http::fake(['app.fawaterk.com/*' => Http::response('boom', 500)]);

        $this->get($this->fawaterakUrl());

        $this->assertSame('pending_payment', $this->order->fresh()->status);
        $this->assertSame('initiated', $this->attempt->fresh()->status);
    }

    public function test_fawaterak_callback_requires_the_order_token(): void
    {
        $this->fawaterakOrder();

        $this->get(route('front.fawaterak.callback', ['order' => $this->order, 'token' => 'nope', 'result' => 'success']))->assertNotFound();
    }
}
