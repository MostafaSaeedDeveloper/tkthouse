<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Adds the `agent` role (works only the orders assigned to them). Additive: nothing existing is changed; only permissions
 * that already exist are granted, and the role is left alone (no permission reset) if it was created by hand already.
 */
return new class extends Migration
{
    private const PERMISSIONS = ['dashboard.view', 'orders.view', 'orders.update', 'update_status', 'orders.view_assigned', 'orders.manage_assigned', 'payments.transactions.view'];

    public function up(): void
    {
        if (! \Illuminate\Support\Facades\Schema::hasTable('roles')) {
            return;
        }

        $role = Role::query()->where('name', 'agent')->where('guard_name', 'web')->first();
        $created = ! $role;
        $role ??= Role::create(['name' => 'agent', 'guard_name' => 'web']);

        if ($created) {
            $role->givePermissionTo(Permission::query()->where('guard_name', 'web')->whereIn('name', self::PERMISSIONS)->get());
        }

        if (\Illuminate\Support\Facades\Schema::hasColumn('roles', 'display_name')) {
            [$label, $description] = \App\Support\RoleLabel::SYSTEM['agent'];
            $role->forceFill(['display_name' => $role->display_name ?: $label, 'description' => $role->description ?: $description, 'is_system' => true])->save();
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /** Never removes a role that may already have users; delete it from Roles if it is no longer wanted. */
    public function down(): void
    {
    }
};
