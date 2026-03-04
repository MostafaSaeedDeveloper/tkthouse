<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('promo_codes', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('discount_type')->default('percent');
            $table->decimal('discount_value', 10, 2);
            $table->unsignedInteger('usage_limit')->nullable();
            $table->unsignedInteger('used_count')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->timestamps();
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->foreignId('promo_code_id')->nullable()->after('affiliate_user_id')->constrained('promo_codes')->nullOnDelete();
            $table->string('promo_code')->nullable()->after('promo_code_id');
            $table->decimal('discount_amount', 10, 2)->default(0)->after('promo_code');
            $table->decimal('subtotal_amount', 10, 2)->default(0)->after('discount_amount');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('promo_code_id');
            $table->dropColumn(['promo_code', 'discount_amount', 'subtotal_amount']);
        });

        Schema::dropIfExists('promo_codes');
    }
};
