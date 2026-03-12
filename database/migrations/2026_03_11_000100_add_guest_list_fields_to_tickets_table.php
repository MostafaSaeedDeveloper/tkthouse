<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('tickets', 'event_id')) {
            Schema::table('tickets', function (Blueprint $table) {
                $table->foreignId('event_id')->nullable()->after('order_item_id')->constrained()->nullOnDelete();
            });
        }

        if (! Schema::hasColumn('tickets', 'ticket_source')) {
            Schema::table('tickets', function (Blueprint $table) {
                $table->string('ticket_source')->default('sale')->after('status');
            });
        }

        if (! Schema::hasColumn('tickets', 'guest_category')) {
            Schema::table('tickets', function (Blueprint $table) {
                $table->string('guest_category')->nullable()->after('holder_phone');
            });
        }

        if (! Schema::hasColumn('tickets', 'invitation_sent_at')) {
            Schema::table('tickets', function (Blueprint $table) {
                $table->timestamp('invitation_sent_at')->nullable()->after('issued_at');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('tickets', 'event_id')) {
            Schema::table('tickets', function (Blueprint $table) {
                $table->dropForeign(['event_id']);
                $table->dropColumn('event_id');
            });
        }

        $columnsToDrop = collect(['ticket_source', 'guest_category', 'invitation_sent_at'])
            ->filter(fn (string $column) => Schema::hasColumn('tickets', $column))
            ->values()
            ->all();

        if ($columnsToDrop !== []) {
            Schema::table('tickets', function (Blueprint $table) use ($columnsToDrop) {
                $table->dropColumn($columnsToDrop);
            });
        }
    }
};
