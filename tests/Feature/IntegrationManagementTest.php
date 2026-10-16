<?php

namespace Tests\Feature;

use App\Http\Middleware\EnsureAdminPanelAccess;
use App\Integrations\ConnectionTester;
use App\Integrations\IntegrationManager;
use App\Models\IntegrationAccount;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Permission;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

class IntegrationManagementTest extends TestCase
{
    use RefreshDatabase;

    private const SECRET = 'sk_live_SUPERSECRET_123456789';

    private function manager(): User
    {
        $this->withoutMiddleware(EnsureAdminPanelAccess::class);
        $perms = ['settings.view', 'settings.update', 'settings.integrations.view', 'settings.integrations.manage', 'settings.integrations.test'];
        foreach ($perms as $p) {
            Permission::findOrCreate($p, 'web');
        }

        return tap(User::factory()->create())->givePermissionTo($perms);
    }

    private function create(User $u, array $extra = []): IntegrationAccount
    {
        $this->actingAs($u)->post(route('admin.integrations.store', 'paymob'), array_merge([
            'name' => 'Paymob — Main', 'environment' => 'sandbox', 'api_key' => self::SECRET, 'hmac_secret' => 'hmac-secret-value-xyz', 'iframe_id' => '123',
        ], $extra))->assertRedirect();

        return IntegrationAccount::firstOrFail();
    }

    public function test_secrets_are_encrypted_at_rest_masked_and_never_rendered(): void
    {
        $u = $this->manager();
        $account = $this->create($u);

        $raw = DB::table('integration_accounts')->where('id', $account->id)->value('secrets');
        $this->assertStringNotContainsString(self::SECRET, (string) $raw);
        $this->assertSame(self::SECRET, $account->fresh()->secret('api_key'));

        $page = $this->actingAs($u)->get(route('admin.payments.providers'))->assertOk();
        $page->assertDontSee(self::SECRET)->assertDontSee('hmac-secret-value-xyz')->assertSee('••••');
        $this->assertStringNotContainsString(self::SECRET, json_encode($account->fresh()->toArray()));

        $this->assertStringNotContainsString(self::SECRET, Activity::query()->get()->toJson());
    }

    public function test_blank_secret_keeps_old_value_and_credential_change_disables_account(): void
    {
        $u = $this->manager();
        $account = $this->create($u);
        $account->forceFill(['status' => 'verified', 'enabled' => true])->save();

        $this->actingAs($u)->put(route('admin.integrations.update', $account), ['name' => 'Paymob — Main', 'environment' => 'sandbox', 'api_key' => '', 'iframe_id' => '123', 'hmac_secret' => 'hmac-secret-value-xyz'])->assertRedirect();
        $account->refresh();
        $this->assertSame(self::SECRET, $account->secret('api_key'));
        $this->assertTrue($account->enabled, 'unchanged credentials keep the verified state');

        $this->actingAs($u)->put(route('admin.integrations.update', $account), ['name' => 'Paymob — Main', 'environment' => 'sandbox', 'api_key' => 'brand-new-key-0000', 'iframe_id' => '123'])->assertRedirect();
        $account->refresh();
        $this->assertSame('brand-new-key-0000', $account->secret('api_key'));
        $this->assertFalse($account->enabled);
        $this->assertSame('unverified', $account->status);
    }

    public function test_cannot_enable_without_a_successful_test_and_saving_never_enables(): void
    {
        $u = $this->manager();
        $account = $this->create($u);
        $this->assertFalse($account->enabled);

        $this->actingAs($u)->post(route('admin.integrations.enable', $account))->assertSessionHasErrors('account');
        $this->assertFalse($account->fresh()->enabled);
    }

    public function test_paymob_test_connection_uses_auth_only_and_enables_after_success(): void
    {
        config(['payments.live_gateways.enabled' => true]);
        Http::fake(['accept.paymob.com/api/auth/tokens' => Http::response(['token' => 'tok'], 201)]);
        $u = $this->manager();
        $account = $this->create($u);

        $this->actingAs($u)->post(route('admin.integrations.test', $account))->assertRedirect();
        Http::assertSentCount(1);
        Http::assertSent(fn ($r) => str_ends_with($r->url(), '/auth/tokens'));
        $this->assertSame('verified', $account->fresh()->status);

        $this->actingAs($u)->post(route('admin.integrations.enable', $account))->assertRedirect();
        $this->assertTrue($account->fresh()->enabled);
    }

    public function test_failed_test_records_sanitised_error_and_keeps_account_off(): void
    {
        config(['payments.live_gateways.enabled' => true]);
        Http::fake(['accept.paymob.com/*' => Http::response(['detail' => 'bad'], 401)]);
        $u = $this->manager();
        $account = $this->create($u);

        $this->actingAs($u)->post(route('admin.integrations.test', $account));
        $account->refresh();
        $this->assertSame('error', $account->status);
        $this->assertFalse($account->enabled);
        $this->assertStringNotContainsString(self::SECRET, (string) $account->last_error);
    }

    public function test_gateway_tests_are_blocked_where_live_gateways_are_disabled(): void
    {
        config(['payments.live_gateways.enabled' => false]);
        Http::fake();
        $u = $this->manager();
        $account = $this->create($u);

        $this->actingAs($u)->post(route('admin.integrations.test', $account));
        Http::assertNothingSent();
        $this->assertNotSame('verified', $account->fresh()->status);
    }

    public function test_fawaterak_cannot_be_verified_or_enabled_yet(): void
    {
        Http::fake();
        $u = $this->manager();
        $this->actingAs($u)->post(route('admin.integrations.store', 'fawaterak'), ['name' => 'Fawaterak A', 'environment' => 'sandbox', 'api_key' => 'fw-key-123456789'])->assertRedirect();
        $account = IntegrationAccount::where('provider', 'fawaterak')->firstOrFail();

        $this->actingAs($u)->post(route('admin.integrations.test', $account));
        Http::assertNothingSent();
        $this->assertSame('unverified', $account->fresh()->status);
        $this->actingAs($u)->post(route('admin.integrations.enable', $account))->assertSessionHasErrors('account');
    }

    public function test_production_enable_requires_confirmation_and_permissions_are_enforced(): void
    {
        config(['payments.live_gateways.enabled' => true]);
        Http::fake(['accept.paymob.com/*' => Http::response(['token' => 't'], 201)]);
        $u = $this->manager();
        $account = $this->create($u, ['environment' => 'production']);
        $this->actingAs($u)->post(route('admin.integrations.test', $account));

        $manager = app(IntegrationManager::class);
        $this->expectException(ValidationException::class);

        try {
            $manager->enable($account->fresh(), false, $u);
        } finally {
            $this->assertFalse($account->fresh()->enabled);
            $viewer = User::factory()->create();
            Permission::findOrCreate('settings.integrations.view', 'web');
            $viewer->givePermissionTo('settings.integrations.view');
            $this->actingAs($viewer)->post(route('admin.integrations.test', $account))->assertForbidden();
            $this->actingAs($viewer)->put(route('admin.integrations.update', $account), ['name' => 'x', 'environment' => 'sandbox'])->assertForbidden();
        }
    }

    public function test_runtime_ultramsg_prefers_a_verified_enabled_account_else_env(): void
    {
        config(['services.ultramsg.instance_id' => 'env-instance', 'services.ultramsg.token' => 'env-token']);
        $this->assertNull(IntegrationManager::runtimeAccount('ultramsg'));

        $account = IntegrationAccount::create(['provider' => 'ultramsg', 'name' => 'U', 'environment' => 'production', 'enabled' => true, 'status' => 'unverified',
            'config' => ['instance_id' => 'db-instance'], 'secrets' => ['token' => 'db-token']]);
        $this->assertNull(IntegrationManager::runtimeAccount('ultramsg'), 'unverified accounts are never used');

        $account->update(['status' => 'verified']);
        $this->assertSame('db-instance', IntegrationManager::runtimeAccount('ultramsg')->field('instance_id'));
    }

    public function test_legacy_import_is_dry_run_safe_non_destructive_and_reversible(): void
    {
        \App\Models\PaymentMethod::updateOrCreate(['code' => 'paymob_card'], ['name' => 'Paymob Card', 'provider' => 'paymob', 'is_active' => true,
            'config' => ['api_key' => 'legacy-key-123456', 'iframe_id' => '999', 'integration_id' => '42']]);

        $this->artisan('settings:import-legacy-credentials --dry-run')->assertSuccessful();
        $this->assertSame(0, IntegrationAccount::count());

        $this->artisan('settings:import-legacy-credentials')->assertSuccessful();
        $acc = IntegrationAccount::firstOrFail();
        $this->assertFalse($acc->enabled);
        $this->assertSame('unverified', $acc->status);
        $this->assertSame('legacy-key-123456', $acc->secret('api_key'));
        $this->assertSame('legacy-key-123456', \App\Models\PaymentMethod::where('code', 'paymob_card')->first()->config['api_key'], 'legacy value untouched');

        $this->artisan('settings:import-legacy-credentials')->assertSuccessful();
        $this->assertSame(1, IntegrationAccount::count(), 'idempotent');

        $this->artisan('settings:import-legacy-credentials --rollback')->assertSuccessful();
        $this->assertSame(0, IntegrationAccount::count());
    }
}
