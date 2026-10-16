<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A gateway transaction / invoice id can belong to exactly one payment attempt. NULLs stay allowed
 * (attempts that have not reached the gateway yet). Additive: the table only holds data created by Checkout V2.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasIndex('payment_attempts', 'payment_attempts_provider_tx_unique')) {
            return;
        }

        Schema::table('payment_attempts', function (Blueprint $table) {
            $table->unique(['provider', 'transaction_id'], 'payment_attempts_provider_tx_unique');
        });
    }

    public function down(): void
    {
        Schema::table('payment_attempts', function (Blueprint $table) {
            $table->dropUnique('payment_attempts_provider_tx_unique');
        });
    }
};
