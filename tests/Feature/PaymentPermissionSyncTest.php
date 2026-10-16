<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\PaymentPermissionPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/** The production permission rollout is additive, policy-driven, idempotent and dry-run by default. */
class PaymentPermissionSyncTest extends TestCase
{
    use RefreshDatabase;

    private function role(string $name, array $perms = []): Role
    {
        foreach ($perms as $p) {
            Permission::findOrCreate($p, 'web');
        }

        return tap(Role::findOrCreate($name, 'web'), fn ($r) => $r->syncPermissions($perms));
    }

    private function perms(string $role): array
    {
        return Role::findByName($role, 'web')->permissions->pluck('name')->sort()->values()->all();
    }

    public function test_dry_run_changes_nothing_and_apply_grants_exactly_the_policy_additively(): void
    {
        $this->role('Order Manager', ['orders.view', 'orders.view_all', 'custom.keep']);
        $this->role('Order Agent', ['orders.view', 'orders.view_assigned']);
        $this->role('support', ['orders.view']);
        $this->role('admin', ['orders.view']);
        $before = DB::table('role_has_permissions')->count();

        $this->artisan('payments:sync-permissions')->expectsOutputToContain('DRY RUN')->assertExitCode(0);
        $this->assertSame($before, DB::table('role_has_permissions')->count(), 'dry run writes nothing');

        $this->artisan('payments:sync-permissions', ['--apply' => true])->assertExitCode(0);

        $manager = $this->perms('Order Manager');
        foreach (['payments.transactions.view', 'payments.refunds.view', 'payments.refunds.create'] as $p) {
            $this->assertContains($p, $manager);
        }
        foreach (['payments.refunds.approve', 'payments.accounts.manage', 'payments.accounts.view', 'payments.webhooks.view', 'payments.transactions.inquire'] as $p) {
            $this->assertNotContains($p, $manager, "Order Manager must not get {$p} automatically");
        }
        $this->assertContains('custom.keep', $manager, 'existing permissions are never removed');

        $agent = $this->perms('Order Agent');
        $this->assertContains('payments.transactions.view', $agent);
        $this->assertSame([], array_values(array_filter($agent, fn ($p) => str_starts_with($p, 'payments.') && $p !== 'payments.transactions.view')), 'agents get no refund / account rights');

        $this->assertSame(['orders.view'], $this->perms('support'), 'roles outside the policy are untouched');
        foreach (PaymentPermissionPolicy::ALL as $p) {
            $this->assertContains($p, $this->perms('admin'));
        }

        $after = DB::table('role_has_permissions')->count();
        $this->artisan('payments:sync-permissions', ['--apply' => true])->assertExitCode(0);
        $this->assertSame($after, DB::table('role_has_permissions')->count(), 'idempotent');
    }

    public function test_a_general_view_permission_never_implies_a_financial_permission(): void
    {
        $this->role('Accountant', ['orders.view', 'settings.view']);
        $this->artisan('payments:sync-permissions', ['--apply' => true])->assertExitCode(0);

        $this->assertSame(['orders.view', 'settings.view'], $this->perms('Accountant'));
    }

    public function test_the_approval_permission_is_only_ever_held_by_explicit_grant_and_super_admin(): void
    {
        $this->role('Order Manager', ['orders.view']);
        $this->role('super admin', []);
        $this->artisan('payments:sync-permissions', ['--apply' => true]);

        $this->assertNotContains('payments.refunds.approve', $this->perms('Order Manager'));
        $this->assertContains('payments.refunds.approve', $this->perms('super admin'));

        $user = User::factory()->create();
        $user->assignRole('Order Manager');
        $this->assertFalse($user->fresh()->can('payments.refunds.approve'));
        $user->givePermissionTo('payments.refunds.approve'); // an explicit grant is how a manager becomes an approver
        $this->assertTrue($user->fresh()->can('payments.refunds.approve'));
    }

    public function test_the_foundation_migration_does_not_copy_financial_permissions_from_general_view_roles(): void
    {
        $role = $this->role('Viewer', ['orders.view']);
        $migration = require database_path('migrations/2026_12_01_000001_payment_management_foundation.php');
        $migration->up();

        $this->assertSame(['orders.view'], $this->perms('Viewer'));
    }
}
