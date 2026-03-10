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
            $table->string('source', 20)->default('standard')->after('event_id');
            $table->index(['source', 'event_id']);
        });
    }

    public function down(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->dropIndex(['source', 'event_id']);
            $table->dropConstrainedForeignId('event_id');
            $table->dropColumn('source');
        });
    }
};
