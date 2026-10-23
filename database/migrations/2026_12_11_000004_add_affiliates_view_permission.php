<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * Affiliates gets its own permission (affiliates.view) instead of sharing attendees.view with Customers. Whoever could open
 * Affiliates before keeps it (holders of attendees.view), except agent roles: "agent" / "Order Agent" and any role that only works
 * its own assigned orders (orders.view_assigned without orders.view_all). Super-admin roles get it too.
 */
return new class extends Migration
{
    private const NAME = 'affiliates.view';

    public function up(): void
    {
        $new = Permission::findOrCreate(self::NAME, 'web');
        $old = Permission::where('name', 'attendees.view')->where('guard_name', 'web')->value('id');
        $skip = $this->agentRoleIds();

        if ($old) {
            foreach (DB::table('role_has_permissions')->where('permission_id', $old)->pluck('role_id') as $roleId) {
                if (! in_array($roleId, $skip, true)) {
                    DB::table('role_has_permissions')->insertOrIgnore(['permission_id' => $new->id, 'role_id' => $roleId]);
                }
            }
            foreach (DB::table('model_has_permissions')->where('permission_id', $old)->get() as $row) {
                $hasAgentRole = DB::table('model_has_roles')->where('model_type', $row->model_type)->where('model_id', $row->model_id)->whereIn('role_id', $skip)->exists();
                if (! $hasAgentRole) {
                    DB::table('model_has_permissions')->insertOrIgnore(['permission_id' => $new->id, 'model_type' => $row->model_type, 'model_id' => $row->model_id]);
                }
            }
        }

        foreach (Role::query()->get() as $role) {
            if (strtolower((string) preg_replace('/[^a-z0-9]/i', '', $role->name)) === 'superadmin') {
                $role->givePermissionTo(self::NAME);
            }
        }
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        Permission::query()->where('name', self::NAME)->where('guard_name', 'web')->delete();
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /** @return list<int> */
    private function agentRoleIds(): array
    {
        $ids = Role::query()->get()
            ->filter(fn ($r) => in_array(strtolower((string) preg_replace('/[^a-z0-9]/i', '', $r->name)), ['agent', 'orderagent'], true))
            ->pluck('id')->all();

        $perm = fn (string $n) => Permission::where('name', $n)->where('guard_name', 'web')->value('id');
        $assigned = $perm('orders.view_assigned');
        if ($assigned) {
            $all = $perm('orders.view_all');
            $withAll = $all ? DB::table('role_has_permissions')->where('permission_id', $all)->pluck('role_id')->all() : [];
            $ids = array_merge($ids, array_diff(DB::table('role_has_permissions')->where('permission_id', $assigned)->pluck('role_id')->all(), $withAll));
        }

        return array_values(array_unique(array_map('intval', $ids)));
    }
};
