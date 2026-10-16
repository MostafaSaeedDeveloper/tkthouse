<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('orders', 'payment_timeout_minutes')) {
            return; // already present outside the migrations table
        }

        Schema::table('orders', function (Blueprint $table) {
            $table->unsignedInteger('payment_timeout_minutes')
                ->nullable()
                ->after('payment_timeout_started_at');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('payment_timeout_minutes');
        });
    }
};

