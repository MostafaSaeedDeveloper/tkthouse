<?php

namespace Tests\Feature;

use App\Http\Middleware\EnsureAdminPanelAccess;
use App\Models\PaymentMethod;
use App\Models\User;
use App\Services\Payments\PaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Spatie\Permission\Models\Permission;
use Tests\Support\BuildsMerchantAccounts;
use Tests\Support\BuildsOrders;
use Tests\TestCase;

class OrderPaymentInformationTest extends TestCase
{
    use BuildsMerchantAccounts, BuildsOrders, RefreshDatabase;

    private function staff(array $perms): User
    {
        $this->withoutMiddleware(EnsureAdminPanelAccess::class);
        foreach ($perms as $p) {
            Permission::findOrCreate($p, 'web');
        }

        return tap(User::factory()->create())->givePermissionTo($perms);
    }

    public function test_order_details_show_payment_information_history_and_actions_by_permission(): void
    {
        Mail::fake();
        $this->fakeProviders();
        $this->makeMethod('paymob', 'paymob_card', ['api_key' => 'L', 'iframe_id' => '1', 'integration_id' => '1']);
        $acc = $this->account('paymob', 'Events Merchant');
        $order = $this->makePendingOrder(User::factory()->create(), null, 2, 150, 'paymob_card');
        $order->forceFill(['integration_account_id' => $acc->id])->saveQuietly();
        app(PaymentService::class)->start($order->fresh(), PaymentMethod::where('code', 'paymob_card')->first());
        $a = $order->paymentAttempts()->first();

        $viewer = $this->staff(['orders.view']);
        $this->actingAs($viewer)->get(route('admin.orders.show', $order))->assertOk()->assertSee('Payment Information')->assertSee($a->internal_reference)->assertSee('Payment Attempts History')
            ->assertDontSee('Events Merchant')->assertDontSee('Check payment status')->assertDontSee('Refund');

        $admin = $this->staff(['orders.view', 'payments.transactions.view', 'payments.transactions.inquire', 'payments.accounts.view']);
        $this->actingAs($admin)->get(route('admin.orders.show', $order))->assertOk()->assertSee('Events Merchant')->assertSee('Check payment status')->assertSee('View transaction details');
    }

    public function test_orders_without_attempts_or_legacy_attempts_render(): void
    {
        $viewer = $this->staff(['orders.view']);
        $o = $this->makePendingOrder(User::factory()->create(), null, 1, 100, 'cash');
        $this->actingAs($viewer)->get(route('admin.orders.show', $o))->assertOk()->assertSee('No online payment yet');

        \App\Models\PaymentAttempt::create(['order_id' => $o->id, 'provider' => 'paymob', 'method_code' => 'paymob_card', 'amount' => 100, 'currency' => 'EGP', 'status' => 'paid', 'transaction_id' => 'LEGACY-1']);
        $this->actingAs($viewer)->get(route('admin.orders.show', $o))->assertOk()->assertSee('LEGACY-1')->assertSee('Legacy attempt');
    }
}
