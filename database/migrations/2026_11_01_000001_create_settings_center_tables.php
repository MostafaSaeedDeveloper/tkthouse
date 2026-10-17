<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;

/**
 * Additive only. Existing system_settings rows are untouched (group/updated_by are nullable).
 */
return new class extends Migration
{
    public const PERMISSIONS = [
        'settings.orders',
        'settings.integrations.view',
        'settings.integrations.manage',
        'settings.integrations.test',
        'settings.security.view',
    ];

    public function up(): void
    {
        Schema::table('system_settings', function (Blueprint $table) {
            if (! Schema::hasColumn('system_settings', 'group')) {
                $table->string('group', 40)->nullable()->after('key');
                $table->unsignedBigInteger('updated_by')->nullable()->after('value');
            }
        });

        if (! Schema::hasTable('setting_audits')) {
            Schema::create('setting_audits', function (Blueprint $table) {
                $table->id();
                $table->string('key');
                $table->string('group', 40)->nullable();
                $table->text('old_value')->nullable();
                $table->text('new_value')->nullable();
                $table->unsignedBigInteger('user_id')->nullable();
                $table->string('ip', 45)->nullable();
                $table->timestamp('created_at')->useCurrent();
                $table->index(['key', 'created_at']);
            });
        }

        if (! Schema::hasTable('integration_accounts')) {
            Schema::create('integration_accounts', function (Blueprint $table) {
                $table->id();
                $table->string('provider', 40);              // paymob | fawaterak | ultramsg | email
                $table->string('name');
                $table->string('environment', 20)->default('sandbox'); // sandbox | production
                $table->boolean('enabled')->default(false);
                $table->string('status', 20)->default('unverified');   // unverified | verified | error
                $table->timestamp('last_tested_at')->nullable();
                $table->timestamp('last_success_at')->nullable();
                $table->string('last_error', 300)->nullable();
                $table->json('config')->nullable();          // non-secret fields
                $table->text('secrets')->nullable();         // encrypted JSON (encrypted:array cast)
                $table->string('credential_fingerprint', 32)->nullable();
                $table->string('source', 20)->default('manual'); // manual | legacy_import
                $table->string('legacy_ref')->nullable();
                $table->unsignedBigInteger('created_by')->nullable();
                $table->unsignedBigInteger('updated_by')->nullable();
                $table->timestamps();
                $table->unique(['provider', 'name']);
                $table->index(['provider', 'enabled']);
            });
        }

        if (Schema::hasTable('permissions')) {
            foreach (self::PERMISSIONS as $name) {
                Permission::findOrCreate($name, 'web');
            }
            // Super-admin roles (any spelling) get the new permissions; every other role stays opt-in.
            foreach (\Spatie\Permission\Models\Role::query()->get() as $role) {
                if (strtolower((string) preg_replace('/[^a-z0-9]/i', '', $role->name)) === 'superadmin') {
                    $role->givePermissionTo(self::PERMISSIONS);
                }
            }
            app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('integration_accounts');
        Schema::dropIfExists('setting_audits');
        // system_settings columns and permissions are intentionally kept (non-destructive rollback).
    }
};
