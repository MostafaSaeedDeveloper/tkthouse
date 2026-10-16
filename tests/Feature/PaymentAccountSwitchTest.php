<?php

namespace Tests\Feature;

use App\Http\Middleware\EnsureAdminPanelAccess;
use App\Models\IntegrationAccount;
use App\Models\IssuedTicket;
use App\Models\Order;
use App\Models\PaymentAttempt;
use App\Models\User;
use App\Services\Payments\PaymentService;
use App\Services\Payments\Routing\PaymentAccountSwitcher;
use App\Support\SystemSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Permission;
use Tests\Support\BuildsMerchantAccounts;
use Tests\Support\BuildsOrders;
use Tests\TestCase;

/** Explicit merchant switching: the gateway is asked for real before an attempt is closed; refusals never change anything. */
class PaymentAccountSwitchTest extends TestCase
{
    use BuildsMerchantAccounts, BuildsOrders, RefreshDatabase;

    private IntegrationAccount $A;
    private IntegrationAccount $B;
    private User $staff;
    private Order $order;
    private PaymentAttempt $attempt;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(EnsureAdminPanelAccess::class);
        Mail::fake();
        $this->fakeProviders();
        SystemSettings::set('payment_routing_enabled', true);
        $this->makeMethod('paymob', 'paymob_card', ['api_key' => 'L', 'iframe_id' => '1', 'integration_id' => '1']);
        $this->A = $this->account('paymob', 'A');
        $this->B = $this->account('paymob', 'B');
        SystemSettings::set('default_payment_account_id', $this->A->id);

        foreach (['orders.reroute_payment', 'orders.view'] as $p) {
            Permission::findOrCreate($p, 'web');
        }
        $this->staff = User::factory()->create();
        $this->staff->givePermissionTo(['orders.reroute_payment', 'orders.view']);

        $customer = User::factory()->create();
        $this->order = $this->makePendingOrder($customer, null, 2, 150, 'paymob_card');
        app(PaymentService::class)->start($this->order, \App\Models\PaymentMethod::where('code', 'paymob_card')->first());
        $this->attempt = $this->order->paymentAttempts()->firstOrFail();
    }

    private function switchTo(IntegrationAccount $to, ?User $by = null): Order
    {
        return app(PaymentAccountSwitcher::class)->switch($this->order->fresh(), $to, $by ?? $this->staff, 'customer asked for another merchant');
    }

    private function age(int $minutes): void
    {
        $this->attempt->forceFill(['created_at' => now()->subMinutes($minutes)])->saveQuietly();
    }

    private function assertUntouched(): void
    {
        $this->assertSame($this->A->id, $this->order->fresh()->integration_account_id);
        $this->assertSame('initiated', $this->attempt->fresh()->status);
    }

    public function test_requires_the_dedicated_permission(): void
    {
        $nobody = User::factory()->create();
        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        $this->switchTo($this->B, $nobody);
    }

    public function test_refused_when_the_gateway_says_the_attempt_is_already_paid_and_the_money_is_recorded(): void
    {
        $this->attempt->forceFill(['transaction_id' => 'P1'])->saveQuietly();
        $this->paymobPays($this->attempt, 'KEY-A', 'P1');

        try {
            $this->switchTo($this->B);
            $this->fail('switched while paid');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('already paid', $e->errors()['account'][0]);
        }
        $this->assertSame('paid', $this->order->fresh()->status, 'the real payment was settled instead');
        $this->assertSame($this->A->id, $this->order->fresh()->integration_account_id);
    }

    public function test_refused_when_the_gateway_state_is_unknown(): void
    {
        $this->attempt->forceFill(['transaction_id' => 'GHOST'])->saveQuietly(); // gateway cannot find it => unknown
        $this->age(600);

        $this->expectException(ValidationException::class);
        try {
            $this->switchTo($this->B);
        } finally {
            $this->assertUntouched();
        }
    }

    public function test_refused_while_a_young_attempt_may_still_be_in_progress(): void
    {
        $this->age(5); // no transaction yet: the customer may be on the 3DS page right now
        $this->expectException(ValidationException::class);
        try {
            $this->switchTo($this->B);
        } finally {
            $this->assertUntouched();
        }
    }

    public function test_refused_for_unusable_target_wrong_status_or_unsupported_method(): void
    {
        $this->age(600);

        $disabled = $this->account('paymob', 'Off', [], [], ['enabled' => false]);
        $narrow = $this->account('paymob', 'Narrow', ['supported_methods' => 'paymob_wallet']);
        foreach ([$disabled, $narrow] as $bad) {
            try {
                $this->switchTo($bad);
                $this->fail('switched to an unusable target');
            } catch (ValidationException) {
            }
        }
        $this->order->forceFill(['status' => 'canceled'])->saveQuietly();
        try {
            $this->switchTo($this->B);
            $this->fail('switched a closed order');
        } catch (ValidationException) {
        }
        $this->assertSame($this->A->id, $this->order->fresh()->integration_account_id);
    }

    public function test_switch_after_a_clean_check_closes_old_session_pins_new_account_and_audits(): void
    {
        $this->age(600);
        $this->switchTo($this->B);

        $order = $this->order->fresh();
        $this->assertSame($this->B->id, $order->integration_account_id);
        $old = $this->attempt->fresh();
        $this->assertSame('expired', $old->status);
        $this->assertTrue($old->meta['rerouted']);
        $this->assertSame($this->A->id, $old->integration_account_id, 'history is never rewritten');

        $new = app(PaymentService::class)->start($order, \App\Models\PaymentMethod::where('code', 'paymob_card')->first());
        $this->assertStringContainsString('IF-B', $new);
        $this->assertSame($this->B->id, $order->paymentAttempts()->latest('id')->first()->integration_account_id);

        $log = \Spatie\Activitylog\Models\Activity::where('description', 'Payment account switched')->firstOrFail();
        $this->assertSame('A', $log->properties['from_account']);
        $this->assertSame('B', $log->properties['to_account']);
        $this->assertSame($this->staff->id, $log->causer_id);
    }

    public function test_late_payment_on_the_old_session_is_still_matched_and_the_second_payment_is_flagged_not_double_issued(): void
    {
        $this->attempt->forceFill(['transaction_id' => 'OLD-TX'])->saveQuietly();
        $this->age(600);
        // gateway says pending at switch time (customer abandoned 3DS), so the switch is allowed
        $this->paymobTx['OLD-TX'] = ['id' => 'OLD-TX', 'owner' => 'KEY-A', 'merchant_order_id' => $this->attempt->reference, 'amount_cents' => 30000, 'pending' => true, 'success' => false];
        $this->switchTo($this->B);

        $newUrl = app(PaymentService::class)->start($this->order->fresh(), \App\Models\PaymentMethod::where('code', 'paymob_card')->first());
        $newAttempt = $this->order->paymentAttempts()->latest('id')->first();

        // the customer pays the OLD session late; reconcile finds it through the old account
        $this->paymobTx['OLD-TX'] = ['id' => 'OLD-TX', 'owner' => 'KEY-A', 'merchant_order_id' => $this->attempt->reference, 'amount_cents' => 30000, 'success' => true];
        app(PaymentService::class)->refreshAttempt($this->attempt->fresh(), 'OLD-TX');
        $this->assertSame('paid', $this->order->fresh()->status);

        // ...and then also pays the new one: recorded for refund review, tickets are not issued twice
        $newAttempt->forceFill(['transaction_id' => 'NEW-TX'])->saveQuietly();
        $this->paymobPays($newAttempt, 'KEY-B', 'NEW-TX');
        $this->assertSame('already_paid', app(PaymentService::class)->refreshAttempt($newAttempt->fresh(), 'NEW-TX'));
        $this->assertSame('duplicate_payment', $newAttempt->fresh()->meta['needs_review']);
        $this->assertSame(2, IssuedTicket::where('order_id', $this->order->id)->count());
    }

    public function test_cannot_open_a_second_account_next_to_a_live_session(): void
    {
        // an unpinned legacy-style order with a live attempt on A: opening on B directly must be refused
        $this->attempt->forceFill(['created_at' => now()])->saveQuietly();
        $this->order->forceFill([])->saveQuietly();
        \DB::table('orders')->where('id', $this->order->id)->update(['integration_account_id' => null]);
        \DB::table('payment_attempts')->where('id', $this->attempt->id)->update(['status' => 'initiated']);
        SystemSettings::set('default_payment_account_id', $this->B->id);

        // sticky rule: the latest attempt's account (A) wins, so no parallel session on B is created
        app(PaymentService::class)->start($this->order->fresh(), \App\Models\PaymentMethod::where('code', 'paymob_card')->first());
        $this->assertSame(1, $this->order->paymentAttempts()->count());
        $this->assertSame($this->A->id, $this->order->paymentAttempts()->first()->integration_account_id);
    }

    public function test_http_switch_needs_permission_and_returns_the_refusal_message(): void
    {
        $this->age(5);
        $this->actingAs($this->staff)->post(route('admin.orders.payment-account.switch', $this->order), ['integration_account_id' => $this->B->id, 'reason' => 'because'])
            ->assertSessionHasErrors('account');
        $this->assertUntouched();

        $other = User::factory()->create();
        $other->givePermissionTo('orders.view');
        $this->actingAs($other)->post(route('admin.orders.payment-account.switch', $this->order), ['integration_account_id' => $this->B->id, 'reason' => 'because'])->assertForbidden();
    }
}
