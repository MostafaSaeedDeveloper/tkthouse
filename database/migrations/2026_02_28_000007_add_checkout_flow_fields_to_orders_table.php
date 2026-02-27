<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->after('customer_id')->constrained()->nullOnDelete();
            $table->boolean('requires_approval')->default(false)->after('status');
            $table->string('payment_method')->default('pending_review')->after('requires_approval');
            $table->string('payment_status')->default('unpaid')->after('payment_method');
            $table->string('payment_link_token')->nullable()->unique()->after('payment_status');
            $table->timestamp('approved_at')->nullable()->after('payment_link_token');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('user_id');
            $table->dropColumn(['requires_approval', 'payment_method', 'payment_status', 'payment_link_token', 'approved_at']);
        });
    }
};
