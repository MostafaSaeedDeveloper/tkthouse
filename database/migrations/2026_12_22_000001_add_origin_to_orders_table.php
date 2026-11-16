<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Where the sale came from (instagram, organic, affiliate, ...). NULL = not recorded (older orders). */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('orders', 'origin')) {
            Schema::table('orders', function (Blueprint $table) {
                $table->string('origin', 50)->nullable()->index();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('orders', 'origin')) {
            Schema::table('orders', function (Blueprint $table) {
                $table->dropColumn('origin');
            });
        }
    }
};
