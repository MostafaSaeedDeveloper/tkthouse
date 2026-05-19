<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Build name → event_id map from current event names
        $nameToEventId = DB::table('events')->pluck('id', 'name')->toArray();

        // Index of existing event IDs to validate historical references
        $existingEventIds = array_flip(DB::table('events')->pluck('id')->toArray());

        // Extend map with historical names stored in the activity_log
        // Only include entries whose subject_id still exists in the events table
        DB::table('activity_log')
            ->where('subject_type', 'App\\Models\\Event')
            ->whereNotNull('subject_id')
            ->orderBy('id')
            ->get(['subject_id', 'properties'])
            ->each(function ($log) use (&$nameToEventId, $existingEventIds) {
                $eventId = (int) $log->subject_id;
                if (! isset($existingEventIds[$eventId])) {
                    return;
                }
                $props = json_decode((string) $log->properties, true);
                $oldName = $props['old']['name'] ?? null;
                if ($oldName && ! isset($nameToEventId[$oldName])) {
                    $nameToEventId[$oldName] = $eventId;
                }
            });

        // Backfill order_items
        DB::table('order_items')
            ->whereNull('event_id')
            ->orderBy('id')
            ->chunk(500, function ($items) use ($nameToEventId) {
                foreach ($items as $item) {
                    $prefix = strstr($item->ticket_name, ' - ', true);
                    $eventName = ($prefix !== false) ? $prefix : $item->ticket_name;
                    $eventId = $nameToEventId[$eventName] ?? null;
                    if ($eventId) {
                        DB::table('order_items')
                            ->where('id', $item->id)
                            ->update(['event_id' => $eventId]);
                    }
                }
            });

        // Backfill scan_logs
        DB::table('scan_logs')
            ->whereNull('event_id')
            ->whereNotNull('event_name')
            ->orderBy('id')
            ->chunk(500, function ($logs) use ($nameToEventId) {
                foreach ($logs as $log) {
                    $eventId = $nameToEventId[$log->event_name] ?? null;
                    if ($eventId) {
                        DB::table('scan_logs')
                            ->where('id', $log->id)
                            ->update(['event_id' => $eventId]);
                    }
                }
            });

        // Backfill tickets
        DB::table('tickets')
            ->whereNull('event_id')
            ->orderBy('id')
            ->chunk(500, function ($tickets) use ($nameToEventId) {
                foreach ($tickets as $ticket) {
                    $prefix = strstr($ticket->name, ' - ', true);
                    $eventName = ($prefix !== false) ? $prefix : $ticket->name;
                    $eventId = $nameToEventId[$eventName] ?? null;
                    if ($eventId) {
                        DB::table('tickets')
                            ->where('id', $ticket->id)
                            ->update(['event_id' => $eventId]);
                    }
                }
            });
    }

    public function down(): void
    {
        DB::table('order_items')->update(['event_id' => null]);
        DB::table('scan_logs')->update(['event_id' => null]);
        DB::table('tickets')->update(['event_id' => null]);
    }
};
