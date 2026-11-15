<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Optional per-ticket gender ('male' | 'female'). NULL = the buyer picks the gender per attendee, as before. */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('event_tickets', 'gender')) {
            Schema::table('event_tickets', function (Blueprint $table) {
                $table->string('gender', 10)->nullable()->after('is_couple');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('event_tickets', 'gender')) {
            Schema::table('event_tickets', function (Blueprint $table) {
                $table->dropColumn('gender');
            });
        }
    }
};
