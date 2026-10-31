<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Tickets issued from paid orders were created without event_id, so the dashboard (which counts by event_id) saw only a fraction of them
 * while the Tickets page (which matches by name) saw them all. Fill the missing event_id from the order item each ticket was issued for.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('tickets')
            ->whereNull('event_id')
            ->whereNotNull('order_item_id')
            ->orderBy('id')
            ->chunkById(500, function ($tickets) {
                $eventIds = DB::table('order_items')->whereIn('id', $tickets->pluck('order_item_id'))->whereNotNull('event_id')->pluck('event_id', 'id');
                foreach ($tickets as $ticket) {
                    if (isset($eventIds[$ticket->order_item_id])) {
                        DB::table('tickets')->where('id', $ticket->id)->update(['event_id' => $eventIds[$ticket->order_item_id]]);
                    }
                }
            });

        // scan logs written for those tickets
        DB::table('scan_logs')
            ->whereNull('event_id')
            ->whereNotNull('ticket_id')
            ->orderBy('id')
            ->chunkById(500, function ($logs) {
                $eventIds = DB::table('tickets')->whereIn('id', $logs->pluck('ticket_id'))->whereNotNull('event_id')->pluck('event_id', 'id');
                foreach ($logs as $log) {
                    if (isset($eventIds[$log->ticket_id])) {
                        DB::table('scan_logs')->where('id', $log->id)->update(['event_id' => $eventIds[$log->ticket_id]]);
                    }
                }
            });
    }

    public function down(): void
    {
        // data backfill only; nothing to undo
    }
};
