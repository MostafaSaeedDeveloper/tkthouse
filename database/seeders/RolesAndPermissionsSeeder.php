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
            'dashboard.view',

            'users.view',
            'users.create',
            'users.update',
            'users.delete',

            'roles.view',
            'roles.create',
            'roles.update',
            'roles.delete',

            'permissions.view',
            'permissions.create',
            'permissions.update',
            'permissions.delete',

            'events.view',
            'events.create',
            'events.update',
            'events.delete',
            'events.publish',
            'events.mark-sold-out',

            'tickets.view',
            'tickets.manage',
            'fees.manage',
            'event-images.manage',

            'orders.view',
            'orders.manage',
            'attendees.view',
            'attendees.export',

            'activity-logs.view',
        ];

        foreach ($permissions as $permissionName) {
            Permission::findOrCreate($permissionName, 'web');
        }

        $adminRole = Role::findOrCreate('admin', 'web');
        $eventManagerRole = Role::findOrCreate('event_manager', 'web');
        $ticketManagerRole = Role::findOrCreate('ticket_manager', 'web');
        $supportRole = Role::findOrCreate('support', 'web');

        $adminRole->syncPermissions(Permission::all());

        $eventManagerRole->syncPermissions([
            'dashboard.view',
            'events.view',
            'events.create',
            'events.update',
            'events.publish',
            'events.mark-sold-out',
            'tickets.view',
            'tickets.manage',
            'fees.manage',
            'event-images.manage',
            'orders.view',
            'attendees.view',
            'attendees.export',
        ]);

        $ticketManagerRole->syncPermissions([
            'dashboard.view',
            'events.view',
            'tickets.view',
            'tickets.manage',
            'fees.manage',
            'orders.view',
            'orders.manage',
            'attendees.view',
            'attendees.export',
        ]);

        $supportRole->syncPermissions([
            'dashboard.view',
            'events.view',
            'orders.view',
            'attendees.view',
            'activity-logs.view',
        ]);
    }
}
