<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;

/**
 * Additive only. Every new column is nullable/defaulted: existing attempts, orders and users keep NULL and keep using the
 * legacy payment path. Foreign keys are RESTRICT so an account that has history can never be deleted.
 */
return new class extends Migration
{
    public const PERMISSIONS = ['orders.reroute_payment', 'settings.payment-accounts.view', 'settings.payment-accounts.manage'];

    public function up(): void
    {
        Schema::table('integration_accounts', function (Blueprint $table) {
            if (! Schema::hasColumn('integration_accounts', 'archived_at')) {
                $table->timestamp('archived_at')->nullable();
            }
        });

        Schema::table('payment_attempts', function (Blueprint $table) {
            if (! Schema::hasColumn('payment_attempts', 'integration_account_id')) {
                $table->foreignId('integration_account_id')->nullable()->constrained('integration_accounts')->restrictOnDelete();
                $table->json('routing_snapshot')->nullable();
                $table->index(['integration_account_id', 'status']);
            }
        });

        Schema::table('orders', function (Blueprint $table) {
            if (! Schema::hasColumn('orders', 'integration_account_id')) {
                $table->foreignId('integration_account_id')->nullable()->constrained('integration_accounts')->restrictOnDelete();
            }
        });

        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'payment_routing_mode')) {
                $table->string('payment_routing_mode', 10)->default('default'); // default | custom
                $table->foreignId('payment_account_id')->nullable()->constrained('integration_accounts')->restrictOnDelete();
                $table->json('allowed_payment_methods')->nullable();
                $table->boolean('payment_routing_enabled')->default(false);
            }
        });

        if (! Schema::hasTable('checkout_routings')) {
            Schema::create('checkout_routings', function (Blueprint $table) {
                $table->id();
                $table->string('token', 40)->unique();
                $table->string('session_hash', 64)->index();
                $table->unsignedBigInteger('assignee_id')->nullable();
                $table->foreignId('integration_account_id')->nullable()->constrained('integration_accounts')->restrictOnDelete();
                $table->string('source', 20);                 // round_robin | default_assignee | manual_default | default | blocked
                $table->json('snapshot')->nullable();
                $table->timestamp('expires_at')->index();
                $table->unsignedBigInteger('consumed_order_id')->nullable()->unique(); // a reservation can be consumed once, by one order
                $table->timestamp('released_at')->nullable();
                $table->timestamps();
            });
        }

        if (Schema::hasTable('permissions')) {
            foreach (self::PERMISSIONS as $name) {
                Permission::findOrCreate($name, 'web');
            }
            foreach (\Spatie\Permission\Models\Role::query()->get() as $role) {
                if (strtolower((string) preg_replace('/[^a-z0-9]/i', '', $role->name)) === 'superadmin') {
                    $role->givePermissionTo(self::PERMISSIONS);
                }
            }
            app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
        }
    }

    /**
     * Rolling back removes the account bindings together with the feature (take a database backup first: the binding of past
     * attempts to their merchant account is lost). It is an operator action, never run by the application.
     */
    public function down(): void
    {
        Schema::dropIfExists('checkout_routings');

        foreach ([['users', 'payment_account_id', ['payment_routing_mode', 'allowed_payment_methods', 'payment_routing_enabled']], ['orders', 'integration_account_id', []], ['payment_attempts', 'integration_account_id', ['routing_snapshot']]] as [$table, $fk, $extra]) {
            if (! Schema::hasColumn($table, $fk)) {
                continue;
            }
            Schema::table($table, function (Blueprint $t) use ($table, $fk) {
                $t->dropForeign([$fk]);
            });
            if ($table === 'payment_attempts') {
                Schema::table($table, fn (Blueprint $t) => $t->dropIndex(['integration_account_id', 'status']));
            }
            Schema::table($table, fn (Blueprint $t) => $t->dropColumn(array_merge([$fk], $extra)));
        }

        if (Schema::hasColumn('integration_accounts', 'archived_at')) {
            Schema::table('integration_accounts', fn (Blueprint $t) => $t->dropColumn('archived_at'));
        }
    }
};
