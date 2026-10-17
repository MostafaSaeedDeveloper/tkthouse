<?php

namespace Tests\Feature;

use App\Http\Middleware\EnsureAdminPanelAccess;
use App\Integrations\ConnectionTester;
use App\Models\IntegrationAccount;
use App\Models\User;
use App\Settings\SettingsService;
use App\Support\SystemSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class SettingsCenterTest extends TestCase
{
    use RefreshDatabase;

    private function staff(array $permissions): User
    {
        $this->withoutMiddleware(EnsureAdminPanelAccess::class);
        $user = User::factory()->create();
        foreach ($permissions as $p) {
            Permission::findOrCreate($p, 'web');
        }
        $user->givePermissionTo($permissions);

        return $user;
    }

    public function test_legacy_settings_api_and_defaults_keep_working(): void
    {
        $this->assertSame('TKT House', SystemSettings::get('site_name'));
        $this->assertSame(2880, SystemSettings::pendingPaymentTimeoutMinutes());
        SystemSettings::set('site_name', 'Legacy');
        $this->assertSame('Legacy', SystemSettings::get('site_name'));
        $this->assertSame(10, SystemSettings::get('default_max_tickets_per_order'));
    }

    public function test_update_validates_audits_and_invalidates_cache(): void
    {
        $user = $this->staff(['settings.view', 'settings.update']);

        $this->actingAs($user)->put(route('admin.settings.update'), ['site_name' => 'New Name', 'contact_email' => 'hi@example.com'])->assertRedirect();
        $this->assertSame('New Name', app(SettingsService::class)->get('site_name'));
        $this->assertDatabaseHas('setting_audits', ['key' => 'site_name', 'new_value' => 'New Name', 'user_id' => $user->id]);

        // cache invalidation across a second service instance reading the shared cache
        $this->assertSame('New Name', (new SettingsService())->get('site_name'));

        $this->actingAs($user)->put(route('admin.settings.update'), ['site_name' => 'x', 'contact_email' => 'not-an-email'])->assertSessionHasErrors('contact_email');
        $this->assertSame('New Name', app(SettingsService::class)->get('site_name'));
    }

    public function test_unknown_keys_cannot_be_written(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        app(SettingsService::class)->update(['app_key' => 'x']);
    }

    public function test_sections_respect_permissions(): void
    {
        $viewer = $this->staff(['settings.view']);
        $this->actingAs($viewer)->get(route('admin.settings.section', 'branding'))->assertOk();
        $this->actingAs($viewer)->get(route('admin.settings.section', 'orders'))->assertForbidden();
        $this->actingAs($viewer)->put(route('admin.settings.section.update', 'branding'), ['primary_color' => '#112233'])->assertForbidden();
        $this->actingAs($viewer)->get(route('admin.integrations.index'))->assertForbidden();
        $this->actingAs($viewer)->get('/dashboard/settings/nope')->assertNotFound();
    }

    public function test_frontend_maintenance_flag_from_settings(): void
    {
        $this->get('/')->assertOk();
        SystemSettings::set('frontend_maintenance', true);
        $this->get('/')->assertStatus(503);
        $this->get('/checkout')->assertStatus(302); // checkout/payments are never taken down by it
    }

    public function test_default_max_tickets_setting_is_the_fallback_only(): void
    {
        $sel = app(\App\Services\Checkout\CheckoutSelection::class);
        $own = new \App\Models\EventTicket(['max_per_order' => 3]);
        $none = new \App\Models\EventTicket();

        $this->assertSame(3, $sel->maxAllowedQuantity($own));
        $this->assertSame(10, $sel->maxAllowedQuantity($none));

        SystemSettings::set('default_max_tickets_per_order', 4);
        $this->assertSame(3, $sel->maxAllowedQuantity($own));   // the ticket's own limit always wins
        $this->assertSame(4, $sel->maxAllowedQuantity($none));
    }
}
