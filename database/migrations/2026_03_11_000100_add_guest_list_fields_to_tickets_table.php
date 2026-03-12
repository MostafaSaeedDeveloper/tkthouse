<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->foreignId('event_id')->nullable()->after('order_item_id')->constrained()->nullOnDelete();
            $table->string('ticket_source')->default('sale')->after('status');
            $table->string('guest_category')->nullable()->after('holder_phone');
            $table->timestamp('invitation_sent_at')->nullable()->after('issued_at');
        });
    }

    public function down(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->dropConstrainedForeignId('event_id');
            $table->dropColumn(['ticket_source', 'guest_category', 'invitation_sent_at']);
        });
    }
};
