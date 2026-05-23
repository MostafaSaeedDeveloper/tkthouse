<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('event_lineups', function (Blueprint $table) {
            $table->date('performance_date')->nullable()->after('performance_time');
        });
    }

    public function down(): void
    {
        Schema::table('event_lineups', function (Blueprint $table) {
            $table->dropColumn('performance_date');
        });
    }
};
