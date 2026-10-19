<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * "Default" payment methods: the ones offered when nothing narrows the list (order not assigned to an agent, or an agent with no
 * payment-method selection). Additive: no existing row changes, and while NO active method is flagged default the checkout behaves exactly as before.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payment_methods', function (Blueprint $table) {
            if (! Schema::hasColumn('payment_methods', 'is_default')) {
                $table->boolean('is_default')->default(false)->after('is_active');
            }
        });
    }

    public function down(): void
    {
        Schema::table('payment_methods', function (Blueprint $table) {
            if (Schema::hasColumn('payment_methods', 'is_default')) {
                $table->dropColumn('is_default');
            }
        });
    }
};
