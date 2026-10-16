<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Additive only. Old attempts keep NULL in every new column and stay fully functional. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payment_attempts', function (Blueprint $table) {
            if (! Schema::hasColumn('payment_attempts', 'internal_reference')) {
                $table->string('internal_reference', 40)->nullable()->unique();   // OUR reference, separate from any provider id
                $table->string('provider_order_id')->nullable();                  // Paymob order id / Fawaterak invoice key
                $table->string('provider_reference')->nullable();                 // e.g. Fawaterak referenceNumber
                $table->timestamp('paid_at')->nullable();
                $table->timestamp('last_checked_at')->nullable();
                $table->index(['provider', 'provider_order_id']);
                $table->index(['provider', 'provider_reference']);
            }
        });

        Schema::table('integration_accounts', function (Blueprint $table) {
            if (! Schema::hasColumn('integration_accounts', 'webhook_configured_at')) {
                $table->timestamp('webhook_configured_at')->nullable();   // an operator confirmed the URL is set in the provider portal
                $table->unsignedBigInteger('webhook_configured_by')->nullable();
            }
        });

        if (! Schema::hasTable('webhook_events')) {
            Schema::create('webhook_events', function (Blueprint $table) {
                $table->id();
                $table->string('provider', 40);
                $table->foreignId('integration_account_id')->nullable()->constrained('integration_accounts')->restrictOnDelete();
                $table->unsignedBigInteger('payment_attempt_id')->nullable()->index();
                $table->unsignedBigInteger('order_id')->nullable()->index();
                $table->string('event_type', 40);                       // payment.succeeded|payment.failed|payment.pending|payment.cancelled|refund|void|unknown
                $table->string('dedupe_key', 64);
                $table->string('transaction_reference')->nullable()->index();
                $table->string('signature_status', 20);                 // valid|invalid|missing|unverifiable|unsupported
                $table->string('processing_status', 20)->default('received'); // received|processed|ignored|failed|needs_review
                $table->string('error', 300)->nullable();               // safe text only
                $table->text('payload')->nullable();                    // encrypted JSON, whitelisted fields (never card data / secrets)
                $table->unsignedInteger('deliveries')->default(1);
                $table->unsignedInteger('process_attempts')->default(0);
                $table->timestamp('received_at')->useCurrent();
                $table->timestamp('processed_at')->nullable();
                $table->timestamps();
                $table->unique(['provider', 'dedupe_key']);
                $table->index(['provider', 'processing_status']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('webhook_events');
    }
};
