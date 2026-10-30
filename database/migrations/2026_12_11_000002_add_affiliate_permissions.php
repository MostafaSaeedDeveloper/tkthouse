<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * Affiliates get their own permissions (affiliates.view / affiliates.create) instead of borrowing attendees.view / attendees.export. Holders of the
 * old permissions keep access, EXCEPT roles that only work their own assigned orders (orders.view_assigned without orders.view_all): an
 * Order Agent no longer sees Affiliates unless it is granted affiliates.view explicitly. Super-admin roles get both.
 */
return new class extends Migration
{
    private const MAP = ['affiliates.view' => 'attendees.view', 'affiliates.create' => 'attendees.export'];

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
