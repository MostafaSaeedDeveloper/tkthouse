<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            // The invoice is a snapshot taken when the order is paid, so later changes never rewrite an issued invoice.
            $table->string('invoice_number', 40)->nullable()->unique()->after('paid_at');
            $table->timestamp('invoice_issued_at')->nullable()->after('invoice_number');
            $table->json('invoice_snapshot')->nullable()->after('invoice_issued_at');
            // The event's fees/taxes exactly as charged at checkout (name, type, value, amount), so an invoice can itemise them.
            $table->json('fees_breakdown')->nullable()->after('discount_amount');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropUnique(['invoice_number']);
            $table->dropColumn(['invoice_number', 'invoice_issued_at', 'invoice_snapshot', 'fees_breakdown']);
        });
    }
};
