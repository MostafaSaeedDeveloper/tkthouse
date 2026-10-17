<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('payment_refunds', 'approved_at')) {
            Schema::table('payment_refunds', function (Blueprint $table) {
                $table->timestamp('approved_at')->nullable()->after('approved_by');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('payment_refunds', 'approved_at')) {
            Schema::table('payment_refunds', function (Blueprint $table) {
                $table->dropColumn('approved_at');
            });
        }
    }
};
