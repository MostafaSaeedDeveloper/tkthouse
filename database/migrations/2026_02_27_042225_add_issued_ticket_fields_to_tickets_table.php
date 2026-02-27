<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->foreignId('order_id')->nullable()->after('id')->constrained()->nullOnDelete();
            $table->foreignId('order_item_id')->nullable()->after('order_id')->constrained()->nullOnDelete();
            $table->string('ticket_number')->nullable()->unique()->after('status');
            $table->string('holder_name')->nullable()->after('ticket_number');
            $table->string('holder_email')->nullable()->after('holder_name');
            $table->string('holder_phone')->nullable()->after('holder_email');
            $table->text('qr_payload')->nullable()->after('holder_phone');
            $table->timestamp('issued_at')->nullable()->after('qr_payload');
        });
    }

    public function down(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->dropConstrainedForeignId('order_item_id');
            $table->dropConstrainedForeignId('order_id');
            $table->dropColumn(['ticket_number', 'holder_name', 'holder_email', 'holder_phone', 'qr_payload', 'issued_at']);
        });
    }
};
