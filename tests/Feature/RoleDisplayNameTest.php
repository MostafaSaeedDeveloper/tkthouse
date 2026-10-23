<?php

namespace Tests\Feature;

use App\Http\Middleware\EnsureAdminPanelAccess;
use App\Models\User;
use App\Support\RoleLabel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/** `roles.name` is the key the code uses and never changes; `display_name` is what people read and can be edited freely. */
class RoleDisplayNameTest extends TestCase
{
    use RefreshDatabase;

    private function manager(): User
    {
        $this->withoutMiddleware(EnsureAdminPanelAccess::class);
        foreach (['roles.view', 'roles.create', 'roles.update', 'roles.delete', 'users.view'] as $p) {
            Permission::findOrCreate($p, 'web');
        }

        return tap(User::factory()->create())->givePermissionTo(['roles.view', 'roles.create', 'roles.update', 'roles.delete', 'users.view']);
    }

    public function test_system_roles_get_labels_and_descriptions_from_the_migration(): void
    {
        $role = Role::findOrCreate('ticket_manager', 'web');
        (new \Database\Seeders\RolesAndPermissionsSeeder)->run();

        $role = $role->fresh();
        $this->assertSame('Ticket Manager', $role->display_name);
        $this->assertTrue((bool) $role->is_system);
        $this->assertNotEmpty($role->description);
        $this->assertSame('Ticket Manager', RoleLabel::of($role));
        $this->assertSame('Scanner Staff', RoleLabel::of('scanner'));
        $this->assertSame('My Custom Thing', RoleLabel::of('my_custom_thing'));   // readable fallback for any key
    }

    public function test_the_agent_role_exists_with_assigned_orders_only_permissions(): void
    {
        (new \Database\Seeders\RolesAndPermissionsSeeder)->run();

        $role = Role::findByName('agent', 'web');
        $this->assertSame('Agent', RoleLabel::of($role));
        $this->assertTrue((bool) $role->is_system);
        foreach (['orders.view_assigned', 'orders.manage_assigned', 'orders.update', 'dashboard.view', 'payments.transactions.view'] as $p) {
            $this->assertTrue($role->hasPermissionTo($p), $p);
        }
        foreach (['orders.assign', 'orders.reassign', 'orders.view_all', 'orders.delete', 'payments.refunds.approve', 'users.update'] as $p) {
            $this->assertFalse($role->hasPermissionTo($p), "agent must not have $p");
        }

        $user = User::factory()->create(['can_receive_orders' => true]);
        $user->assignRole('agent');
        $this->assertTrue(app(\App\Orders\OrderAssignmentService::class)->isEligible($user->fresh()), 'an agent can be picked by automatic assignment');
        $this->actingAs($this->manager())->delete(route('admin.roles.destroy', $role))->assertSessionHas('error');
    }

    private function userBody(array $extra = []): array
    {
        return ['name' => 'Perm Test', 'username' => 'permtest'.random_int(1, 99999), 'email' => 'p'.random_int(1, 99999).'@example.test', 'password' => 'Secret123!', 'permissions_effective' => 1] + $extra;
    }

    public function test_user_form_ticks_the_effective_permissions_and_ships_the_role_map(): void
    {
        (new \Database\Seeders\RolesAndPermissionsSeeder)->run();
        $manager = $this->manager();
        Permission::findOrCreate('users.create', 'web');
        $manager->givePermissionTo('users.create');

        $html = $this->actingAs($manager)->get(route('admin.users.create'))->assertOk()->getContent();

        $this->assertStringContainsString('name="permissions_effective"', $html);
        $this->assertStringContainsString('data-perm-reset', $html);
        $this->assertStringContainsString('data-role-perms=', $html);
        $this->assertStringContainsString('Ticket Manager', $html);   // role names in the picker are the display names
    }

    public function test_saving_with_the_roles_permissions_ticked_assigns_the_role_plus_only_the_extras(): void
    {
        (new \Database\Seeders\RolesAndPermissionsSeeder)->run();
        $base = Role::findByName('support', 'web')->permissions->pluck('name')->all();
        Permission::findOrCreate('users.create', 'web');
        $manager = $this->manager();
        $manager->givePermissionTo('users.create');

        $this->actingAs($manager)->post(route('admin.users.store'), $this->userBody(['role' => 'support', 'permissions' => [...$base, 'tickets.view']]))->assertRedirect();

        $u = User::query()->where('name', 'Perm Test')->firstOrFail();
        $this->assertTrue($u->hasRole('support'));
        $this->assertSame(['tickets.view'], $u->permissions->pluck('name')->all(), 'only the extra is stored directly');
        $this->assertTrue($u->can('orders.view') && $u->can('tickets.view'));
    }

    public function test_unticking_a_role_permission_keeps_the_role_and_denies_only_that_permission(): void
    {
        (new \Database\Seeders\RolesAndPermissionsSeeder)->run();
        $base = Role::findByName('support', 'web')->permissions->pluck('name')->all();
        $kept = array_values(array_diff($base, ['reports.view']));
        Permission::findOrCreate('users.create', 'web');
        $manager = $this->manager();
        $manager->givePermissionTo('users.create');

        $this->actingAs($manager)->post(route('admin.users.store'), $this->userBody(['role' => 'support', 'permissions' => $kept]))->assertRedirect();

        $u = User::query()->where('name', 'Perm Test')->firstOrFail();
        $this->assertTrue($u->hasRole('support'));
        $this->assertTrue($u->permissions->isEmpty());
        $this->assertSame(['reports.view'], $u->denied_permissions);
        $this->assertFalse($u->can('reports.view'), 'the unticked permission is removed for this user only');
        $this->assertTrue($u->can('orders.view'));
    }

    public function test_requests_without_the_marker_keep_the_previous_role_plus_direct_meaning(): void
    {
        (new \Database\Seeders\RolesAndPermissionsSeeder)->run();
        Permission::findOrCreate('users.create', 'web');
        $manager = $this->manager();
        $manager->givePermissionTo('users.create');
        $body = $this->userBody(['role' => 'support']);
        unset($body['permissions_effective']);

        $this->actingAs($manager)->post(route('admin.users.store'), $body)->assertRedirect();

        $u = User::query()->where('name', 'Perm Test')->firstOrFail();
        $this->assertTrue($u->hasRole('support'));
        $this->assertTrue($u->permissions->isEmpty());
    }

    public function test_renaming_the_display_name_never_changes_the_key_or_what_the_code_sees(): void
    {
        (new \Database\Seeders\RolesAndPermissionsSeeder)->run();
        $role = Role::findByName('ticket_manager', 'web');
        $user = User::factory()->create();
        $user->assignRole('ticket_manager');

        // a forged "name" in the request is ignored
        $this->actingAs($this->manager())->put(route('admin.roles.update', $role), [
            'name' => 'hacked_key', 'display_name' => 'Tickets Lead', 'description' => 'Runs ticketing', 'permissions' => ['tickets.view'],
        ])->assertRedirect();

        $role = $role->fresh();
        $this->assertSame('ticket_manager', $role->name);
        $this->assertSame('Tickets Lead', $role->display_name);
        $this->assertTrue($user->fresh()->hasRole('ticket_manager'), 'code that checks the key keeps working');
        $this->assertSame('Tickets Lead', RoleLabel::of($user->fresh()->roles->first()));
    }

    public function test_a_new_role_gets_a_unique_snake_case_key_from_its_display_name(): void
    {
        $manager = $this->manager();
        $this->actingAs($manager)->post(route('admin.roles.store'), ['display_name' => 'Door Supervisor', 'permissions' => []])->assertRedirect();
        $this->actingAs($manager)->post(route('admin.roles.store'), ['display_name' => 'Door  Supervisor', 'permissions' => []])->assertRedirect();

        $this->assertSame(['door_supervisor', 'door_supervisor_2'], Role::query()->where('name', 'like', 'door_supervisor%')->orderBy('name')->pluck('name')->all());
        $this->assertSame('Door Supervisor', Role::findByName('door_supervisor', 'web')->display_name);
        $this->actingAs($manager)->post(route('admin.roles.store'), ['display_name' => '', 'permissions' => []])->assertSessionHasErrors('display_name');
    }

    public function test_system_roles_and_roles_in_use_cannot_be_deleted(): void
    {
        (new \Database\Seeders\RolesAndPermissionsSeeder)->run();
        $manager = $this->manager();

        $this->actingAs($manager)->delete(route('admin.roles.destroy', Role::findByName('support', 'web')))->assertSessionHas('error');
        $this->assertNotNull(Role::findByName('support', 'web'));

        $custom = Role::create(['name' => 'temp_role']);
        $u = User::factory()->create();
        $u->assignRole('temp_role');
        $this->actingAs($manager)->delete(route('admin.roles.destroy', $custom))->assertSessionHas('error');
        $this->assertNotNull($custom->fresh());

        $u->removeRole('temp_role');
        $this->actingAs($manager)->delete(route('admin.roles.destroy', $custom))->assertSessionHas('success');
        $this->assertNull(Role::query()->where('name', 'temp_role')->first());
    }

    public function test_screens_show_the_label_and_keep_the_key_as_the_submitted_value(): void
    {
        (new \Database\Seeders\RolesAndPermissionsSeeder)->run();
        $manager = $this->manager();

        $this->actingAs($manager)->get(route('admin.roles.index'))->assertOk()->assertSee('Ticket Manager')->assertSee('<code>ticket_manager</code>', false);
        $this->actingAs($manager)->get(route('admin.roles.edit', Role::findByName('ticket_manager', 'web')))->assertOk()
            ->assertSee('name="display_name"', false)->assertSee('readonly', false)->assertDontSee('name="name"', false);
    }
}
