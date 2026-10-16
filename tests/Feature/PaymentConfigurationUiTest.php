<?php

namespace Tests\Feature;

use App\Http\Middleware\EnsureAdminPanelAccess;
use App\Integrations\Definitions;
use App\Models\IntegrationAccount;
use App\Models\User;
use App\Support\SystemSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\Support\BuildsMerchantAccounts;
use Tests\TestCase;

class PaymentConfigurationUiTest extends TestCase
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

    private function body(User $u, array $extra = []): array
    {
        return ['name' => $u->name, 'username' => $u->username, 'email' => $u->email] + $extra;
    }

    public function test_routing_and_default_account_are_off_by_default(): void
    {
        $this->assertFalse((bool) SystemSettings::get('payment_routing_enabled'));
        $this->assertNull(SystemSettings::get('default_payment_account_id'));
        $this->assertSame('default_account', SystemSettings::get('unassigned_order_payment_policy'));
    }

    public function test_only_authorised_staff_can_change_a_users_payment_configuration_and_keys_are_never_shown(): void
    {
        $acc = $this->account('paymob', 'Main');
        $target = User::factory()->create();
        $editor = $this->staff(['users.update', 'users.view', 'settings.payment-accounts.manage']);
        $plain = $this->staff(['users.update', 'users.view']);

        // not authorised: the section is absent and a forged request is ignored
        $this->actingAs($plain)->get(route('admin.users.edit', $target))->assertOk()->assertDontSee('Payment configuration');
        $this->actingAs($plain)->put(route('admin.users.update', $target), $this->body($target, ['payment_flags' => 1, 'payment_routing_mode' => 'custom', 'payment_account_id' => $acc->id]))->assertRedirect();
        $this->assertSame('default', $target->fresh()->payment_routing_mode);

        // authorised
        $page = $this->actingAs($editor)->get(route('admin.users.edit', $target))->assertOk()->assertSee('Payment configuration')->assertSee('Main');
        $page->assertDontSee('KEY-Main')->assertDontSee('HMAC-Main');

        $this->actingAs($editor)->put(route('admin.users.update', $target), $this->body($target, [
            'payment_flags' => 1, 'payment_routing_mode' => 'custom', 'payment_account_id' => $acc->id, 'payment_routing_enabled' => 1, 'allowed_payment_methods' => ['paymob_card'],
        ]))->assertRedirect();
        $t = $target->fresh();
        $this->assertSame([$acc->id, 'custom', true, ['paymob_card']], [$t->payment_account_id, $t->payment_routing_mode, $t->payment_routing_enabled, $t->allowed_payment_methods]);
        $this->assertDatabaseHas('activity_log', ['description' => 'User payment configuration changed']);

        // custom needs a real, non-archived account; a request without the marker never touches the setting
        $this->actingAs($editor)->put(route('admin.users.update', $target), $this->body($target, ['payment_flags' => 1, 'payment_routing_mode' => 'custom']))->assertSessionHasErrors('payment_account_id');
        $this->actingAs($editor)->put(route('admin.users.update', $target), $this->body($target))->assertRedirect();
        $this->assertSame('custom', $target->fresh()->payment_routing_mode);
    }

    public function test_default_merchant_account_must_be_usable_and_only_managers_save_payment_settings(): void
    {
        config(['payments.live_gateways.enabled' => true]);
        $ok = $this->account('paymob', 'Ready');
        $off = $this->account('paymob', 'Off', [], [], ['enabled' => false]);
        $manager = $this->staff(['settings.view', 'settings.update', 'settings.payment-accounts.view', 'settings.payment-accounts.manage']);
        $viewer = $this->staff(['settings.view', 'settings.update', 'settings.payment-accounts.view']);

        $this->actingAs($manager)->get(route('admin.settings.section', 'payments'))->assertOk()->assertSee('Payment account status')->assertSee('Ready');
        $this->actingAs($manager)->put(route('admin.settings.section.update', 'payments'), ['default_payment_account_id' => $off->id, 'unassigned_order_payment_policy' => 'default_account'])->assertSessionHasErrors('default_payment_account_id');
        $this->actingAs($viewer)->put(route('admin.settings.section.update', 'payments'), ['default_payment_account_id' => $ok->id, 'unassigned_order_payment_policy' => 'default_account'])->assertForbidden();
        $this->actingAs($manager)->put(route('admin.settings.section.update', 'payments'), ['default_payment_account_id' => $ok->id, 'payment_routing_enabled' => 1, 'unassigned_order_payment_policy' => 'block_online'])->assertRedirect();

        $this->assertTrue((bool) SystemSettings::get('payment_routing_enabled'));
        $this->assertSame($ok->id, (int) SystemSettings::get('default_payment_account_id'));
        $this->assertSame('block_online', SystemSettings::get('unassigned_order_payment_policy'));
    }

    public function test_accounts_with_history_can_only_be_archived_and_archived_accounts_cannot_be_enabled(): void
    {
        $manager = $this->staff(['settings.integrations.view', 'settings.integrations.manage', 'settings.integrations.test']);
        $acc = $this->account('paymob', 'Used', [], [], ['enabled' => false]);
        $user = User::factory()->create();
        $user->forceFill(['payment_account_id' => $acc->id, 'payment_routing_mode' => 'custom'])->save();

        $this->actingAs($manager)->delete(route('admin.integrations.destroy', $acc))->assertSessionHas('error');
        $this->assertNotNull(IntegrationAccount::find($acc->id));

        $this->actingAs($manager)->post(route('admin.integrations.archive', $acc))->assertRedirect();
        $this->assertNotNull($acc->fresh()->archived_at);
        $this->actingAs($manager)->post(route('admin.integrations.enable', $acc))->assertSessionHasErrors('account');
        $this->assertFalse($acc->fresh()->enabled);
    }

    public function test_the_fake_provider_exists_only_where_the_fake_gateway_may_run(): void
    {
        config(['payments.fake.enabled' => true]);
        $this->assertArrayHasKey('fake', Definitions::all());
        $this->app->detectEnvironment(fn () => 'production');
        $this->assertArrayNotHasKey('fake', Definitions::all());
    }

    public function test_fawaterak_sandbox_attestation_is_recorded_and_production_stays_locked(): void
    {
        $manager = $this->staff(['settings.integrations.view', 'settings.integrations.manage', 'settings.integrations.test']);
        $sb = $this->account('fawaterak', 'FSB', [], [], ['status' => 'unverified', 'enabled' => false]);
        $pr = $this->account('fawaterak', 'FPR', [], [], ['status' => 'unverified', 'enabled' => false, 'environment' => 'production']);

        $this->actingAs($manager)->post(route('admin.integrations.attest', $sb), ['note' => 'sandbox run 2026-11-12'])->assertRedirect();
        $this->assertSame('verified', $sb->fresh()->status);
        $this->actingAs($manager)->post(route('admin.integrations.attest', $pr), ['note' => 'sandbox run 2026-11-12'])->assertSessionHasErrors('account');

        $this->actingAs($manager)->post(route('admin.integrations.enable', $sb))->assertRedirect(); // sandbox account outside production
        $this->assertTrue($sb->fresh()->enabled);

        $pr->forceFill(['status' => 'verified'])->save();
        config(['payments.live_gateways.enabled' => true, 'payments.fawaterak.verified' => false]);
        $this->actingAs($manager)->post(route('admin.integrations.enable', $pr), ['confirm_production' => 1])->assertSessionHasErrors('account');
        $this->assertFalse($pr->fresh()->enabled);
    }
}
