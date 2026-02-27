<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->timestamp('checked_in_at')->nullable()->after('issued_at');
            $table->timestamp('canceled_at')->nullable()->after('checked_in_at');
        });

        DB::table('tickets')->whereIn('status', ['active', 'inactive', 'sold_out'])->update(['status' => 'not_checked_in']);
    }

    public function down(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->dropColumn(['checked_in_at', 'canceled_at']);
        });
    }
};
