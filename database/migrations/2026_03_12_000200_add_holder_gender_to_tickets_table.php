<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('tickets', 'holder_gender')) {
            Schema::table('tickets', function (Blueprint $table) {
                $table->string('holder_gender')->nullable()->after('holder_phone');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('tickets', 'holder_gender')) {
            Schema::table('tickets', function (Blueprint $table) {
                $table->dropColumn('holder_gender');
            });
        }
    }
};
