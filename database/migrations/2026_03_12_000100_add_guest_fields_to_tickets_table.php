<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Guarded: some databases already have these columns (applied outside the migrations table).
        if (! Schema::hasColumn('tickets', 'source')) {
            Schema::table('tickets', function (Blueprint $table) {
                $table->string('source')->default('standard')->after('name');
            });
        }

        if (! Schema::hasColumn('tickets', 'guest_type')) {
            Schema::table('tickets', function (Blueprint $table) {
                $table->string('guest_type')->nullable()->after('source');
            });
        }
    }

    public function down(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->dropColumn(['source', 'guest_type']);
        });
    }
};
