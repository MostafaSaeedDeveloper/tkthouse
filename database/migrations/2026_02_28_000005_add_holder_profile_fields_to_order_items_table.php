<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->string('holder_gender', 20)->nullable()->after('holder_phone');
            $table->string('holder_social_profile')->nullable()->after('holder_gender');
        });
    }

    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->dropColumn(['holder_gender', 'holder_social_profile']);
        });
    }
};
