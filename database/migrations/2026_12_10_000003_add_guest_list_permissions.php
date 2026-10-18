<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * The guest list gets its own permissions (guest-list.view / guest-list.create) instead of borrowing tickets.* and being closed to
 * everyone restricted by Order Assignment. Whoever could use it before keeps it: holders of tickets.view / tickets.create get the new
 * permission, except roles that only work their own assigned orders (orders.view_assigned without orders.view_all), which never had it
 * under assignment. Super-admin roles get both. Nothing else changes.
 */
return new class extends Migration
{
    private const MAP = ['guest-list.view' => 'tickets.view', 'guest-list.create' => 'tickets.create'];

    public function up(): void
    {
        foreach (array_keys(self::MAP) as $name) {
            Permission::findOrCreate($name, 'web');
        }

        $id = fn (string $n) => Permission::where('name', $n)->where('guard_name', 'web')->value('id');
        $agentRoleIds = $this->agentOnlyRoleIds($id);

        foreach (self::MAP as $new => $old) {
            $oldId = $id($old);
            $newId = $id($new);
            if (! $oldId) {
                continue;
            }
            foreach (DB::table('role_has_permissions')->where('permission_id', $oldId)->pluck('role_id') as $roleId) {
                if (! in_array($roleId, $agentRoleIds, true)) {
                    DB::table('role_has_permissions')->insertOrIgnore(['permission_id' => $newId, 'role_id' => $roleId]);
                }
            }
            foreach (DB::table('model_has_permissions')->where('permission_id', $oldId)->get() as $row) {
                DB::table('model_has_permissions')->insertOrIgnore(['permission_id' => $newId, 'model_type' => $row->model_type, 'model_id' => $row->model_id]);
            }
        }

        foreach (Role::query()->get() as $role) {
            if (strtolower((string) preg_replace('/[^a-z0-9]/i', '', $role->name)) === 'superadmin') {
                $role->givePermissionTo(array_keys(self::MAP));
            }
        }
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        Permission::query()->whereIn('name', array_keys(self::MAP))->where('guard_name', 'web')->delete();
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /** @return list<int> */
    private function agentOnlyRoleIds(\Closure $id): array
    {
        $assigned = $id('orders.view_assigned');
        if (! $assigned) {
            return [];
        }
        $all = $id('orders.view_all');
        $withAll = $all ? DB::table('role_has_permissions')->where('permission_id', $all)->pluck('role_id')->all() : [];

        return array_values(array_diff(DB::table('role_has_permissions')->where('permission_id', $assigned)->pluck('role_id')->all(), $withAll));
    }
};
