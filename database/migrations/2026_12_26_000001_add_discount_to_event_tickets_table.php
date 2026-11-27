<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Optional timed discount on a ticket type: percent or fixed amount off, valid until discount_ends_at (and from discount_starts_at, if set). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('event_tickets', function (Blueprint $table) {
            if (! Schema::hasColumn('event_tickets', 'discount_type')) {
                $table->string('discount_type', 10)->nullable()->after('price');
                $table->decimal('discount_value', 10, 2)->nullable()->after('discount_type');
                $table->dateTime('discount_starts_at')->nullable()->after('discount_value');
                $table->dateTime('discount_ends_at')->nullable()->after('discount_starts_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('event_tickets', function (Blueprint $table) {
            $table->dropColumn(['discount_type', 'discount_value', 'discount_starts_at', 'discount_ends_at']);
        });
    }
};
