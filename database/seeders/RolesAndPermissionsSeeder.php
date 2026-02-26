<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = [
            'users.*', 'roles.*', 'permissions.*', 'events.*', 'venues.*', 'ticket_types.*', 'orders.view',
            'orders.update', 'refunds.*', 'tickets.*', 'checkin.scan', 'checkin.view', 'reports.view',
            'settings.update', 'templates.manage', 'fees.manage', 'wallet.manage',
        ];

        foreach ($permissions as $permission) {
            Permission::findOrCreate($permission, 'web');
        }

        $roles = ['super_admin', 'admin', 'organizer', 'cashier', 'support', 'customer'];
        foreach ($roles as $roleName) {
            $role = Role::findOrCreate($roleName, 'web');
            if ($roleName === 'super_admin') {
                $role->syncPermissions(Permission::all());
            }
        }
    }
}
