<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;

/**
 * Phase 1 of the unified payment management. Additive only: existing payment methods keep their codes/names; sort_order defaults
 * to 0 (the previous implicit order was by id, which stays the tie-breaker). New permissions are copied only to roles/users that
 * already hold the equivalent payment-account permission (so the pages that moved keep working for the same people). Nothing is
 * granted because of a GENERAL view permission such as orders.view: new financial permissions reach other roles only through
 * `php artisan payments:sync-permissions --apply` (the production policy) or explicitly in Admin → Roles.
 */
return new class extends Migration
{
    public const PERMISSIONS = [
        'payments.transactions.view', 'payments.transactions.inquire',
        'payments.refunds.view', 'payments.refunds.create', 'payments.refunds.approve',
        'payments.accounts.view', 'payments.accounts.manage', 'payments.webhooks.view',
    ];

    public function up(): void
    {
        Schema::table('payment_methods', function (Blueprint $table) {
            if (! Schema::hasColumn('payment_methods', 'sort_order')) {
                $table->integer('sort_order')->default(0)->after('is_active');
            }
        });

        if (! Schema::hasTable('permissions')) {
            return;
        }

        foreach (self::PERMISSIONS as $name) {
            Permission::findOrCreate($name, 'web');
        }

        // Equivalent-access copy (roles and direct user permissions).
        $map = [
            'settings.payment-accounts.view' => ['payments.accounts.view', 'payments.transactions.view', 'payments.webhooks.view'],
            'settings.payment-accounts.manage' => ['payments.accounts.manage'],
        ];
        $id = fn (string $n) => Permission::where('name', $n)->where('guard_name', 'web')->value('id');
        foreach ($map as $old => $news) {
            $oldId = $id($old);
            if (! $oldId) {
                continue;
            }
            foreach ($news as $new) {
                $newId = $id($new);
                foreach (DB::table('role_has_permissions')->where('permission_id', $oldId)->pluck('role_id') as $roleId) {
                    DB::table('role_has_permissions')->insertOrIgnore(['permission_id' => $newId, 'role_id' => $roleId]);
                }
                foreach (DB::table('model_has_permissions')->where('permission_id', $oldId)->get() as $row) {
                    DB::table('model_has_permissions')->insertOrIgnore(['permission_id' => $newId, 'model_type' => $row->model_type, 'model_id' => $row->model_id]);
                }
            }
        }

        foreach (\Spatie\Permission\Models\Role::query()->get() as $role) {
            if (strtolower((string) preg_replace('/[^a-z0-9]/i', '', $role->name)) === 'superadmin') {
                $role->givePermissionTo(self::PERMISSIONS);
            }
        }
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        // sort_order and permissions are intentionally kept (non-destructive rollback).
    }
};
