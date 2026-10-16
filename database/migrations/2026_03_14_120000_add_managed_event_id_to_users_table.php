<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('users', 'managed_event_id')) {
            return; // already present outside the migrations table
        }

        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('managed_event_id')
                ->nullable()
                ->after('last_login_ip')
                ->constrained('events')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('managed_event_id');
        });
    }
};
