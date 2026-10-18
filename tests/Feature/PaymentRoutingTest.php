<?php

namespace Tests\Feature;

use App\Http\Middleware\EnsureAdminPanelAccess;
use App\Mail\OrderTicketsIssuedMail;
use App\Models\IntegrationAccount;
use App\Models\IssuedTicket;
use App\Models\Order;
use App\Models\PaymentAttempt;
use App\Models\User;
use App\Services\Payments\PaymentService;
use App\Services\Payments\Routing\PaymentAccountResolver;
use App\Services\Payments\Routing\PaymentAccountSwitcher;
use App\Support\SystemSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Permission;
use Tests\Support\BuildsMerchantAccounts;
use Tests\Support\BuildsOrders;
use Tests\TestCase;

/** Multi-merchant routing: account binding, credentials per account, callbacks, reconciliation, switching. Mock gateways only. */
class PaymentRoutingTest extends TestCase
{
    use BuildsMerchantAccounts, BuildsOrders, RefreshDatabase;

    private IntegrationAccount $A;
    private IntegrationAccount $B;
    private User $customer;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        $this->fakeProviders();
        $this->customer = User::factory()->create();
        $this->makeMethod('paymob', 'paymob_card', ['api_key' => 'LEGACY-KEY', 'iframe_id' => 'LEGACY-IF', 'integration_id' => 'LEGACY-INT']);
        $this->A = $this->account('paymob', 'A');
        $this->B = $this->account('paymob', 'B');
    }

    private function agent(?IntegrationAccount $account, array $o = []): User
    {
        $u = User::factory()->create();
        $u->forceFill($o + ['payment_routing_mode' => $account ? 'custom' : 'default', 'payment_account_id' => $account?->id, 'payment_routing_enabled' => (bool) $account, 'can_receive_orders' => true])->save();

        return $u->fresh();
    }

    private function order(?User $agent = null, string $method = 'paymob_card', bool $pin = true): Order
    {
        $o = $this->makePendingOrder($this->customer, null, 2, 150, $method);
        if ($agent) {
            // Merchant accounts are no longer chosen by the agent. Where a test needs an order on a particular account, it is
            // pinned to it explicitly (what the "switch payment account" action does); the agent only drives the visible methods.
            $o->forceFill(['assigned_user_id' => $agent->id, 'integration_account_id' => $pin ? $agent->payment_account_id : null])->save();
        }

        return $o->fresh();
    }

    private function start(Order $order, string $code = 'paymob_card'): PaymentAttempt
    {
        $method = \App\Models\PaymentMethod::where('code', $code)->firstOrFail();
        app(PaymentService::class)->start($order->fresh(), $method);

        return $order->paymentAttempts()->latest('id')->firstOrFail();
    }

    private function routingOn(): void
    {
        SystemSettings::set('payment_routing_enabled', true);
    }

    /* ---------------------------------------------------------------- legacy / defaults */

    public function test_nothing_configured_keeps_the_legacy_path_and_binds_nothing(): void
    {
        $attempt = $this->start($this->order());

        $this->assertNull($attempt->integration_account_id);
        $this->assertNull($attempt->order->fresh()->integration_account_id);
        $keys = collect($this->httpLog)->pluck('key')->filter()->unique()->all();
        $this->assertSame(['LEGACY-KEY'], $keys, 'legacy credentials from the payment method are still used');
    }

    public function test_default_account_without_routing_is_used_for_new_attempts(): void
    {
        SystemSettings::set('default_payment_account_id', $this->A->id);
        $attempt = $this->start($this->order());

        $this->assertSame($this->A->id, $attempt->integration_account_id);
        $this->assertContains('KEY-A', collect($this->httpLog)->pluck('key')->filter()->all());
        $this->assertNotContains('LEGACY-KEY', collect($this->httpLog)->pluck('key')->all());
    }

    public function test_a_users_old_custom_account_setting_never_chooses_the_merchant_account(): void
    {
        // Per-agent merchant accounts no longer exist: whatever an older save left on the user row is ignored, routing on or off.
        $agent = $this->agent($this->B);
        SystemSettings::set('default_payment_account_id', $this->A->id);

        $this->assertSame($this->A->id, $this->start($this->order($agent, pin: false))->integration_account_id);

        $this->routingOn();
        $this->assertSame($this->A->id, $this->start($this->order($agent, pin: false))->integration_account_id);
    }

    /* ---------------------------------------------------------------- routing per user */

    public function test_every_agent_pays_into_the_same_account_but_customers_see_only_their_agents_methods(): void
    {
        $this->routingOn();
        $this->makeMethod('paymob', 'paymob_wallet', ['api_key' => 'x', 'iframe_id' => 'x', 'integration_id' => 'x']);
        SystemSettings::set('default_payment_account_id', $this->A->id);
        $cards = $this->agent(null, ['allowed_payment_methods' => ['paymob_card']]);
        $wallets = $this->agent(null, ['allowed_payment_methods' => ['paymob_wallet']]);
        $everything = $this->agent(null);

        $offered = fn (Order $o) => app(\App\Services\Payments\Routing\OfferedMethods::class)
            ->for(app(PaymentAccountResolver::class)->forOrder($o))->pluck('code')->all();

        $this->assertSame(['paymob_card'], $offered($this->order($cards, pin: false)));
        $this->assertSame(['paymob_wallet'], $offered($this->order($wallets, pin: false)));
        foreach ([$this->order($everything, pin: false), $this->order(null)] as $unrestricted) {   // no selection / unassigned = every active method
            $this->assertEqualsCanonicalizing([], array_diff(['paymob_card', 'paymob_wallet'], $offered($unrestricted)));
            $this->assertGreaterThan(1, count($offered($unrestricted)));
        }

        // Same merchant account for everyone; the method the agent does not offer cannot be opened either.
        $a1 = $this->start($this->order($cards, pin: false));
        $a2 = $this->start($this->order($wallets, 'paymob_wallet', pin: false), 'paymob_wallet');
        $this->assertSame($this->A->id, $a1->integration_account_id);
        $this->assertSame($this->A->id, $a2->integration_account_id);

        $this->expectException(\RuntimeException::class);
        $this->start($this->order($cards, 'paymob_wallet', pin: false), 'paymob_wallet');
    }

    public function test_reassigning_an_order_changes_the_methods_its_customer_sees(): void
    {
        $this->routingOn();
        $this->makeMethod('paymob', 'paymob_wallet', ['api_key' => 'x', 'iframe_id' => 'x', 'integration_id' => 'x']);
        SystemSettings::set('default_payment_account_id', $this->A->id);
        $cards = $this->agent(null, ['allowed_payment_methods' => ['paymob_card']]);
        $wallets = $this->agent(null, ['allowed_payment_methods' => ['paymob_wallet']]);
        $order = $this->order($cards, pin: false);
        $offered = fn () => app(\App\Services\Payments\Routing\OfferedMethods::class)
            ->for(app(PaymentAccountResolver::class)->forOrder($order->fresh()))->pluck('code')->all();

        $this->assertSame(['paymob_card'], $offered());
        $order->forceFill(['assigned_user_id' => $wallets->id])->save();
        $this->assertSame(['paymob_wallet'], $offered());
    }

    public function test_an_order_pinned_to_an_account_still_follows_its_agents_methods(): void
    {
        $this->routingOn();
        $this->makeMethod('paymob', 'paymob_wallet', ['api_key' => 'x', 'iframe_id' => 'x', 'integration_id' => 'x']);
        $cards = $this->agent(null, ['allowed_payment_methods' => ['paymob_card']]);
        $order = $this->order($cards, pin: false);
        $order->forceFill(['integration_account_id' => $this->B->id])->save();

        $this->assertSame(['paymob_card'], app(\App\Services\Payments\Routing\OfferedMethods::class)
            ->for(app(PaymentAccountResolver::class)->forOrder($order->fresh()))->pluck('code')->all());
    }

    public function test_methods_not_supported_by_the_account_are_not_offered_or_openable(): void
    {
        $this->routingOn();
        $this->makeMethod('paymob', 'paymob_wallet', ['api_key' => 'x', 'iframe_id' => 'x', 'integration_id' => 'x']);
        $narrow = $this->account('paymob', 'Narrow', ['supported_methods' => 'paymob_card']);
        $agent = $this->agent($narrow);
        $order = $this->order($agent, 'paymob_wallet');

        $this->expectException(\RuntimeException::class);
        $this->start($order, 'paymob_wallet');
    }

    public function test_agent_level_allowed_methods_restrict_the_offer(): void
    {
        $this->routingOn();
        $this->makeMethod('paymob', 'paymob_wallet', ['api_key' => 'x', 'iframe_id' => 'x', 'integration_id' => 'x']);
        $agent = $this->agent($this->A, ['allowed_payment_methods' => ['paymob_card']]);
        $offered = app(\App\Services\Payments\Routing\OfferedMethods::class)->for(app(PaymentAccountResolver::class)->forOrder($this->order($agent)));

        $this->assertSame(['paymob_card'], $offered->pluck('code')->all());
    }

    public function test_unassigned_policy_default_account_vs_block_online(): void
    {
        $this->routingOn();
        SystemSettings::set('default_payment_account_id', $this->B->id);
        $unassigned = $this->order();
        $this->assertSame($this->B->id, $this->start($unassigned)->integration_account_id);

        SystemSettings::set('unassigned_order_payment_policy', 'block_online');
        $this->expectException(\RuntimeException::class);
        $this->start($this->order());
    }

    public function test_an_agent_whose_methods_are_all_unavailable_never_leaves_the_customer_unable_to_pay(): void
    {
        $this->routingOn();
        SystemSettings::set('default_payment_account_id', $this->A->id);
        $agent = $this->agent(null, ['allowed_payment_methods' => ['paymob_wallet']]);   // that method does not exist / is not active here

        $offered = app(\App\Services\Payments\Routing\OfferedMethods::class)
            ->for(app(PaymentAccountResolver::class)->forOrder($this->order($agent, pin: false)))->pluck('code')->all();

        $this->assertContains('paymob_card', $offered);
        $this->assertSame($this->A->id, $this->start($this->order($agent, pin: false))->integration_account_id);
    }

    public function test_missing_or_invalid_credentials_never_open_a_payment(): void
    {
        $this->routingOn();
        $broken = $this->account('paymob', 'Broken', [], ['api_key' => ''], ['credential_fingerprint' => 'x']);
        $order = $this->order($this->agent($broken));

        try {
            $this->start($order);
            $this->fail('expected refusal');
        } catch (\RuntimeException) {
        }
        $this->assertSame(0, $order->paymentAttempts()->count());
        $this->assertEmpty(array_filter($this->httpLog, fn ($r) => str_contains($r['url'], 'auth/tokens')));
    }

    /* ---------------------------------------------------------------- immutability / reassignment / retry */

    public function test_attempt_and_order_binding_are_immutable(): void
    {
        $this->routingOn();
        $attempt = $this->start($this->order($this->agent($this->A)));

        try {
            $attempt->update(['integration_account_id' => $this->B->id]);
            $this->fail('attempt binding changed');
        } catch (\LogicException) {
        }
        try {
            $attempt->order->update(['integration_account_id' => $this->B->id]);
            $this->fail('order pin changed');
        } catch (\LogicException) {
        }
        $this->assertSame($this->A->id, $attempt->fresh()->integration_account_id);
    }

    public function test_an_account_with_history_cannot_be_deleted_even_at_database_level(): void
    {
        $this->routingOn();
        $this->start($this->order($this->agent($this->A)));

        $this->assertTrue($this->A->isReferenced());
        $this->expectException(\Illuminate\Database\QueryException::class);
        $this->A->delete();
    }

    public function test_reassignment_never_changes_the_account_and_a_live_session_is_reused_not_mixed(): void
    {
        $this->routingOn();
        $ahmed = $this->agent($this->A);
        $mohamed = $this->agent($this->B);
        $order = $this->order($ahmed);
        $first = $this->start($order);

        $order->forceFill(['assigned_user_id' => $mohamed->id])->save(); // reassigned during payment
        $count = count($this->httpLog);
        $again = $this->start($order);

        $this->assertSame($first->id, $again->id, 'same live session reused');
        $this->assertCount($count, $this->httpLog, 'no new call to any gateway');
        $this->assertSame($this->A->id, $order->fresh()->integration_account_id);
        $this->assertSame(1, $order->paymentAttempts()->count());
    }

    public function test_retry_after_failure_stays_on_the_same_account_even_after_reassignment(): void
    {
        $this->routingOn();
        $ahmed = $this->agent($this->A);
        $mohamed = $this->agent($this->B);
        $order = $this->order($ahmed);
        $first = $this->start($order);
        $first->forceFill(['status' => 'failed', 'failure_reason' => 'declined'])->save();

        $order->forceFill(['assigned_user_id' => $mohamed->id])->save();
        $retry = $this->start($order);

        $this->assertNotSame($first->id, $retry->id);
        $this->assertSame($this->A->id, $retry->integration_account_id);
    }

    public function test_legacy_attempt_stays_legacy_even_when_routing_and_defaults_are_turned_on_later(): void
    {
        $order = $this->order();
        $legacy = $this->start($order);
        $this->assertNull($legacy->integration_account_id);

        $this->routingOn();
        SystemSettings::set('default_payment_account_id', $this->B->id);
        $legacy->forceFill(['status' => 'failed'])->save();
        $retry = $this->start($order->fresh());

        $this->assertNull($retry->integration_account_id, 'sticky to the legacy path: no silent merchant change');
    }

    public function test_turning_the_switch_off_shows_every_method_to_everyone_again(): void
    {
        $this->routingOn();
        $this->makeMethod('paymob', 'paymob_wallet', ['api_key' => 'x', 'iframe_id' => 'x', 'integration_id' => 'x']);
        $cards = $this->agent(null, ['allowed_payment_methods' => ['paymob_card']]);
        $order = $this->order($cards, pin: false);
        $offered = fn () => app(\App\Services\Payments\Routing\OfferedMethods::class)
            ->for(app(PaymentAccountResolver::class)->forOrder($order->fresh()))->pluck('code')->all();

        $this->assertSame(['paymob_card'], $offered());

        SystemSettings::set('payment_routing_enabled', false);
        $this->assertEqualsCanonicalizing([], array_diff(['paymob_card', 'paymob_wallet'], $offered()));
        $this->assertGreaterThan(1, count($offered()));
    }

    /* ---------------------------------------------------------------- disabled accounts, inquiry, reconcile, callbacks */

    public function test_disabled_account_blocks_new_attempts_but_old_ones_still_settle(): void
    {
        $this->routingOn();
        $order = $this->order($this->agent($this->A));
        $attempt = $this->start($order);
        $this->A->forceFill(['enabled' => false])->save();

        // new attempts on this order are refused (pinned account unavailable)...
        $attempt->forceFill(['status' => 'failed'])->save();
        try {
            $this->start($order->fresh());
            $this->fail('new attempt on a disabled account');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('pinned_account_unavailable', $e->getMessage());
        }

        // ...but the earlier session is still verified with the account's own credentials and settles.
        $attempt->forceFill(['status' => 'initiated'])->save();
        $this->paymobPays($attempt, 'KEY-A', 'T1');
        $outcome = app(PaymentService::class)->refreshAttempt($attempt->fresh(), 'T1');

        $this->assertSame('paid', $outcome);
        $this->assertSame('paid', $order->fresh()->status);
    }

    public function test_inquiry_uses_the_attempts_own_account_never_the_current_method_config(): void
    {
        $this->routingOn();
        $order = $this->order($this->agent($this->A));
        $attempt = $this->start($order);
        // the legacy method config is rotated to something else afterwards
        \App\Models\PaymentMethod::where('code', 'paymob_card')->update(['config' => json_encode(['api_key' => 'ROTATED', 'iframe_id' => '1', 'integration_id' => '1'])]);
        $this->paymobPays($attempt, 'KEY-A', 'T2');
        $this->httpLog = [];

        $this->assertSame('paid', app(PaymentService::class)->refreshAttempt($attempt->fresh(), 'T2'));
        $this->assertNotContains('ROTATED', collect($this->httpLog)->pluck('key')->all());
        $this->assertContains('KEY-A', collect($this->httpLog)->pluck('key')->all());
    }

    public function test_a_transaction_of_account_b_cannot_settle_an_attempt_of_account_a(): void
    {
        $this->routingOn();
        $orderA = $this->order($this->agent($this->A));
        $attemptA = $this->start($orderA);
        $this->paymobPays($attemptA, 'KEY-B', 'FOREIGN'); // exists only under merchant B

        $this->assertSame('pending', app(PaymentService::class)->refreshAttempt($attemptA->fresh(), 'FOREIGN'));
        $this->assertSame('pending_payment', $orderA->fresh()->status);
    }

    public function test_paymob_callback_is_verified_with_the_attempts_account_secret(): void
    {
        $this->routingOn();
        config(['payments.paymob.hmac_secret' => 'GLOBAL-SECRET']);
        $orderA = $this->order($this->agent($this->A));
        $attemptA = $this->start($orderA);
        $this->paymobPays($attemptA, 'KEY-A', 'CB1');
        $obj = $this->paymobCallbackObj($attemptA, 'CB1');

        // signed with B's secret or the global one: rejected, nothing changes
        foreach (['HMAC-B', 'GLOBAL-SECRET', 'nope'] as $wrong) {
            $this->postJson(route('front.paymob.callback').'?hmac='.$this->paymobSign($obj, $wrong), ['obj' => $obj])->assertStatus(403);
        }
        $this->assertSame('pending_payment', $orderA->fresh()->status);

        $this->postJson(route('front.paymob.callback').'?hmac='.$this->paymobSign($obj, 'HMAC-A'), ['obj' => $obj])->assertOk()->assertJson(['updated' => true]);
        $this->assertSame('paid', $orderA->fresh()->status);
    }

    public function test_two_accounts_settle_independently_and_duplicate_callbacks_issue_tickets_once(): void
    {
        $this->routingOn();
        $oA = $this->order($this->agent($this->A));
        $oB = $this->order($this->agent($this->B));
        $aA = $this->start($oA);
        $aB = $this->start($oB);
        $this->paymobPays($aA, 'KEY-A', 'TA');
        $this->paymobPays($aB, 'KEY-B', 'TB');

        foreach ([[$aA, 'TA', 'HMAC-A'], [$aB, 'TB', 'HMAC-B']] as [$att, $tx, $secret]) {
            $obj = $this->paymobCallbackObj($att, $tx);
            for ($i = 0; $i < 3; $i++) { // duplicate deliveries
                $this->postJson(route('front.paymob.callback').'?hmac='.$this->paymobSign($obj, $secret), ['obj' => $obj])->assertOk();
            }
        }

        $this->assertSame('paid', $oA->fresh()->status);
        $this->assertSame('paid', $oB->fresh()->status);
        $this->assertSame(2, IssuedTicket::where('order_id', $oA->id)->count());
        $this->assertSame(2, IssuedTicket::where('order_id', $oB->id)->count());
        Mail::assertSent(OrderTicketsIssuedMail::class, 2);
        $this->assertSame(1, PaymentAttempt::where('order_id', $oA->id)->where('status', 'paid')->count());
    }

    public function test_amount_mismatch_is_rejected_for_account_attempts(): void
    {
        $this->routingOn();
        $order = $this->order($this->agent($this->A));
        $attempt = $this->start($order);
        $this->paymobPays($attempt, 'KEY-A', 'TX', ['amount_cents' => 100]);

        $this->assertSame('mismatch', app(PaymentService::class)->refreshAttempt($attempt->fresh(), 'TX'));
        $this->assertSame('pending_payment', $order->fresh()->status);
    }

    public function test_late_payment_on_a_cancelled_order_is_flagged_not_fulfilled(): void
    {
        $this->routingOn();
        $order = $this->order($this->agent($this->A));
        $attempt = $this->start($order);
        $order->forceFill(['status' => 'canceled'])->saveQuietly();
        $this->paymobPays($attempt, 'KEY-A', 'LATE');

        $this->assertSame('closed_order', app(PaymentService::class)->refreshAttempt($attempt->fresh(), 'LATE'));
        $this->assertSame(0, IssuedTicket::where('order_id', $order->id)->count());
        $this->assertSame('order_closed', $attempt->fresh()->meta['needs_review']);
    }

    public function test_reconcile_job_uses_each_attempts_account(): void
    {
        $this->routingOn();
        $oA = $this->order($this->agent($this->A));
        $oB = $this->order($this->agent($this->B));
        $aA = $this->start($oA); $aB = $this->start($oB);
        PaymentAttempt::query()->update(['created_at' => now()->subMinutes(10), 'transaction_id' => null]);
        $aA->forceFill(['transaction_id' => 'RA'])->saveQuietly(); $aB->forceFill(['transaction_id' => 'RB'])->saveQuietly();
        $this->paymobPays($aA, 'KEY-A', 'RA'); $this->paymobPays($aB, 'KEY-B', 'RB');

        app(PaymentService::class)->reconcile();

        $this->assertSame('paid', $oA->fresh()->status);
        $this->assertSame('paid', $oB->fresh()->status);
    }

    /* ---------------------------------------------------------------- Fawaterak (mock) */

    public function test_fawaterak_accounts_are_isolated_in_sandbox_and_locked_in_production(): void
    {
        $this->routingOn();
        $fA = $this->account('fawaterak', 'FA');
        $fB = $this->account('fawaterak', 'FB');
        $this->makeMethod('fawaterak', 'fawaterak_card', ['api_key' => 'L', 'provider_key' => 'FAWATERAK.1']);
        $oA = $this->order($this->agent($fA), 'fawaterak_card');
        $oB = $this->order($this->agent($fB), 'fawaterak_card');

        config(['payments.fawaterak.verified' => true]); // sandbox attestation recorded by an operator
        $aA = $this->start($oA, 'fawaterak_card');
        $aB = $this->start($oB, 'fawaterak_card');
        $this->assertSame($fA->id, $aA->integration_account_id);
        $this->assertSame($fB->id, $aB->integration_account_id);
        $this->assertNotSame($aA->transaction_id, $aB->transaction_id);

        // an invoice paid under account B can never settle account A's attempt
        $this->fawInvoices[$aA->transaction_id]['paid'] = false;
        $this->fawInvoices[$aB->transaction_id]['paid'] = true;
        $this->assertSame('pending', app(PaymentService::class)->refreshAttempt($aA->fresh()));
        $this->assertSame('paid', app(PaymentService::class)->refreshAttempt($aB->fresh()));

        // production without the verification flag: nothing is offered or opened
        config(['payments.fawaterak.verified' => false]);
        $this->app->detectEnvironment(fn () => 'production');
        $this->assertFalse(PaymentAccountResolver::usable($fA));
    }

    public function test_unusable_default_account_falls_back_to_the_methods_own_configuration(): void
    {
        SystemSettings::set('default_payment_account_id', $this->A->id);
        $this->A->forceFill(['enabled' => false])->save();
        $order = $this->order();

        $this->actingAs($this->customer)->get(route('front.orders.payment', ['order' => $order, 'token' => $order->payment_link_token]))
            ->assertOk()->assertDontSee('No payment methods are available');
    }

    public function test_empty_checkout_explains_itself_to_payment_admins_only(): void
    {
        \App\Models\PaymentMethod::query()->update(['is_active' => false]);
        $order = $this->order();
        $url = route('front.orders.payment', ['order' => $order, 'token' => $order->payment_link_token]);

        $this->actingAs($this->customer)->get($url)->assertOk()
            ->assertSee('No payment methods are available')->assertDontSee('Admin only');

        Permission::findOrCreate('payments.transactions.view', 'web');
        $this->customer->givePermissionTo('payments.transactions.view');
        $this->actingAs($this->customer->fresh())->get($url)->assertOk()->assertSee('Admin only')->assertSee('No payment method is active');
    }
}
