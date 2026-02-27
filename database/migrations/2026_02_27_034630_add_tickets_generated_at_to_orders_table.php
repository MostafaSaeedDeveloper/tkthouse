<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('orders', 'tickets_generated_at')) {
            Schema::table('orders', function (Blueprint $table) {
                $table->timestamp('tickets_generated_at')->nullable();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('orders', 'tickets_generated_at')) {
            Schema::table('orders', function (Blueprint $table) {
                $table->dropColumn('tickets_generated_at');
            });
        }
    }
};
