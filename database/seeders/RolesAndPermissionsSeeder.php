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

        // MySQL refuses TRUNCATE on tables that are referenced by foreign keys.
        \Illuminate\Support\Facades\Schema::disableForeignKeyConstraints();
        DB::table('role_has_permissions')->truncate();
        DB::table('model_has_permissions')->truncate();
        DB::table('permissions')->truncate();
        \Illuminate\Support\Facades\Schema::enableForeignKeyConstraints();

        $permissions = [
            'dashboard.view',

            'users.view',
            'users.create',
            'users.update',
            'users.delete',

            'scanners.view',

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
            'events.archive',
            'events.restore',
            'events.publish',
            'events.mark-sold-out',

            'tickets.view',
            'tickets.create',
            'tickets.update',
            'tickets.delete',
            'guest-list.view',
            'guest-list.create',
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

            // Settings Center + Order Assignment (V2). Kept here because this seeder truncates the permission tables.
            'settings.orders',
            'settings.integrations.view',
            'settings.integrations.manage',
            'settings.integrations.test',
            'settings.security.view',
            'orders.assign',
            'orders.reassign',
            'orders.view_all',
            'orders.view_assigned',
            'orders.manage_assigned',
            'orders.view_unassigned',
            'orders.view_agent_stats',
            'orders.history.view',
            'orders.reroute_payment',
            'settings.payment-accounts.view',
            'settings.payment-accounts.manage',
            'payments.transactions.view',
            'payments.transactions.inquire',
            'payments.refunds.view',
            'payments.refunds.create',
            'payments.refunds.approve',
            'payments.accounts.view',
            'payments.accounts.manage',
            'payments.webhooks.view',
            'payments.transactions.import',
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
            'guest-list.view',
            'guest-list.create',
            'scanner.access',
            'fees.create',
            'fees.update',
            'fees.delete',
            'event-images.create',
            'event-images.update',
            'event-images.delete',
            'orders.view',
            'orders.history.view',
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
            'guest-list.view',
            'guest-list.create',
            'scanner.access',
            'fees.create',
            'fees.update',
            'fees.delete',
            'orders.view',
            'orders.history.view',
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
            'orders.history.view',
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
            'orders.history.view',
        ]);

        Role::findOrCreate('Order Manager', 'web')->syncPermissions(['dashboard.view', 'orders.view', 'orders.history.view', 'orders.update', 'update_status', 'orders.view_all', 'orders.assign', 'orders.reassign', 'orders.view_unassigned', 'orders.view_agent_stats', ...\App\Support\PaymentPermissionPolicy::forRole('Order Manager')]);
        Role::findOrCreate('Order Agent', 'web')->syncPermissions(['dashboard.view', 'orders.view', 'orders.history.view', 'orders.update', 'update_status', 'orders.view_assigned', 'orders.manage_assigned', ...\App\Support\PaymentPermissionPolicy::forRole('Order Agent')]);

        Role::findOrCreate('agent', 'web')->syncPermissions(['dashboard.view', 'orders.view', 'orders.history.view', 'orders.update', 'update_status', 'orders.view_assigned', 'orders.manage_assigned', ...\App\Support\PaymentPermissionPolicy::forRole('agent')]);

        // Display names/descriptions of the system roles (only filled in when empty, so edits made in the panel survive a re-seed).
        foreach (\App\Support\RoleLabel::SYSTEM as $key => [$label, $description]) {
            $role = Role::query()->where('name', $key)->where('guard_name', 'web')->first();
            if ($role) {
                $role->forceFill([
                    'display_name' => $role->display_name ?: $label,
                    'description' => $role->description ?: $description,
                    'is_system' => true,
                ])->save();
            }
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
