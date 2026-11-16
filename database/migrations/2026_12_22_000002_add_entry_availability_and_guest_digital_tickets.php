<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * 1. Entry Availability: when the scanner lets people in, separate from when customers can see their QR (default: always open, so nothing changes).
 * 2. Guest-list tickets become digital tickets too: an issued ticket may now exist without an order.
 * 3. Two clear permissions for the new controls. Roles that could edit events keep that ability (they receive both); super admins too.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->string('entry_mode', 20)->default('always')->after('qr_visibility_changed_at');
            $table->unsignedInteger('entry_opens_minutes_before')->nullable()->after('entry_mode');
            $table->string('entry_override', 10)->nullable()->after('entry_opens_minutes_before');
            $table->unsignedBigInteger('entry_changed_by')->nullable()->after('entry_override');
            $table->timestamp('entry_changed_at')->nullable()->after('entry_changed_by');
        });

        Schema::table('issued_tickets', function (Blueprint $table) {
            $table->unsignedBigInteger('order_id')->nullable()->change();
            $table->unsignedBigInteger('order_item_id')->nullable()->change();
        });

        foreach (['events.qr-visibility.manage', 'events.entry.manage'] as $name) {
            Permission::findOrCreate($name, 'web');
        }
        $ids = Permission::whereIn('name', ['events.qr-visibility.manage', 'events.entry.manage'])->pluck('id');
        $old = Permission::where('name', 'events.update')->where('guard_name', 'web')->value('id');
        if ($old) {
            foreach (DB::table('role_has_permissions')->where('permission_id', $old)->pluck('role_id') as $roleId) {
                foreach ($ids as $id) {
                    DB::table('role_has_permissions')->insertOrIgnore(['permission_id' => $id, 'role_id' => $roleId]);
                }
            }
        }
        foreach (Role::query()->get() as $role) {
            if (strtolower((string) preg_replace('/[^a-z0-9]/i', '', $role->name)) === 'superadmin') {
                $role->givePermissionTo(['events.qr-visibility.manage', 'events.entry.manage']);
            }
        }
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        Permission::whereIn('name', ['events.qr-visibility.manage', 'events.entry.manage'])->delete();

        Schema::table('events', function (Blueprint $table) {
            $table->dropColumn(['entry_mode', 'entry_opens_minutes_before', 'entry_override', 'entry_changed_by', 'entry_changed_at']);
        });
    }
};
