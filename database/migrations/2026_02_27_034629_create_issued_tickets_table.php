<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('issued_tickets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('order_item_id')->constrained()->cascadeOnDelete();
            $table->string('uuid')->unique();
            $table->string('ticket_number')->unique();
            $table->string('holder_name');
            $table->string('holder_email');
            $table->string('holder_phone')->nullable();
            $table->string('ticket_name');
            $table->decimal('ticket_price', 10, 2)->default(0);
            $table->unsignedInteger('seat_index')->default(1);
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();

            $table->unique(['order_item_id', 'seat_index']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('issued_tickets');
    }
};
