<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('order_items', 'event_id')) Schema::table('order_items', function (Blueprint $table) {
            $table->foreignId('event_id')->nullable()->after('order_id')->constrained()->nullOnDelete();
        });

        if (! Schema::hasColumn('scan_logs', 'event_id')) Schema::table('scan_logs', function (Blueprint $table) {
            $table->foreignId('event_id')->nullable()->after('ticket_id')->constrained()->nullOnDelete();
        });

        if (! Schema::hasColumn('tickets', 'event_id')) Schema::table('tickets', function (Blueprint $table) {
            $table->foreignId('event_id')->nullable()->after('id')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('event_id');
        });

        Schema::table('scan_logs', function (Blueprint $table) {
            $table->dropConstrainedForeignId('event_id');
        });

        Schema::table('tickets', function (Blueprint $table) {
            $table->dropConstrainedForeignId('event_id');
        });
    }
};
