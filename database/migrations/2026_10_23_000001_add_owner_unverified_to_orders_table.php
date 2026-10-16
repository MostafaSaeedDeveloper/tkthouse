<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Orders placed by a guest whose mailbox was not proven yet. They are reachable from the buying browser only
     * (session grant) and stay out of account pages until the owner claims them with an e-mail code.
     * Existing rows default to false, so nothing already placed changes visibility.
     */
    public function up(): void
    {
        if (Schema::hasColumn('orders', 'owner_unverified')) {
            return; // already present outside the migrations table
        }

        Schema::table('orders', function (Blueprint $table) {
            $table->boolean('owner_unverified')->default(false)->after('user_id');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('owner_unverified');
        });
    }
};
