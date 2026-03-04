<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RemoveOrdersManagePermissionSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $replacementPermissions = [
            Permission::findOrCreate('orders.create', 'web'),
            Permission::findOrCreate('orders.update', 'web'),
            Permission::findOrCreate('orders.delete', 'web'),
        ];

        $legacyPermissions = Permission::query()
            ->whereIn('name', ['orders.manage', 'orders_manage'])
            ->where('guard_name', 'web')
            ->get();

        foreach ($legacyPermissions as $legacyPermission) {
            $roles = Role::query()->whereHas('permissions', function ($query) use ($legacyPermission) {
                $query->where('permissions.id', $legacyPermission->id);
            })->get();

            foreach ($roles as $role) {
                $role->givePermissionTo($replacementPermissions);
                $role->revokePermissionTo($legacyPermission);
            }

            $users = User::query()->whereHas('permissions', function ($query) use ($legacyPermission) {
                $query->where('permissions.id', $legacyPermission->id);
            })->get();

            foreach ($users as $user) {
                $user->givePermissionTo($replacementPermissions);
                $user->revokePermissionTo($legacyPermission);
            }

            DB::table('role_has_permissions')->where('permission_id', $legacyPermission->id)->delete();
            DB::table('model_has_permissions')->where('permission_id', $legacyPermission->id)->delete();

            $legacyPermission->delete();
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
