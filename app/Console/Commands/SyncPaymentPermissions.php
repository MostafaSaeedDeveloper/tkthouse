<?php

namespace App\Console\Commands;

use App\Support\PaymentPermissionPolicy;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Additive, idempotent permission rollout for Payments (safe on production; replaces running RolesAndPermissionsSeeder, which truncates).
 * Default is a DRY RUN. Never removes a permission, never touches users, never touches roles outside the policy.
 */
class SyncPaymentPermissions extends Command
{
    protected $signature = 'payments:sync-permissions {--apply : actually grant (default: only show what would change)}';

    protected $description = 'Create the Payments permissions and grant them per the production policy (additive only; dry run unless --apply).';

    public function handle(): int
    {
        $apply = (bool) $this->option('apply');
        $this->line($apply ? 'APPLYING (additive only).' : 'DRY RUN: nothing is written. Re-run with --apply to grant.');

        $missing = array_values(array_filter(PaymentPermissionPolicy::ALL, fn ($p) => ! Permission::query()->where('name', $p)->where('guard_name', 'web')->exists()));
        foreach ($missing as $name) {
            $this->line("  + create permission {$name}");
            if ($apply) {
                Permission::findOrCreate($name, 'web');
            }
        }

        $changes = 0;
        foreach (Role::query()->where('guard_name', 'web')->orderBy('name')->get() as $role) {
            $wanted = PaymentPermissionPolicy::forRole($role->name);
            if ($wanted === []) {
                continue;
            }
            $have = DB::table('role_has_permissions')->join('permissions', 'permissions.id', '=', 'role_has_permissions.permission_id')
                ->where('role_id', $role->id)->pluck('permissions.name')->all();
            $grant = array_values(array_diff($wanted, $have));
            foreach ($grant as $name) {
                $this->line("  + role \"{$role->name}\" gets {$name}");
                $changes++;
            }
            if ($apply && $grant) {
                $role->givePermissionTo($grant);
            }
        }

        $unmatched = Role::query()->where('guard_name', 'web')->get()->filter(fn ($r) => PaymentPermissionPolicy::forRole($r->name) === [])->pluck('name')->implode(', ');
        $this->line('Roles outside the policy (left untouched, grant payments permissions to them explicitly in Admin → Roles): '.($unmatched ?: '—'));

        if ($apply) {
            app(PermissionRegistrar::class)->forgetCachedPermissions();
        }
        $this->info(($apply ? 'Granted' : 'Would grant').' '.$changes.' role permission(s); '.count($missing).' permission(s) '.($apply ? 'created' : 'to create').'.');

        return self::SUCCESS;
    }
}
