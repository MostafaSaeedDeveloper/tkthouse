<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;

/**
 * Transactions that exist at the provider but were not created by TKT House (looked up by id or imported from a dashboard CSV),
 * and a log of provider syncs. Additive only. Imported rows never create orders and never change an order or an attempt.
 * New permission payments.transactions.import is copied only from payments.accounts.manage holders (and super admin).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('external_transactions')) {
            Schema::create('external_transactions', function (Blueprint $table) {
                $table->id();
                $table->string('provider', 20);
                $table->foreignId('integration_account_id')->constrained('integration_accounts')->restrictOnDelete();
                $table->string('provider_transaction_id', 120);
                $table->string('provider_order_id', 120)->nullable();
                $table->string('provider_reference', 190)->nullable();      // merchant order id / invoice key / reference number
                $table->bigInteger('amount_minor')->nullable();
                $table->string('currency', 8)->nullable();
                $table->string('status', 20)->default('unknown');           // paid | pending | failed | refunded | voided | unknown
                $table->string('provider_status', 60)->nullable();           // the provider's own word
                $table->string('payment_method', 60)->nullable();
                $table->bigInteger('fee_minor')->nullable();
                $table->bigInteger('refunded_amount_minor')->nullable();
                $table->boolean('is_refunded')->default(false);
                $table->boolean('is_voided')->default(false);
                $table->string('customer_label', 190)->nullable();           // e-mail when the provider gives one; shown only to permitted staff
                $table->timestamp('provider_created_at')->nullable();
                $table->timestamp('paid_at')->nullable();
                $table->string('source', 20);                                // api_lookup | csv_import
                $table->timestamp('verified_at')->nullable();                // set only by an official API lookup
                $table->timestamp('last_checked_at')->nullable();
                $table->unsignedBigInteger('matched_attempt_id')->nullable(); // exact provider-id match with a local payment attempt, never a guess
                $table->unsignedBigInteger('imported_by')->nullable();
                $table->json('snapshot')->nullable();                        // whitelisted scalar fields only (no card data)
                $table->timestamps();
                $table->unique(['provider', 'integration_account_id', 'provider_transaction_id'], 'ext_tx_unique');
                $table->index(['status', 'provider_created_at']);
                $table->index('matched_attempt_id');
            });
        }

        if (! Schema::hasTable('provider_syncs')) {
            Schema::create('provider_syncs', function (Blueprint $table) {
                $table->id();
                $table->foreignId('integration_account_id')->constrained('integration_accounts')->restrictOnDelete();
                $table->string('trigger', 12);                               // manual | scheduled
                $table->string('status', 12)->default('running');            // running | succeeded | partial | failed | skipped
                $table->unsignedInteger('examined')->default(0);
                $table->unsignedInteger('updated')->default(0);
                $table->unsignedInteger('errors')->default(0);
                $table->boolean('rate_limited')->default(false);
                $table->text('message')->nullable();
                $table->unsignedBigInteger('started_by')->nullable();
                $table->timestamp('started_at')->nullable();
                $table->timestamp('finished_at')->nullable();
                $table->timestamps();
                $table->index(['integration_account_id', 'id']);
            });
        }

        if (Schema::hasTable('permissions')) {
            Permission::findOrCreate('payments.transactions.import', 'web');
            $id = fn (string $n) => Permission::where('name', $n)->where('guard_name', 'web')->value('id');
            $from = $id('payments.accounts.manage');
            $new = $id('payments.transactions.import');
            if ($from) {
                foreach (DB::table('role_has_permissions')->where('permission_id', $from)->pluck('role_id') as $roleId) {
                    DB::table('role_has_permissions')->insertOrIgnore(['permission_id' => $new, 'role_id' => $roleId]);
                }
                foreach (DB::table('model_has_permissions')->where('permission_id', $from)->get() as $row) {
                    DB::table('model_has_permissions')->insertOrIgnore(['permission_id' => $new, 'model_type' => $row->model_type, 'model_id' => $row->model_id]);
                }
            }
            foreach (\Spatie\Permission\Models\Role::query()->get() as $role) {
                if (strtolower((string) preg_replace('/[^a-z0-9]/i', '', $role->name)) === 'superadmin') {
                    $role->givePermissionTo('payments.transactions.import');
                }
            }
            app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('provider_syncs');
        Schema::dropIfExists('external_transactions');
    }
};
