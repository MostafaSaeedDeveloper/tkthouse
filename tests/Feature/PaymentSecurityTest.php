<?php

namespace Tests\Feature;

use App\Mail\OrderTicketsIssuedMail;
use App\Models\Event;
use App\Models\IssuedTicket;
use App\Models\Order;
use App\Models\PaymentAttempt;
use App\Models\User;
use App\Services\Payments\GatewayResult;
use App\Services\Payments\PaymentService;
use App\Services\PaymobService;
use App\Services\PendingPaymentExpiryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Mail\Events\MessageSending;
use Illuminate\Support\Facades\Event as EventFacade;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\Support\BuildsOrders;
use Tests\TestCase;

/** Payment security: signatures, strict settlement, late payments, environment safety, issuance recovery. */
class PaymentSecurityTest extends TestCase
{
    use BuildsOrders, RefreshDatabase;

    private User $user;
    private Order $order;
    private PaymentAttempt $attempt;
    private Event $event;

    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
        $this->user = User::factory()->create();
        $this->event = $this->makeEvent();
        $this->makeTicket($this->event);
        $this->order = $this->makePendingOrder($this->user, $this->event, 2, 150, 'paymob_card'); // total 300
        $this->makeMethod('paymob', 'paymob_card', ['api_key' => 'k', 'iframe_id' => '1', 'integration_id' => '2']);
        $this->attempt = PaymentAttempt::create([
            'order_id' => $this->order->id, 'provider' => 'paymob', 'method_code' => 'paymob_card',
            'reference' => $this->order->order_number.'-ref1', 'amount' => 300, 'currency' => 'EGP', 'status' => 'initiated',
        ]);
    }

    private function production(): void
    {
        $this->app->detectEnvironment(fn () => 'production');
    }

    private function paid(array $o = []): GatewayResult
    {
        $o = array_merge(['tx' => 'tx-1', 'amount' => 300.0, 'currency' => 'EGP', 'reference' => $this->attempt->reference], $o);

        return new GatewayResult(GatewayResult::PAID, $o['tx'], $o['amount'], $o['currency'], $o['reference']);
    }

    private function settle(?GatewayResult $r = null, ?PaymentAttempt $a = null): string
    {
        return app(PaymentService::class)->settle($a ?? $this->attempt, $r ?? $this->paid());
    }

    private function paymobTx(array $o = []): array
    {
        return array_merge([
            'id' => 555, 'success' => true, 'pending' => false, 'is_voided' => false, 'is_refunded' => false, 'amount_cents' => 30000, 'currency' => 'EGP',
            'order' => ['id' => 9, 'merchant_order_id' => $this->attempt->reference],
        ], $o);
    }

    private function fakePaymob(?array $tx = null): void
    {
        Http::fake([
            'accept.paymob.com/api/auth/tokens' => Http::response(['token' => 't']),
            'accept.paymob.com/api/acceptance/transactions/*' => Http::response($tx ?? $this->paymobTx()),
        ]);
    }

    private function signedObj(): array
    {
        return [
            'amount_cents' => 30000, 'created_at' => '2026-01-01T00:00:00', 'currency' => 'EGP', 'error_occured' => false, 'has_parent_transaction' => false,
            'id' => 555, 'integration_id' => 2, 'is_3d_secure' => true, 'is_auth' => false, 'is_capture' => false, 'is_refunded' => false,
            'is_standalone_payment' => true, 'is_voided' => false, 'order' => ['id' => 9, 'merchant_order_id' => $this->attempt->reference], 'owner' => 1,
            'pending' => false, 'source_data' => ['pan' => '2346', 'sub_type' => 'MasterCard', 'type' => 'card'], 'success' => true,
        ];
    }

    private function sign(array $obj, string $secret): string
    {
        $c = '';
        foreach (['amount_cents', 'created_at', 'currency', 'error_occured', 'has_parent_transaction', 'id', 'integration_id', 'is_3d_secure', 'is_auth', 'is_capture', 'is_refunded',
            'is_standalone_payment', 'is_voided', 'order.id', 'owner', 'pending', 'source_data.pan', 'source_data.sub_type', 'source_data.type', 'success'] as $k) {
            $v = data_get($obj, $k, '');
            $c .= is_bool($v) ? ($v ? 'true' : 'false') : (string) $v;
        }

        return hash_hmac('sha512', $c, $secret);
    }

    /* ================= 1. Paymob signature ================= */

    public function test_in_production_an_unconfigured_secret_rejects_every_webhook(): void
    {
        $this->production();
        config(['payments.paymob.hmac_secret' => null]);
        $this->fakePaymob();

        $this->postJson(route('front.paymob.callback'), ['obj' => $this->signedObj()])->assertStatus(503)->assertJson(['received' => false]);

        $this->assertSame('pending_payment', $this->order->fresh()->status);
        Http::assertNothingSent();
    }

    public function test_in_production_unsigned_or_wrongly_signed_webhooks_are_rejected_and_never_looked_up(): void
    {
        $this->production();
        config(['payments.paymob.hmac_secret' => 'prod-secret']);
        $this->fakePaymob();

        $this->postJson(route('front.paymob.callback'), ['obj' => $this->signedObj()])->assertStatus(403);
        $this->postJson(route('front.paymob.callback').'?hmac=deadbeef', ['obj' => $this->signedObj()])->assertStatus(403);
        $this->postJson(route('front.paymob.callback').'?hmac='.$this->sign($this->signedObj(), 'other-secret'), ['obj' => $this->signedObj()])->assertStatus(403);

        // Tampered body with a signature computed for the original one.
        $good = $this->sign($this->signedObj(), 'prod-secret');
        $tampered = $this->signedObj();
        $tampered['amount_cents'] = 1;
        $this->postJson(route('front.paymob.callback').'?hmac='.$good, ['obj' => $tampered])->assertStatus(403);

        $this->assertSame('pending_payment', $this->order->fresh()->status);
        Http::assertNothingSent();
    }

    public function test_a_valid_signature_alone_is_not_proof_of_payment(): void
    {
        $this->production();
        config(['payments.paymob.hmac_secret' => 'prod-secret']);
        // Correctly signed "success", but Paymob's own record says the transaction failed.
        $this->fakePaymob($this->paymobTx(['success' => false]));
        $obj = $this->signedObj();

        $this->postJson(route('front.paymob.callback').'?hmac='.$this->sign($obj, 'prod-secret'), ['obj' => $obj])->assertOk()->assertJson(['updated' => false]);

        $this->assertSame('pending_payment', $this->order->fresh()->status);
        Http::assertSent(fn ($r) => str_contains($r->url(), '/acceptance/transactions/555'));
    }

    public function test_a_valid_signature_plus_a_confirmed_transaction_settles_in_production(): void
    {
        $this->production();
        config(['payments.paymob.hmac_secret' => 'prod-secret']);
        $this->fakePaymob();
        $obj = $this->signedObj();

        $this->postJson(route('front.paymob.callback').'?hmac='.$this->sign($obj, 'prod-secret'), ['obj' => $obj])->assertOk()->assertJson(['updated' => true]);

        $this->assertSame('paid', $this->order->fresh()->status);
        $this->assertSame('555', $this->attempt->fresh()->transaction_id);
    }

    public function test_browser_returns_in_production_only_use_the_url_id_when_the_url_is_signed(): void
    {
        $this->production();
        config(['payments.paymob.hmac_secret' => 'prod-secret']);
        $this->fakePaymob();
        $query = ['id' => 555, 'merchant_order_id' => $this->attempt->reference, 'success' => 'true', 'hmac' => 'forged'];

        $this->actingAs($this->user)->get(route('front.paymob.callback', $query))->assertRedirect(route('front.checkout.confirmation', $this->order->order_number));

        // The forged id was never used for a lookup, so nothing was settled.
        Http::assertNothingSent();
        $this->assertSame('pending_payment', $this->order->fresh()->status);
    }

    public function test_flat_browser_return_signatures_are_verified(): void
    {
        $obj = $this->signedObj();
        $query = [];
        foreach (['amount_cents', 'created_at', 'currency', 'error_occured', 'has_parent_transaction', 'id', 'integration_id', 'is_3d_secure', 'is_auth', 'is_capture',
            'is_refunded', 'is_standalone_payment', 'is_voided', 'owner', 'pending', 'success'] as $k) {
            $query[$k] = is_bool($obj[$k]) ? ($obj[$k] ? 'true' : 'false') : (string) $obj[$k];
        }
        $query['order'] = '9';
        $query['source_data_pan'] = '2346';      // PHP turns "source_data.pan" into "source_data_pan"
        $query['source_data_sub_type'] = 'MasterCard';
        $query['source_data_type'] = 'card';
        $hmac = $this->sign($obj, 'prod-secret');

        $this->assertTrue(PaymobService::hmacIsValidFlat($query, $hmac, 'prod-secret'));
        $this->assertFalse(PaymobService::hmacIsValidFlat(array_merge($query, ['amount_cents' => '1']), $hmac, 'prod-secret'));
        $this->assertFalse(PaymobService::hmacIsValidFlat($query, '', 'prod-secret'));
        $this->assertFalse(PaymobService::hmacIsValidFlat($query, $hmac, ''));
    }

    /* ================= 2 & 3. Strict settlement and transaction binding ================= */

    public function test_a_matching_payment_is_accepted(): void
    {
        $this->assertSame(PaymentService::OUTCOME_PAID, $this->settle());
        $this->assertSame('paid', $this->order->fresh()->status);
    }

    /** @dataProvider rejectedResults */
    public function test_anything_unknown_or_different_is_rejected(array $override, string $reason): void
    {
        $this->assertSame(PaymentService::OUTCOME_MISMATCH, $this->settle($this->paid($override)));

        $this->assertSame('pending_payment', $this->order->fresh()->status);
        $this->assertSame($reason, $this->attempt->fresh()->failure_reason);
        $this->assertSame(0, IssuedTicket::count());
    }

    public static function rejectedResults(): array
    {
        return [
            'unknown amount' => [['amount' => null], 'unknown_amount'],
            'smaller amount' => [['amount' => 299.0], 'amount_mismatch'],
            'larger amount' => [['amount' => 301.0], 'amount_mismatch'],
            'unknown currency' => [['currency' => null], 'unknown_currency'],
            'empty currency' => [['currency' => ''], 'unknown_currency'],
            'other currency' => [['currency' => 'USD'], 'currency_mismatch'],
            'missing transaction id' => [['tx' => ''], 'missing_transaction_id'],
            'other reference' => [['reference' => '9999999999-other'], 'reference_mismatch'],
            'missing reference' => [['reference' => null], 'reference_mismatch'],
        ];
    }

    public function test_amount_must_match_the_attempt_and_the_order_total(): void
    {
        // Order total changed after the attempt was opened (e.g. admin edit): the stale payment is not accepted.
        $this->order->update(['total_amount' => 350]);

        $this->assertSame(PaymentService::OUTCOME_MISMATCH, $this->settle());
        $this->assertSame('pending_payment', $this->order->fresh()->status);
    }

    public function test_a_transaction_cannot_settle_two_different_orders(): void
    {
        $this->assertSame(PaymentService::OUTCOME_PAID, $this->settle($this->paid(['tx' => 'shared-tx'])));

        $other = $this->makePendingOrder($this->user, $this->event, 2, 150, 'paymob_card');
        $otherAttempt = PaymentAttempt::create([
            'order_id' => $other->id, 'provider' => 'paymob', 'method_code' => 'paymob_card', 'reference' => $other->order_number.'-ref',
            'amount' => 300, 'currency' => 'EGP', 'status' => 'initiated',
        ]);

        $result = new GatewayResult(GatewayResult::PAID, 'shared-tx', 300.0, 'EGP', $otherAttempt->reference);
        $this->assertSame(PaymentService::OUTCOME_MISMATCH, $this->settle($result, $otherAttempt));

        $this->assertSame('pending_payment', $other->fresh()->status);
        $this->assertSame('transaction_reused', $otherAttempt->fresh()->failure_reason);
        $this->assertSame(0, IssuedTicket::where('order_id', $other->id)->count());
    }

    public function test_the_database_itself_refuses_a_second_attempt_with_the_same_transaction(): void
    {
        PaymentAttempt::create(['order_id' => $this->order->id, 'provider' => 'paymob', 'amount' => 1, 'status' => 'initiated', 'transaction_id' => 'dup']);

        $this->expectException(\Illuminate\Database\UniqueConstraintViolationException::class);
        PaymentAttempt::create(['order_id' => $this->order->id, 'provider' => 'paymob', 'amount' => 1, 'status' => 'initiated', 'transaction_id' => 'dup']);
    }

    public function test_the_same_transaction_id_may_exist_at_different_providers_and_unbound_attempts_may_be_null(): void
    {
        PaymentAttempt::create(['order_id' => $this->order->id, 'provider' => 'paymob', 'amount' => 1, 'status' => 'initiated', 'transaction_id' => 'same']);
        PaymentAttempt::create(['order_id' => $this->order->id, 'provider' => 'fawaterak', 'amount' => 1, 'status' => 'initiated', 'transaction_id' => 'same']);
        PaymentAttempt::create(['order_id' => $this->order->id, 'provider' => 'paymob', 'amount' => 1, 'status' => 'initiated']);
        PaymentAttempt::create(['order_id' => $this->order->id, 'provider' => 'paymob', 'amount' => 1, 'status' => 'initiated']);

        $this->assertSame(5, PaymentAttempt::count());
    }

    public function test_replaying_the_same_callback_reuses_nothing_and_changes_nothing(): void
    {
        $this->settle();
        $before = $this->attempt->fresh()->toArray();

        $this->assertSame(PaymentService::OUTCOME_ALREADY_PAID, $this->settle());
        $this->assertSame(PaymentService::OUTCOME_ALREADY_PAID, $this->settle($this->paid(['tx' => 'tx-other'])));

        $this->assertSame($before['transaction_id'], $this->attempt->fresh()->transaction_id);
        $this->assertSame(2, IssuedTicket::count());
    }

    public function test_a_retry_inside_the_same_gateway_session_records_the_paying_transaction(): void
    {
        $this->attempt->update(['transaction_id' => 'tx-failed-first', 'status' => 'failed']);

        $this->assertSame(PaymentService::OUTCOME_PAID, $this->settle($this->paid(['tx' => 'tx-second-try'])));

        $fresh = $this->attempt->fresh();
        $this->assertSame('tx-second-try', $fresh->transaction_id);
        $this->assertSame(['tx-failed-first'], $fresh->meta['previous_transaction_ids']);
    }

    public function test_a_transaction_that_belongs_to_another_attempt_of_the_same_order_is_ignored_by_paymob(): void
    {
        $second = PaymentAttempt::create([
            'order_id' => $this->order->id, 'provider' => 'paymob', 'method_code' => 'paymob_card', 'reference' => $this->order->order_number.'-ref2',
            'amount' => 300, 'currency' => 'EGP', 'status' => 'initiated',
        ]);
        // Paymob says tx 555 belongs to ref1 (the first attempt).
        $this->fakePaymob();

        $result = app(\App\Services\Payments\Gateways\PaymobGateway::class)->inquire($second, '555');

        $this->assertSame(GatewayResult::UNKNOWN, $result->status);
    }

    /* ================= 4. Late payments ================= */

    private function expire(): void
    {
        $this->order->update(['payment_timeout_started_at' => now()->subHours(5), 'payment_timeout_minutes' => 60]);
        app(PendingPaymentExpiryService::class)->expireDueOrders();
        $this->assertSame('canceled', $this->order->fresh()->status);
    }

    public function test_a_late_payment_reinstates_the_order_only_when_it_is_still_fulfillable(): void
    {
        $this->expire();

        $this->assertSame(PaymentService::OUTCOME_PAID, $this->settle());
        $this->assertSame('paid', $this->order->fresh()->status);
        $this->assertSame(2, IssuedTicket::count());
    }

    /** @dataProvider unfulfillable */
    public function test_a_late_payment_for_an_unfulfillable_order_is_recorded_for_refund_and_issues_nothing(string $change, string $reason): void
    {
        $this->expire();
        match ($change) {
            'ended' => $this->event->update(['event_date' => now()->subDay()->toDateString()]),
            'inactive' => $this->event->update(['status' => 'inactive']),
            'archived' => $this->event->update(['status' => 'archived']),
            'sold_out' => $this->event->update(['status' => 'sold_out']),
            'ticket_off' => $this->event->tickets()->update(['status' => 'sold_out']),
            'ticket_gone' => $this->event->tickets()->delete(),
        };

        $this->assertSame(PaymentService::OUTCOME_REVIEW_REQUIRED, $this->settle());

        $this->assertSame('canceled', $this->order->fresh()->status);
        $this->assertSame(0, IssuedTicket::count());
        $this->assertSame(0, \App\Models\Ticket::count());
        $attempt = $this->attempt->fresh();
        $this->assertSame('paid', $attempt->status);                       // money WAS taken: keep the record
        $this->assertSame($reason, $attempt->meta['needs_review']);
        $this->assertDatabaseHas('activity_log', ['subject_id' => $this->order->id, 'description' => 'Payment received but the order can no longer be fulfilled — refund review required']);

        // Replaying the callback does not change the decision.
        $this->assertSame(PaymentService::OUTCOME_ALREADY_PAID, $this->settle());
        $this->assertSame(0, IssuedTicket::count());

        $this->artisan('payments:review-queue')->expectsOutputToContain($this->order->order_number)->assertExitCode(0);
    }

    public static function unfulfillable(): array
    {
        return [
            'event ended' => ['ended', 'event_ended'],
            'event no longer active' => ['inactive', 'event_not_on_sale'],
            'event sold out' => ['sold_out', 'event_not_on_sale'],
            'event archived' => ['archived', 'event_not_on_sale'],
            'ticket no longer on sale' => ['ticket_off', 'ticket_unavailable'],
            'ticket removed' => ['ticket_gone', 'ticket_unavailable'],
        ];
    }

    public function test_a_still_pending_order_is_honoured_unless_its_event_has_ended(): void
    {
        $this->event->update(['status' => 'sold_out']); // admin closed sales after this order was created
        $this->assertSame(PaymentService::OUTCOME_PAID, $this->settle());
        $this->assertSame('paid', $this->order->fresh()->status);
    }

    public function test_a_pending_order_for_an_event_that_ended_is_flagged_not_fulfilled(): void
    {
        $this->event->update(['event_date' => now()->subDay()->toDateString()]);

        $this->assertSame(PaymentService::OUTCOME_REVIEW_REQUIRED, $this->settle());
        $this->assertSame('pending_payment', $this->order->fresh()->status);
        $this->assertSame(0, IssuedTicket::count());
    }

    public function test_the_review_queue_is_empty_when_everything_is_fine(): void
    {
        $this->settle();
        $this->artisan('payments:review-queue')->expectsOutputToContain('Nothing needs review.')->assertExitCode(0);
    }

    /* ================= 7. Issuance failure after a successful payment ================= */

    public function test_a_failure_while_issuing_does_not_lose_the_payment_and_is_repaired_without_duplicates(): void
    {
        $fail = true;
        IssuedTicket::creating(function () use (&$fail) {
            if ($fail) {
                throw new \RuntimeException('database hiccup');
            }
        });

        $this->assertSame(PaymentService::OUTCOME_PAID, $this->settle());   // payment accepted despite the failure

        $order = $this->order->fresh();
        $this->assertSame('paid', $order->status);
        $this->assertNull($order->tickets_generated_at);
        $this->assertSame(0, IssuedTicket::count());
        $this->assertDatabaseHas('activity_log', ['subject_id' => $order->id, 'description' => 'Ticket issuance failed after payment (will retry)']);

        // A duplicate callback now repairs it...
        $fail = false;
        $this->assertSame(PaymentService::OUTCOME_ALREADY_PAID, $this->settle());
        $this->assertSame(2, IssuedTicket::count());
        $this->assertSame(2, \App\Models\Ticket::count());

        // ...and the retry job afterwards changes nothing.
        $order->update(['paid_at' => now()->subMinutes(10)]);
        $this->artisan('orders:retry-ticket-issuance')->assertExitCode(0);
        $this->assertSame(2, IssuedTicket::count());
        $this->assertSame(2, \App\Models\Ticket::count());
    }

    public function test_the_retry_job_finishes_issuance_when_no_callback_ever_repeats(): void
    {
        $fail = true;
        IssuedTicket::creating(function () use (&$fail) {
            if ($fail) {
                throw new \RuntimeException('database hiccup');
            }
        });
        $this->settle();
        $fail = false;
        $this->order->update(['paid_at' => now()->subMinutes(10)]);

        $this->artisan('orders:retry-ticket-issuance')->assertExitCode(0);

        $this->assertSame(2, IssuedTicket::count());
        $this->assertNotNull($this->order->fresh()->tickets_generated_at);
        $this->artisan('orders:retry-ticket-issuance')->expectsOutputToContain('Orders retried: 0')->assertExitCode(0);
    }

    public function test_failed_deliveries_are_retried_and_successful_ones_are_never_repeated(): void
    {
        // Real (array) mailer, but the SMTP side is "down" for the first pass.
        $down = true;
        EventFacade::listen(MessageSending::class, function () use (&$down) {
            if ($down) {
                throw new \RuntimeException('smtp down');
            }
        });
        $sent = fn () => count(Mail::mailer('array')->getSymfonyTransport()->messages());
        $before = $sent();

        $this->settle();

        $this->assertSame(2, IssuedTicket::count());
        $this->assertSame(2, IssuedTicket::whereNull('sent_at')->count());   // nothing was delivered
        $this->assertSame($before, $sent());

        $down = false;
        $this->order->update(['paid_at' => now()->subMinutes(10)]);
        $this->artisan('orders:retry-ticket-issuance')->assertExitCode(0);

        $this->assertSame(0, IssuedTicket::whereNull('sent_at')->count());
        $afterFirstRetry = $sent();
        $this->assertGreaterThan($before, $afterFirstRetry);

        $this->artisan('orders:retry-ticket-issuance')->assertExitCode(0);
        app(\App\Services\TicketIssuanceService::class)->issueIfPaid($this->order->fresh());
        $this->assertSame($afterFirstRetry, $sent());                       // no duplicate e-mails
        $this->assertSame(2, IssuedTicket::count());                        // and no duplicate tickets
    }

    public function test_the_retry_job_gives_up_after_the_attempt_cap_instead_of_looping_forever(): void
    {
        $tries = 0;
        IssuedTicket::creating(function () use (&$tries) {
            $tries++;
            throw new \RuntimeException('permanent');
        });
        $this->settle();                       // 1 failed try during the payment itself
        $this->order->update(['paid_at' => now()->subMinutes(10)]);

        for ($i = 0; $i < 6; $i++) {
            $this->artisan('orders:retry-ticket-issuance --max-attempts=2')->assertExitCode(0);
        }

        $this->assertSame(1 + 2, $tries);      // initial try + exactly 2 retries, then it stops
        $this->assertSame(0, IssuedTicket::count());
    }
}
