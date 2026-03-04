<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class AddDeletedOrdersPermissionSeeder extends Seeder
{
    public function run(): void
    {
        $permission = Permission::findOrCreate('orders.deleted.view', 'web');

        foreach (['admin', 'ticket_manager'] as $roleName) {
            $role = Role::findByName($roleName, 'web');
            if (! $role->hasPermissionTo($permission)) {
                $role->givePermissionTo($permission);
            }
        }
    }
}
