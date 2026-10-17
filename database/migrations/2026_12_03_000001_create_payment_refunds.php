<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Financial refund records. Separate from order status and from tickets: a refund never cancels an order or deletes a ticket by itself.
 * Amounts are stored in minor units (integer) so partial refunds never suffer float errors.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('integration_accounts', function (Blueprint $table) {
            if (! Schema::hasColumn('integration_accounts', 'refund_enabled_at')) {
                $table->timestamp('refund_enabled_at')->nullable();      // an operator confirmed refunds work for this account (sandbox run)
                $table->unsignedBigInteger('refund_enabled_by')->nullable();
            }
        });

        if (! Schema::hasTable('payment_refunds')) {
            Schema::create('payment_refunds', function (Blueprint $table) {
                $table->id();
                $table->string('internal_reference', 40)->unique();
                $table->foreignId('payment_attempt_id')->constrained('payment_attempts')->restrictOnDelete();
                $table->foreignId('order_id')->constrained('orders')->restrictOnDelete();
                $table->foreignId('integration_account_id')->nullable()->constrained('integration_accounts')->restrictOnDelete(); // copied from the attempt: never the current assignee's account
                $table->string('provider', 40);
                $table->string('mode', 10);                    // api | manual
                $table->string('type', 10);                    // full | partial | void
                $table->string('status', 20);                  // requested | processing | succeeded | failed | rejected | unknown
                $table->boolean('awaiting_approval')->default(false);
                $table->string('currency', 8)->default('EGP');
                $table->unsignedBigInteger('amount_minor');
                $table->string('reason', 300);
                $table->string('idempotency_key', 64)->unique();
                $table->string('provider_refund_id')->nullable();
                $table->string('provider_reference')->nullable();
                $table->unsignedBigInteger('requested_by')->nullable();
                $table->unsignedBigInteger('approved_by')->nullable();
                $table->timestamp('requested_at')->useCurrent();
                $table->timestamp('submitted_at')->nullable();
                $table->timestamp('completed_at')->nullable();
                $table->timestamp('last_checked_at')->nullable();
                $table->string('failure_reason', 300)->nullable();   // safe text only
                $table->json('meta')->nullable();                    // timeline + evidence (never secrets or card data)
                $table->timestamps();
                $table->index(['payment_attempt_id', 'status']);
                $table->index(['provider', 'provider_refund_id']);
                $table->index(['status', 'mode']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_refunds');
    }
};
