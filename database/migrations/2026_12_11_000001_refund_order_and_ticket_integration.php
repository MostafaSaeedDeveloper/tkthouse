<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Additive only. Connects confirmed refunds to orders and tickets and lets legacy paid orders (no payment attempt) be refunded
 * manually without inventing a gateway transaction. Nothing is backfilled: existing refunds, orders and tickets are left as they are
 * (a refund that succeeded before this migration has effects_status NULL = "predates ticket integration", and is never touched automatically).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payment_refunds', function (Blueprint $table) {
            if (! Schema::hasColumn('payment_refunds', 'captured_minor')) {
                $table->unsignedBigInteger('captured_minor')->nullable();      // what was paid at the moment of the request (snapshot, audit)
                $table->string('payment_method', 100)->nullable();            // order payment method at request time (snapshot)
                $table->string('external_method', 30)->nullable();            // manual refunds: provider_portal | bank_transfer | cash | wallet | other
                $table->text('internal_notes')->nullable();                   // staff-only; never shown to scanners or customers
                $table->timestamp('refunded_at')->nullable();                 // the date the money actually went back (manual: entered by staff)
                $table->unsignedBigInteger('submitted_by')->nullable();
                $table->unsignedBigInteger('confirmed_by')->nullable();
                $table->timestamp('confirmed_at')->nullable();
                $table->string('effects_status', 20)->nullable();            // pending | applied | needs_review | not_applicable ; NULL = predates this feature
                $table->timestamp('effects_applied_at')->nullable();
                $table->string('effects_note', 300)->nullable();
                $table->index(['status', 'completed_at']);
            }
        });

        // Legacy paid orders have no payment attempt: the refund is recorded against the order itself.
        Schema::table('payment_refunds', function (Blueprint $table) {
            $table->unsignedBigInteger('payment_attempt_id')->nullable()->change();
        });

        if (! Schema::hasTable('payment_refund_tickets')) {
            Schema::create('payment_refund_tickets', function (Blueprint $table) {
                $table->id();
                $table->foreignId('payment_refund_id')->constrained('payment_refunds')->restrictOnDelete();
                $table->foreignId('ticket_id')->constrained('tickets')->restrictOnDelete();
                $table->unsignedBigInteger('amount_minor')->default(0);       // the ticket's share of the paid amount (informational)
                $table->timestamp('canceled_at')->nullable();                // set when this refund really invalidated the ticket
                $table->boolean('was_checked_in')->default(false);
                $table->timestamps();
                $table->unique(['payment_refund_id', 'ticket_id']);
                $table->index('ticket_id');
            });
        }

        Schema::table('tickets', function (Blueprint $table) {
            if (! Schema::hasColumn('tickets', 'cancellation_source')) {
                $table->string('cancellation_source', 20)->nullable()->index();   // refund | admin ; NULL on older rows
                $table->string('cancellation_reason', 300)->nullable();          // safe, scanner-visible text
                $table->unsignedBigInteger('canceled_by_refund_id')->nullable()->index();
                $table->string('refund_flag', 40)->nullable();                   // refunded_after_check_in
            }
        });
    }

    public function down(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            foreach (['cancellation_source', 'canceled_by_refund_id'] as $col) {
                if (Schema::hasColumn('tickets', $col)) {
                    $table->dropIndex([$col]);
                }
            }
            $table->dropColumn(['cancellation_source', 'cancellation_reason', 'canceled_by_refund_id', 'refund_flag']);
        });
        Schema::dropIfExists('payment_refund_tickets');
        // payment_attempt_id stays nullable and the refund columns stay: dropping them could destroy financial records.
    }
};
