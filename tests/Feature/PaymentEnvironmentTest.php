<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\PaymentAttempt;
use App\Models\PaymentMethod;
use App\Models\User;
use App\Services\Payments\GatewayResult;
use App\Services\Payments\PaymentGatewayManager;
use App\Services\Payments\PaymentService;
use App\Services\PaymobService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\Support\BuildsOrders;
use Tests\TestCase;

/** Which gateways may run where, and Fawaterak's verification contract (official "Get Transaction Data" shape). */
class PaymentEnvironmentTest extends TestCase
{
    use BuildsOrders, RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
        Mail::fake();
        $this->user = User::factory()->create();
    }

    private function env(string $name): void
    {
        $this->app->detectEnvironment(fn () => $name);
    }

    private function codes(): array
    {
        return app(PaymentGatewayManager::class)->available()->pluck('code')->all();
    }

    private function openAttempt(string $provider, string $code, array $extra = []): array
    {
        $order = $this->makePendingOrder($this->user, null, 2, 150, $code);
        $attempt = PaymentAttempt::create($extra + ['order_id' => $order->id, 'provider' => $provider, 'method_code' => $code, 'amount' => 300, 'currency' => 'EGP', 'status' => 'initiated']);

        return [$order, $attempt];
    }

    /* ================= Fake gateway ================= */

    public function test_the_fake_gateway_can_never_run_in_production_even_if_switched_on(): void
    {
        $this->makeMethod('fake', 'fake_card');
        config(['payments.fake.enabled' => true]);
        $this->env('production');
        $order = $this->makePendingOrder($this->user, null, 1, 100, 'fake_card');
        $attempt = PaymentAttempt::create(['order_id' => $order->id, 'provider' => 'fake', 'method_code' => 'fake_card', 'amount' => 100, 'status' => 'initiated', 'meta' => ['fake_state' => 'paid']]);

        $this->assertNotContains('fake_card', $this->codes());
        $this->expectExceptionMessage('not available');
        try {
            app(PaymentService::class)->start($order, PaymentMethod::where('code', 'fake_card')->first());
        } finally {
            // A stored "paid" simulator state is ignored too: it can never settle anything in production.
            app(PaymentService::class)->refresh($order);
            $this->assertSame('pending_payment', $order->fresh()->status);
            $this->assertSame('initiated', $attempt->fresh()->status);
        }
    }

    public function test_the_simulator_pages_are_404_in_production(): void
    {
        config(['payments.fake.enabled' => true]);
        $this->env('production');
        [$order, $attempt] = $this->openAttempt('fake', 'fake_card');

        $this->actingAs($this->user)->get(route('front.payments.fake.show', $attempt))->assertNotFound();
        $this->actingAs($this->user)->withSession(['_token' => 't'])->post(route('front.payments.fake.complete', $attempt), ['_token' => 't', 'outcome' => 'paid'])->assertNotFound();
        $this->assertSame('pending_payment', $order->fresh()->status);
    }

    public function test_the_fake_method_helper_refuses_to_run_in_production(): void
    {
        $this->env('production');

        $this->artisan('payments:setup-fake-method')->expectsOutputToContain('Refusing to run in production')->assertExitCode(1);
        $this->assertSame(0, PaymentMethod::where('provider', 'fake')->count());
    }

    /* ================= Staging must not touch production gateways ================= */

    public function test_outside_production_live_gateways_are_off_unless_explicitly_enabled(): void
    {
        config(['payments.live_gateways.enabled' => false]);
        $this->makeMethod('paymob', 'paymob_card', ['api_key' => 'PROD-KEY', 'iframe_id' => '1', 'integration_id' => '2']);
        $this->makeMethod('fawaterak', 'fawaterak_card', ['api_key' => 'PROD-KEY', 'provider_key' => '5']);

        $this->assertNotContains('paymob_card', $this->codes());
        $this->assertNotContains('fawaterak_card', $this->codes());

        config(['payments.live_gateways.enabled' => true]);
        $this->assertContains('paymob_card', $this->codes());
        $this->assertContains('fawaterak_card', $this->codes());
    }

    public function test_staging_never_contacts_a_real_gateway_not_for_payment_callbacks_or_the_scheduler(): void
    {
        config(['payments.live_gateways.enabled' => false]);
        $this->makeMethod('paymob', 'paymob_card', ['api_key' => 'PROD-KEY', 'iframe_id' => '1', 'integration_id' => '2']);
        $this->makeMethod('fawaterak', 'fawaterak_card', ['api_key' => 'PROD-KEY', 'provider_key' => '5']);
        [$pmOrder, $pm] = $this->openAttempt('paymob', 'paymob_card', ['reference' => 'X-ref', 'transaction_id' => 'tx-1']);
        [$fwOrder, $fw] = $this->openAttempt('fawaterak', 'fawaterak_card', ['transaction_id' => 'INV-1']);
        $pm->forceFill(['created_at' => now()->subMinutes(10)])->save();
        $fw->forceFill(['created_at' => now()->subMinutes(10)])->save();

        $this->artisan('orders:reconcile-payments')->assertExitCode(0);                // the scheduler
        app(PaymentService::class)->refresh($pmOrder, 'tx-1', 'paymob');               // a callback
        app(PaymentService::class)->refresh($fwOrder, 'INV-1', 'fawaterak');
        $this->actingAs($this->user)->get(route('front.orders.payment.gateway', ['order' => $pmOrder, 'token' => $pmOrder->payment_link_token, 'method' => 'paymob_card']))->assertRedirect(route('front.orders.payment', ['order' => $pmOrder, 'token' => $pmOrder->payment_link_token]));

        Http::assertNothingSent();
        $this->assertSame('pending_payment', $pmOrder->fresh()->status);
        $this->assertSame('pending_payment', $fwOrder->fresh()->status);
        $this->assertSame(1, $pmOrder->paymentAttempts()->count());                   // no new attempt was opened either
    }

    public function test_the_gateway_services_themselves_refuse_when_live_gateways_are_disabled(): void
    {
        config(['payments.live_gateways.enabled' => false]);
        $method = $this->makeMethod('paymob', 'paymob_card', ['api_key' => 'K', 'iframe_id' => '1', 'integration_id' => '2']);
        $order = $this->makePendingOrder($this->user, null, 1, 100, 'paymob_card');

        $this->expectExceptionMessage('Live payment gateways are disabled');
        try {
            app(PaymobService::class)->createCheckout($order);
        } finally {
            Http::assertNothingSent();
        }
    }

    public function test_production_always_allows_the_real_gateways(): void
    {
        config(['payments.live_gateways.enabled' => false]);
        $this->env('production');
        $this->makeMethod('paymob', 'paymob_card', ['api_key' => 'K', 'iframe_id' => '1', 'integration_id' => '2']);

        $this->assertContains('paymob_card', $this->codes());
    }

    /* ================= Fawaterak ================= */

    private function invoice(array $data = [], array $top = []): array
    {
        return $top + ['status' => 'success', 'data' => array_merge([
            'invoice_id' => 'INV-9', 'invoice_key' => 'k', 'currency' => 'EGP', 'total' => 300, 'paid' => 1, 'paid_at' => '2026-10-16 10:00:00',
        ], $data)];
    }

    private function fawaterak(array $json): array
    {
        $this->makeMethod('fawaterak', 'fawaterak_card', ['api_key' => 'k', 'provider_key' => '5']);
        [$order, $attempt] = $this->openAttempt('fawaterak', 'fawaterak_card', ['transaction_id' => 'INV-9']);
        Http::fake(['app.fawaterk.com/*' => Http::response($json)]);

        return [$order, $attempt];
    }

    public function test_fawaterak_is_settled_only_from_a_complete_official_shaped_paid_response(): void
    {
        [$order, $attempt] = $this->fawaterak($this->invoice());

        $this->assertSame(PaymentService::OUTCOME_PAID, app(PaymentService::class)->refresh($order));
        $this->assertSame('paid', $order->fresh()->status);
        Http::assertSent(fn ($r) => $r->method() === 'GET' && str_ends_with($r->url(), '/api/v2/getInvoiceData/INV-9') && $r->hasHeader('Authorization', 'Bearer k'));
    }

    /** @dataProvider notConfirmed */
    public function test_fawaterak_anything_not_positively_confirmed_never_pays(array $json): void
    {
        [$order] = $this->fawaterak($json);

        $this->assertNotSame(PaymentService::OUTCOME_PAID, app(PaymentService::class)->refresh($order));
        $this->assertSame('pending_payment', $order->fresh()->status);
    }

    public static function notConfirmed(): array
    {
        $base = ['invoice_id' => 'INV-9', 'invoice_key' => 'k', 'currency' => 'EGP', 'total' => 300, 'paid' => 1, 'paid_at' => '2026-10-16 10:00:00'];

        return [
            'paid flag 0' => [['status' => 'success', 'data' => array_merge($base, ['paid' => 0])]],
            'paid flag but no paid_at' => [['status' => 'success', 'data' => array_merge($base, ['paid_at' => null])]],
            'paid is a string word' => [['status' => 'success', 'data' => array_merge($base, ['paid' => 'yes'])]],
            'wrong top-level status' => [['status' => 'error', 'data' => $base]],
            'a different invoice' => [['status' => 'success', 'data' => array_merge($base, ['invoice_id' => 'INV-OTHER'])]],
            'missing total' => [['status' => 'success', 'data' => array_diff_key($base, ['total' => 1])]],
            'wrong total' => [['status' => 'success', 'data' => array_merge($base, ['total' => 3])]],
            'missing currency' => [['status' => 'success', 'data' => array_diff_key($base, ['currency' => 1])]],
            'other currency' => [['status' => 'success', 'data' => array_merge($base, ['currency' => 'USD'])]],
            'empty body' => [[]],
        ];
    }

    public function test_fawaterak_never_uses_a_browser_supplied_invoice_id(): void
    {
        [$order] = $this->fawaterak($this->invoice(['invoice_id' => 'SOMEONE-ELSE']));

        app(PaymentService::class)->refresh($order, 'SOMEONE-ELSE', 'fawaterak');

        Http::assertSent(fn ($r) => str_contains($r->url(), 'INV-9') && ! str_contains($r->url(), 'SOMEONE-ELSE'));
        $this->assertSame('pending_payment', $order->fresh()->status);
    }

    public function test_a_fawaterak_attempt_without_a_stored_invoice_id_can_not_be_settled(): void
    {
        $this->makeMethod('fawaterak', 'fawaterak_card', ['api_key' => 'k', 'provider_key' => '5']);
        [$order] = $this->openAttempt('fawaterak', 'fawaterak_card');
        Http::fake();

        app(PaymentService::class)->refresh($order, 'INV-9', 'fawaterak');

        Http::assertNothingSent();
        $this->assertSame('pending_payment', $order->fresh()->status);
    }

    public function test_fawaterak_refuses_to_open_a_payment_it_could_not_verify_later(): void
    {
        $method = $this->makeMethod('fawaterak', 'fawaterak_card', ['api_key' => 'k', 'provider_key' => '5']);
        $order = $this->makePendingOrder($this->user, null, 1, 100, 'fawaterak_card');
        Http::fake(['app.fawaterk.com/*' => Http::response(['status' => 'success', 'data' => ['payment_data' => ['redirectTo' => 'https://pay.example/x']]])]);

        $this->expectExceptionMessage('did not return an invoice id');
        try {
            app(PaymentService::class)->start($order, $method);
        } finally {
            $this->assertSame('failed', $order->paymentAttempts()->first()->status);
        }
    }

    public function test_fawaterak_stays_off_in_production_until_it_is_marked_verified(): void
    {
        $this->env('production');
        config(['payments.fawaterak.verified' => false]);
        $this->makeMethod('fawaterak', 'fawaterak_card', ['api_key' => 'k', 'provider_key' => '5']);
        [$order] = $this->openAttempt('fawaterak', 'fawaterak_card', ['transaction_id' => 'INV-9']);
        Http::fake();

        $this->assertNotContains('fawaterak_card', $this->codes());
        $this->assertSame(PaymentService::OUTCOME_PENDING, app(PaymentService::class)->refresh($order));
        $this->actingAs($this->user)->get(route('front.orders.payment.gateway', ['order' => $order, 'token' => $order->payment_link_token, 'method' => 'fawaterak_card']))
            ->assertRedirect(route('front.orders.payment', ['order' => $order, 'token' => $order->payment_link_token]));
        Http::assertNothingSent();

        config(['payments.fawaterak.verified' => true]);
        $this->assertContains('fawaterak_card', $this->codes());
    }

    public function test_the_gateway_result_type_never_treats_unknown_as_paid(): void
    {
        $this->assertFalse(GatewayResult::unknown('x')->isPaid());
        $this->assertSame(PaymentService::OUTCOME_PENDING, app(PaymentService::class)->settle(
            PaymentAttempt::create(['order_id' => $this->makePendingOrder($this->user)->id, 'provider' => 'fake', 'amount' => 1, 'status' => 'initiated']),
            GatewayResult::unknown('network')
        ));
    }
}
