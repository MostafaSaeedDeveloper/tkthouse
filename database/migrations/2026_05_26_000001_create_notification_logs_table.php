<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notification_logs', function (Blueprint $table) {
            $table->id();
            $table->string('channel', 20);          // email | whatsapp
            $table->string('type', 60);             // e.g. admin_ticket_issued, order_tickets …
            $table->string('recipient')->nullable(); // email address or phone number
            $table->unsignedBigInteger('order_id')->nullable();
            $table->unsignedBigInteger('ticket_id')->nullable();
            $table->string('subject')->nullable();   // email subject or message summary
            $table->string('status', 20);            // sent | failed | skipped
            $table->text('error')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['channel', 'created_at']);
            $table->index(['status', 'created_at']);
            $table->index('order_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_logs');
    }
};
