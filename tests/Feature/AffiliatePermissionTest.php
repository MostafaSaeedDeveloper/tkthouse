<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/** Affiliates have their own permissions; attendees.view alone no longer opens them. */
class AffiliatePermissionTest extends TestCase
{
    use RefreshDatabase;

    private function user(array $perms): User
    {
        foreach ($perms as $p) {
            Permission::findOrCreate($p, 'web');
        }

        return tap(User::factory()->create(), fn ($u) => $u->givePermissionTo($perms));
    }

    public function test_affiliates_need_their_own_permission(): void
    {
        $attendeesOnly = $this->user(['dashboard.view', 'attendees.view', 'attendees.export']);
        $this->actingAs($attendeesOnly)->get(route('admin.affiliates.index'))->assertForbidden();
        $this->actingAs($attendeesOnly)->get(route('admin.customers.index'))->assertOk();
        $this->actingAs($attendeesOnly)->get(route('admin.dashboard'))->assertOk()->assertDontSee('Affiliates');

        $viewer = $this->user(['dashboard.view', 'affiliates.view']);
        $this->actingAs($viewer)->get(route('admin.affiliates.index'))->assertOk();
        $this->actingAs($viewer)->get(route('admin.dashboard'))->assertOk()->assertSee('Affiliates');
        $this->actingAs($viewer)->get(route('admin.affiliates.create'))->assertForbidden();
    }
}
