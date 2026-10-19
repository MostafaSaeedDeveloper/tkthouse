<?php

namespace Tests\Feature;

use App\Http\Middleware\EnsureAdminPanelAccess;
use App\Models\Order;
use App\Models\PaymentMethod;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\Support\BuildsMerchantAccounts;
use Tests\TestCase;

class PaymentSettingsUnifiedTest extends TestCase
{
    use BuildsMerchantAccounts, RefreshDatabase;

    private function staff(array $perms): User
    {
        $this->withoutMiddleware(EnsureAdminPanelAccess::class);
        foreach ($perms as $p) {
            Permission::findOrCreate($p, 'web');
        }

        return tap(User::factory()->create())->givePermissionTo($perms);
    }

    public function test_payment_providers_moved_but_nothing_was_lost_and_old_links_redirect(): void
    {
        $u = $this->staff(['payments.accounts.view', 'settings.integrations.view', 'settings.view']);
        $this->account('paymob', 'Main');
        $this->account('ultramsg', 'WA');

        $this->actingAs($u)->get(route('admin.payments.providers'))->assertOk()->assertSee('Main')->assertDontSee('>WA<', false);
        $this->actingAs($u)->get(route('admin.integrations.index'))->assertOk()->assertSee('WA')->assertSee('moved to');
        $this->actingAs($u)->get(route('admin.integrations.index', ['provider' => 'paymob']))->assertRedirect(route('admin.payments.providers', ['provider' => 'paymob']));
        $this->assertDatabaseCount('integration_accounts', 2);
    }

    public function test_provider_management_needs_the_payment_permission_and_does_not_leak_to_messaging_accounts(): void
    {
        $payAdmin = $this->staff(['payments.accounts.view', 'payments.accounts.manage']);
        $sms = $this->account('ultramsg', 'WA2');
        $pm = $this->account('paymob', 'PM', [], [], ['enabled' => false]);

        $this->actingAs($payAdmin)->post(route('admin.integrations.archive', $pm))->assertRedirect();
        $this->actingAs($payAdmin)->post(route('admin.integrations.disable', $sms))->assertForbidden();
        $this->actingAs($this->staff(['payments.accounts.view']))->post(route('admin.integrations.archive', $pm))->assertForbidden();
    }

    public function test_one_default_account_setting_shared_with_routing(): void
    {
        config(['payments.live_gateways.enabled' => true]);
        $u = $this->staff(['payments.accounts.view', 'payments.accounts.manage']);
        $acc = $this->account('paymob', 'Def');
        $this->actingAs($u)->post(route('admin.integrations.default', $acc))->assertRedirect();
        $this->assertSame($acc->id, (int) \App\Support\SystemSettings::get('default_payment_account_id'));
        $this->actingAs($u)->get(route('admin.payments.providers'))->assertSee('Default account');
    }

    public function test_method_form_never_renders_stored_keys_and_blank_keeps_them(): void
    {
        $u = $this->staff(['payment-methods.view', 'payment-methods.update']);
        $m = PaymentMethod::updateOrCreate(['code' => 'paymob_x'], ['name' => 'PX', 'provider' => 'paymob', 'is_active' => true, 'config' => ['api_key' => 'SUPER-SECRET-KEY-1234', 'iframe_id' => '9', 'integration_id' => '77']]);

        $this->actingAs($u)->get(route('admin.payment-methods.edit', $m))->assertOk()->assertDontSee('SUPER-SECRET-KEY-1234')->assertSee('••••1234');
        $this->actingAs($u)->put(route('admin.payment-methods.update', $m), ['name' => 'PX', 'code' => 'paymob_x', 'provider' => 'paymob', 'is_active' => 1, 'paymob_api_key' => '', 'paymob_iframe_id' => '9', 'paymob_integration_id' => '77', 'sort_order' => 3])->assertRedirect();
        $m->refresh();
        $this->assertSame('SUPER-SECRET-KEY-1234', $m->config['api_key']);
        $this->assertSame(3, $m->sort_order);
    }

    public function test_duplicate_methods_are_refused_and_used_methods_cannot_be_deleted(): void
    {
        $u = $this->staff(['payment-methods.view', 'payment-methods.create', 'payment-methods.update', 'payment-methods.delete']);
        PaymentMethod::updateOrCreate(['code' => 'paymob_a'], ['name' => 'Paymob Card A', 'provider' => 'paymob', 'is_active' => true, 'config' => ['integration_id' => '555']]);
        $base = ['provider' => 'paymob', 'is_active' => 1, 'paymob_iframe_id' => '1'];

        $this->actingAs($u)->post(route('admin.payment-methods.store'), $base + ['name' => 'paymob card a', 'code' => 'paymob_b', 'paymob_integration_id' => '556'])->assertSessionHasErrors('name');
        $this->actingAs($u)->post(route('admin.payment-methods.store'), $base + ['name' => 'Other', 'code' => 'paymob_c', 'paymob_integration_id' => '555'])->assertSessionHasErrors('paymob_integration_id');
        $this->assertSame(0, PaymentMethod::whereIn('code', ['paymob_b', 'paymob_c'])->count());

        $customer = \App\Models\Customer::create(['first_name' => 'A', 'last_name' => 'B', 'email' => 'a@b.test', 'phone' => '1']);
        Order::create(['customer_id' => $customer->id, 'order_number' => '1111111111', 'status' => 'paid', 'payment_method' => 'paymob_a', 'total_amount' => 10]);
        $m = PaymentMethod::where('code', 'paymob_a')->first();
        $this->actingAs($u)->delete(route('admin.payment-methods.destroy', $m))->assertSessionHas('error');
        $this->assertNotNull(PaymentMethod::find($m->id));
    }

    public function test_methods_may_share_a_provider_method_id_when_the_api_key_differs_but_not_a_name(): void
    {
        $u = $this->staff(['payment-methods.view', 'payment-methods.create', 'payment-methods.update']);
        PaymentMethod::updateOrCreate(['code' => 'a_wallet'], ['name' => 'A-Wallet', 'provider' => 'fawaterak', 'is_active' => true, 'config' => ['api_key' => 'KEY-A', 'provider_key' => '27416']]);
        $base = ['provider' => 'fawaterak', 'is_active' => 1, 'fawaterak_provider_key' => '27416'];

        // Same provider method id, different API key (another merchant account) => allowed, and editing it again stays allowed.
        $this->actingAs($u)->post(route('admin.payment-methods.store'), $base + ['name' => 'B-Wallet', 'code' => 'b_wallet', 'fawaterak_api_key' => 'KEY-B'])->assertSessionHasNoErrors();
        $b = PaymentMethod::where('code', 'b_wallet')->firstOrFail();
        $this->assertSame('27416', $b->providerMethodIdentifier());
        $this->actingAs($u)->put(route('admin.payment-methods.update', $b), $base + ['name' => 'B-Wallet', 'code' => 'b_wallet', 'fawaterak_api_key' => ''])->assertSessionHasNoErrors();
        $this->assertSame('KEY-B', $b->fresh()->config['api_key']);

        // Same provider method id AND same API key => a real duplicate.
        $this->actingAs($u)->post(route('admin.payment-methods.store'), $base + ['name' => 'Other', 'code' => 'c_wallet', 'fawaterak_api_key' => 'KEY-A'])->assertSessionHasErrors('fawaterak_provider_key');
        // Same name is still refused.
        $this->actingAs($u)->post(route('admin.payment-methods.store'), $base + ['name' => 'a-wallet', 'code' => 'd_wallet', 'fawaterak_api_key' => 'KEY-D'])->assertSessionHasErrors('name');
        $this->assertSame(0, PaymentMethod::whereIn('code', ['c_wallet', 'd_wallet'])->count());
    }

    public function test_checkout_orders_methods_by_sort_order_and_existing_codes_are_untouched(): void
    {
        config(['payments.fake.enabled' => true]);
        PaymentMethod::updateOrCreate(['code' => 'fake_b'], ['name' => 'B', 'provider' => 'fake', 'is_active' => true, 'sort_order' => 2, 'config' => []]);
        PaymentMethod::updateOrCreate(['code' => 'fake_a'], ['name' => 'A', 'provider' => 'fake', 'is_active' => true, 'sort_order' => 1, 'config' => []]);
        $codes = app(\App\Services\Payments\PaymentGatewayManager::class)->available()->pluck('code')->all();
        $this->assertSame(['fake_a', 'fake_b'], array_values(array_intersect($codes, ['fake_a', 'fake_b'])));
        $this->assertContains('visa', PaymentMethod::pluck('code')->all(), 'legacy codes remain');
    }
}
