<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class AddMissingAdminPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = [
            'reports.view',
            'settings.view',
            'settings.update',

            'payment-methods.view',
            'payment-methods.create',
            'payment-methods.update',
            'payment-methods.delete',

            'promo-codes.view',
            'promo-codes.create',
            'promo-codes.update',
            'promo-codes.delete',

            'orders.deleted.view',
            'orders.delete',
            'orders.restore',
            'update_status',
        ];

        foreach ($permissions as $permissionName) {
            Permission::findOrCreate($permissionName, 'web');
        }

        $rolePermissions = [
            'admin' => $permissions,
            'ticket_manager' => [
                'orders.deleted.view',
                'orders.delete',
                'orders.restore',
                'update_status',
                'promo-codes.view',
            ],
            'support' => [
                'reports.view',
            ],
        ];

        foreach ($rolePermissions as $roleName => $rolePermissionList) {
            $role = Role::where('name', $roleName)->first();

            if (! $role) {
                continue;
            }

            $role->givePermissionTo($rolePermissionList);
        }
    }
}
