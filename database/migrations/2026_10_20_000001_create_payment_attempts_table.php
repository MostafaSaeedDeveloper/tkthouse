<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Additive only: one row per attempt to pay an order at a gateway. It gives callbacks,
 * redirects and the reconciliation job a shared, idempotent record to settle against.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_attempts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->string('provider', 40);
            $table->string('method_code', 80)->nullable();
            $table->string('reference')->nullable();      // merchant reference sent to the gateway
            $table->string('transaction_id')->nullable(); // gateway transaction / invoice id
            $table->decimal('amount', 10, 2);
            $table->string('currency', 8)->default('EGP');
            $table->string('status', 20)->default('initiated'); // initiated|paid|failed|expired
            $table->string('failure_reason')->nullable();
            $table->json('meta')->nullable();
            $table->timestamp('settled_at')->nullable();
            $table->timestamps();

            $table->index(['order_id', 'status']);
            $table->index(['provider', 'transaction_id']);
            $table->index(['provider', 'reference']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_attempts');
    }
};
