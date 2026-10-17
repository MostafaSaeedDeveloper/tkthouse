<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ticket-type integrity. Additive and nullable/defaulted only; nothing is rewritten.
 *  - order_items.event_ticket_id: which ticket type a NEW sale was made from. Until now sales were tied to ticket types only by the
 *    text snapshot "Event - Ticket", so a rename or a delete+recreate silently detached history. Old rows stay NULL (they are never
 *    re-linked by guesswork; the read-only `events:audit-tickets` report lists them). Deliberately NO foreign key: a cascade or
 *    set-null rule here could only ever lose information; deletion is guarded in the application (EventTicketSync).
 *  - event_tickets.sort_order: explicit display order (ties keep the old behaviour: by id).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('order_items', 'event_ticket_id')) {
            Schema::table('order_items', function (Blueprint $table) {
                $table->unsignedBigInteger('event_ticket_id')->nullable()->after('event_id');
                $table->index('event_ticket_id');
            });
        }
        if (! Schema::hasColumn('event_tickets', 'sort_order')) {
            Schema::table('event_tickets', function (Blueprint $table) {
                $table->unsignedInteger('sort_order')->default(0)->after('is_couple');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('order_items', 'event_ticket_id')) {
            Schema::table('order_items', function (Blueprint $table) {
                $table->dropIndex(['event_ticket_id']);
                $table->dropColumn('event_ticket_id');
            });
        }
        if (Schema::hasColumn('event_tickets', 'sort_order')) {
            Schema::table('event_tickets', function (Blueprint $table) {
                $table->dropColumn('sort_order');
            });
        }
    }
};
