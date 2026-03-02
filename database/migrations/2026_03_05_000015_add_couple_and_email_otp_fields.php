<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('event_tickets', function (Blueprint $table) {
            $table->boolean('is_couple')->default(false)->after('max_per_order');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->string('email_otp_code')->nullable()->after('email_verified_at');
            $table->timestamp('email_otp_expires_at')->nullable()->after('email_otp_code');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['email_otp_code', 'email_otp_expires_at']);
        });

        Schema::table('event_tickets', function (Blueprint $table) {
            $table->dropColumn('is_couple');
        });
    }
};
