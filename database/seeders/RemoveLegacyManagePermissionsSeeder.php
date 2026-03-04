<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RemoveLegacyManagePermissionsSeeder extends Seeder
{
    /**
     * @var array<string, array<int, string>>
     */
    private const LEGACY_TO_NEW = [
        'orders' => ['orders.create', 'orders.update', 'orders.delete'],
        'tickets' => ['tickets.create', 'tickets.update', 'tickets.delete'],
        'fees' => ['fees.create', 'fees.update', 'fees.delete'],
        'event-images' => ['event-images.create', 'event-images.update', 'event-images.delete'],
    ];

    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (self::LEGACY_TO_NEW as $resource => $replacementPermissionNames) {
            $replacementPermissions = collect($replacementPermissionNames)
                ->map(fn (string $permissionName) => Permission::findOrCreate($permissionName, 'web'))
                ->all();

            $legacyPermissions = Permission::query()
                ->whereIn('name', ["{$resource}.".'manage', str_replace('-', '_', $resource).'_manage'])
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
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
