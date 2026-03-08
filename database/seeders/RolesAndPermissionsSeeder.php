<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        DB::table('role_has_permissions')->truncate();
        DB::table('model_has_permissions')->truncate();
        DB::table('permissions')->truncate();

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
            'tickets.create',
            'tickets.update',
            'tickets.delete',
            'scanner.access',

            'fees.create',
            'fees.update',
            'fees.delete',

            'event-images.create',
            'event-images.update',
            'event-images.delete',

            'orders.view',
            'orders.create',
            'orders.update',
            'update_status',
            'orders.delete',
            'orders.deleted.view',
            'orders.restore',
            'showing_orders',

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
        $scannerRole = Role::findOrCreate('scanner', 'web');

        $adminRole->syncPermissions(Permission::all());

        $eventManagerRole->syncPermissions([
            'dashboard.view',
            'events.view',
            'events.create',
            'events.update',
            'events.publish',
            'events.mark-sold-out',
            'tickets.view',
            'tickets.create',
            'tickets.update',
            'tickets.delete',
            'scanner.access',
            'fees.create',
            'fees.update',
            'fees.delete',
            'event-images.create',
            'event-images.update',
            'event-images.delete',
            'orders.view',
            'orders.update',
            'update_status',
            'attendees.view',
            'attendees.export',
        ]);

        $ticketManagerRole->syncPermissions([
            'dashboard.view',
            'events.view',
            'tickets.view',
            'tickets.create',
            'tickets.update',
            'tickets.delete',
            'scanner.access',
            'fees.create',
            'fees.update',
            'fees.delete',
            'orders.view',
            'orders.update',
            'update_status',
            'orders.delete',
            'orders.deleted.view',
            'orders.restore',
            'promo-codes.view',
            'attendees.view',
            'attendees.export',
        ]);

        $supportRole->syncPermissions([
            'dashboard.view',
            'events.view',
            'orders.view',
            'reports.view',
            'attendees.view',
            'activity-logs.view',
        ]);

        $scannerRole->syncPermissions([
            'dashboard.view',
            'tickets.view',
            'tickets.update',
            'scanner.access',
            'orders.view',
        ]);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
