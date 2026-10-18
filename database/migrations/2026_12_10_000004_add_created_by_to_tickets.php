<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Who added a guest-list ticket, so each person sees only their own guest list (Super Admin sees all).
 * Existing guest tickets have no creator on record and stay visible to Super Admin only.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            if (! Schema::hasColumn('tickets', 'created_by_user_id')) {
                $table->unsignedBigInteger('created_by_user_id')->nullable()->index();
            }
        });
    }

    public function down(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            if (Schema::hasColumn('tickets', 'created_by_user_id')) {
                $table->dropIndex(['created_by_user_id']);
                $table->dropColumn('created_by_user_id');
            }
        });
    }
};
