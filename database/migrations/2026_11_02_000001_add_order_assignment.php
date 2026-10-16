<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * Additive only. Existing orders get NULL everywhere (= unassigned / unattributed); nothing is backfilled.
 * The feature stays OFF (no setting row) until an admin enables it in Settings -> Orders.
 */
return new class extends Migration
{
    public const PERMISSIONS = [
        'orders.assign', 'orders.reassign', 'orders.view_all', 'orders.view_assigned',
        'orders.manage_assigned', 'orders.view_unassigned', 'orders.view_agent_stats',
    ];

    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'is_active')) {
                $table->boolean('is_active')->default(true);
                $table->boolean('can_receive_orders')->default(false);
            }
        });

        Schema::table('orders', function (Blueprint $table) {
            if (! Schema::hasColumn('orders', 'assigned_user_id')) {
                $table->foreignId('assigned_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('assigned_by_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('assigned_at')->nullable();
                // Sales attribution snapshot: written once when payment is confirmed, never rewritten by reassignment.
                $table->foreignId('sold_by_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('sold_at')->nullable();
                $table->index(['assigned_user_id', 'status']);
                $table->index('sold_by_user_id');
            }
        });

        if (! Schema::hasTable('order_assignments')) {
            Schema::create('order_assignments', function (Blueprint $table) {
                $table->id();
                $table->foreignId('order_id')->constrained()->cascadeOnDelete();
                $table->unsignedBigInteger('from_user_id')->nullable();
                $table->unsignedBigInteger('to_user_id')->nullable();
                $table->unsignedBigInteger('changed_by_user_id')->nullable();
                $table->string('source', 20)->default('manual'); // manual | round_robin | default_assignee | system
                $table->string('reason', 300)->nullable();
                $table->json('meta')->nullable();             // name snapshots so history survives user deletion
                $table->timestamp('created_at')->useCurrent();
                $table->index(['order_id', 'id']);
            });
        }

        if (! Schema::hasTable('assignment_state')) {
            Schema::create('assignment_state', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('last_assigned_user_id')->nullable();
                $table->timestamp('updated_at')->nullable();
            });
            DB::table('assignment_state')->insert(['last_assigned_user_id' => null, 'updated_at' => now()]);
        }

        if (Schema::hasTable('permissions')) {
            foreach (array_merge(self::PERMISSIONS, ['orders.view', 'orders.update', 'update_status', 'dashboard.view']) as $name) {
                Permission::findOrCreate($name, 'web');
            }
            Role::findOrCreate('Order Manager', 'web')->givePermissionTo(['dashboard.view', 'orders.view', 'orders.update', 'update_status', 'orders.view_all', 'orders.assign', 'orders.reassign', 'orders.view_unassigned', 'orders.view_agent_stats']);
            Role::findOrCreate('Order Agent', 'web')->givePermissionTo(['dashboard.view', 'orders.view', 'orders.update', 'update_status', 'orders.view_assigned', 'orders.manage_assigned']);
            app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('assignment_state');
        Schema::dropIfExists('order_assignments');
        // Columns/permissions/roles are intentionally kept: a rollback must never lose assignment history.
    }
};
