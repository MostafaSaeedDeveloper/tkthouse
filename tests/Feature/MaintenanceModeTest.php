<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class MaintenanceModeTest extends TestCase
{
    use RefreshDatabase;

    private function userWithRole(?string $role): User
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        if ($role) {
            Role::findOrCreate($role, 'web');
            $user->assignRole($role);
        }

        return $user;
    }

    public function test_visitors_see_the_maintenance_page_with_a_503_and_no_caching(): void
    {
        config(['app.frontend_maintenance' => true]);

        $this->get(route('front.home'))
            ->assertStatus(503)
            ->assertSee('We are updating our system')
            ->assertHeader('Retry-After', '3600')
            ->assertHeader('Cache-Control', 'max-age=0, no-store, private');
    }

    public function test_customers_and_staff_who_are_not_super_admins_still_see_the_maintenance_page(): void
    {
        config(['app.frontend_maintenance' => true]);

        foreach ([null, 'admin', 'support'] as $role) {
            $this->actingAs($this->userWithRole($role))->get(route('front.home'))->assertStatus(503);
        }
    }

    public function test_super_admin_opens_the_site_while_it_is_in_maintenance(): void
    {
        config(['app.frontend_maintenance' => true]);

        $this->actingAs($this->userWithRole('super_admin'))->get(route('front.home'))->assertOk()->assertDontSee('We are updating our system');
    }

    public function test_full_app_maintenance_also_lets_the_super_admin_through_and_keeps_others_out(): void
    {
        config(['app.app_maintenance' => true]);

        $this->get(route('front.home'))->assertStatus(503);
        $this->actingAs($this->userWithRole('super_admin'))->get(route('front.home'))->assertOk();
    }

    public function test_the_toggle_in_system_settings_is_bypassed_by_the_super_admin_too(): void
    {
        \App\Support\SystemSettings::set('frontend_maintenance', true);

        $this->get(route('front.home'))->assertStatus(503);
        $this->actingAs($this->userWithRole('super_admin'))->get(route('front.home'))->assertOk();
    }

    public function test_login_and_payment_webhooks_stay_reachable_during_maintenance(): void
    {
        config(['app.frontend_maintenance' => true]);

        $this->get(route('front.customer.login'))->assertOk();
        $this->postJson(route('front.fawaterak.webhook'), ['invoice_id' => 'X', 'invoice_status' => 'paid'])->assertOk();
    }
}
