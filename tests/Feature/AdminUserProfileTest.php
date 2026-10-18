<?php

namespace Tests\Feature;

use App\Http\Middleware\EnsureAdminPanelAccess;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AdminUserProfileTest extends TestCase
{
    use RefreshDatabase;

    private function actor(array $perms): User
    {
        $this->withoutMiddleware(EnsureAdminPanelAccess::class);
        foreach ($perms as $p) {
            Permission::findOrCreate($p, 'web');
        }

        return tap(User::factory()->create(), fn ($u) => $u->givePermissionTo($perms));
    }

    public function test_profile_page_shows_role_permissions_scope_and_activity(): void
    {
        $viewer = $this->actor(['users.view', 'users.update']);
        Permission::findOrCreate('orders.view', 'web');
        Permission::findOrCreate('orders.update', 'web');
        Role::findOrCreate('Order Agent', 'web')->syncPermissions(['orders.view']);
        $agent = User::factory()->create(['name' => 'Ahmed Agent', 'username' => 'ahmed', 'can_receive_orders' => true]);
        $agent->assignRole('Order Agent');
        $agent->givePermissionTo('orders.update');
        activity('users')->performedOn($agent)->causedBy($viewer)->log('User updated');

        $this->actingAs($viewer)->get(route('admin.users.show', $agent))->assertOk()
            ->assertSee('Ahmed Agent')->assertSee('Order Agent')->assertSee('Receives orders')
            ->assertSee('orders.update')->assertSee('Permissions from the role (1)')
            ->assertSee('All events (no restriction)')->assertSee('User updated')->assertSee('Edit user');
    }

    public function test_profile_requires_view_permission_and_hides_payment_configuration_from_unauthorised_viewers(): void
    {
        $agent = User::factory()->create();
        $noView = $this->actor(['users.update']);
        $this->actingAs($noView)->get(route('admin.users.show', $agent))->assertForbidden();

        $viewer = $this->actor(['users.view']);
        $this->actingAs($viewer)->get(route('admin.users.show', $agent))->assertOk()->assertDontSee('Payment methods')->assertDontSee('Edit user');

        $payments = $this->actor(['users.view', 'settings.payment-accounts.view']);
        $this->actingAs($payments)->get(route('admin.users.show', $agent))->assertOk()->assertSee('Payment methods');
    }

    public function test_the_super_admin_profile_is_not_visible_to_other_staff(): void
    {
        $super = User::factory()->create(['username' => 'superadmin']);
        $viewer = $this->actor(['users.view', 'users.update']);

        $this->actingAs($viewer)->get(route('admin.users.show', $super))->assertForbidden();
        $this->actingAs($viewer)->get(route('admin.users.edit', $super))->assertForbidden();
    }

    public function test_edit_form_has_grouped_permissions_role_map_and_saving_returns_to_the_profile(): void
    {
        $admin = $this->actor(['users.view', 'users.update', 'orders.view']);
        Role::findOrCreate('Order Agent', 'web')->syncPermissions(['orders.view']);
        $target = User::factory()->create(['name' => 'Target User']);

        $this->actingAs($admin)->get(route('admin.users.edit', $target))->assertOk()
            ->assertSee('Permissions')->assertSee('data-perm-reset', false)->assertSee('data-perm-filter', false)->assertSee('data-role-perms', false)->assertSee('from role');

        $this->actingAs($admin)->put(route('admin.users.update', $target), [
            'name' => 'Target Renamed', 'username' => $target->username, 'email' => $target->email, 'role' => 'Order Agent', 'permissions' => ['orders.view'],
        ])->assertRedirect(route('admin.users.show', $target));
        $this->assertSame('Target Renamed', $target->fresh()->name);

        // without users.view the redirect falls back to the list instead of a 403
        $editorOnly = $this->actor(['users.update']);
        $this->actingAs($editorOnly)->put(route('admin.users.update', $target), ['name' => 'Again', 'username' => $target->username, 'email' => $target->email])->assertRedirect(route('admin.users.index'));
    }

    public function test_a_user_cannot_delete_their_own_account(): void
    {
        $me = $this->actor(['users.view', 'users.delete']);

        $this->actingAs($me)->delete(route('admin.users.destroy', $me))->assertStatus(422);
        $this->assertNotNull($me->fresh());
    }
}
