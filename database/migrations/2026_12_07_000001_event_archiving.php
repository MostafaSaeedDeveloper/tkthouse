<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;

/**
 * Archiving instead of deleting events. Additive only: three nullable columns on events; no existing row changes.
 * The new permissions are given only to roles/users who can already delete events (archiving is the gentler version of that
 * power) and to super-admin roles; nobody else gains anything.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            if (! Schema::hasColumn('events', 'archived_at')) {
                $table->timestamp('archived_at')->nullable()->after('requires_booking_approval');
                $table->unsignedBigInteger('archived_by')->nullable()->after('archived_at');
                $table->string('status_before_archive', 20)->nullable()->after('archived_by');
                $table->index('archived_at');
            }
        });

        if (! Schema::hasTable('permissions')) {
            return;
        }
        foreach (['events.archive', 'events.restore'] as $name) {
            Permission::findOrCreate($name, 'web');
        }
        $id = fn (string $n) => Permission::where('name', $n)->where('guard_name', 'web')->value('id');
        $delete = $id('events.delete');
        if ($delete) {
            foreach (['events.archive', 'events.restore'] as $new) {
                $newId = $id($new);
                foreach (DB::table('role_has_permissions')->where('permission_id', $delete)->pluck('role_id') as $roleId) {
                    DB::table('role_has_permissions')->insertOrIgnore(['permission_id' => $newId, 'role_id' => $roleId]);
                }
                foreach (DB::table('model_has_permissions')->where('permission_id', $delete)->get() as $row) {
                    DB::table('model_has_permissions')->insertOrIgnore(['permission_id' => $newId, 'model_type' => $row->model_type, 'model_id' => $row->model_id]);
                }
            }
        }
        foreach (\Spatie\Permission\Models\Role::query()->get() as $role) {
            if (strtolower((string) preg_replace('/[^a-z0-9]/i', '', $role->name)) === 'superadmin') {
                $role->givePermissionTo(['events.archive', 'events.restore']);
            }
        }
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        // Archived events keep their data; the status 'archived' is mapped back to the previous status before the columns go.
        if (Schema::hasColumn('events', 'archived_at')) {
            DB::table('events')->whereNotNull('archived_at')->update(['status' => DB::raw("COALESCE(status_before_archive, 'inactive')")]);
            Schema::table('events', function (Blueprint $table) {
                $table->dropIndex(['archived_at']);
                $table->dropColumn(['archived_at', 'archived_by', 'status_before_archive']);
            });
        }
    }
};
