<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Marks accounts created automatically by Express Checkout (no password chosen yet). Nullable and NULL for every
 * existing user, so nothing about current accounts changes. Cleared when the customer sets a password.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('passwordless_since')->nullable()->after('email_verified_at');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('passwordless_since');
        });
    }
};
