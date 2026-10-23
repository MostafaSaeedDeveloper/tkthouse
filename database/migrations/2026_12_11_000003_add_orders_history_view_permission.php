<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * "Order History" on the order page gets its own permission (orders.history.view). Whoever could open orders before keeps seeing it:
 * every role / user holding orders.view gets the new permission, and super-admin roles get it too. Admins can then untick it per role or user.
 */
return new class extends Migration
{
    private const NAME = 'orders.history.view';

    public function up(): void
    {
        $new = Permission::findOrCreate(self::NAME, 'web');
        $old = Permission::where('name', 'orders.view')->where('guard_name', 'web')->value('id');

        if ($old) {
            foreach (DB::table('role_has_permissions')->where('permission_id', $old)->pluck('role_id') as $roleId) {
                DB::table('role_has_permissions')->insertOrIgnore(['permission_id' => $new->id, 'role_id' => $roleId]);
            }
            foreach (DB::table('model_has_permissions')->where('permission_id', $old)->get() as $row) {
                DB::table('model_has_permissions')->insertOrIgnore(['permission_id' => $new->id, 'model_type' => $row->model_type, 'model_id' => $row->model_id]);
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
};
