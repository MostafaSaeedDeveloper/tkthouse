<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * READ-ONLY diagnostic for ticket-type data. It never writes, never re-links and never "fixes" anything: it only reports, so people
 * can decide. Old sales are tied to ticket types only by text ("Event - Ticket"), so the findings are evidence, not proof.
 */
class AuditEventTickets extends Command
{
    protected $signature = 'events:audit-tickets {--limit=15 : max sample rows per finding}';

    protected $description = 'Read-only report: ticket-type history, orphaned sales lines and broken ticket relations (changes nothing).';

    public function handle(): int
    {
        $limit = max(1, (int) $this->option('limit'));
        $problems = 0;
        $this->info('Read-only audit — nothing is modified.');

        // 0) how many sales are linked to a ticket type by id
        if (Schema::hasColumn('order_items', 'event_ticket_id')) {
            $total = DB::table('order_items')->count();
            $linked = DB::table('order_items')->whereNotNull('event_ticket_id')->count();
            $this->line("Order items: {$total} total, {$linked} linked to a ticket type by id, ".($total - $linked).' only by text (older sales; never re-linked automatically).');
        }

        // 1) every earlier event save recreated its ticket types
        $updates = DB::table('activity_log')->where('subject_type', \App\Models\Event::class)->where('description', 'Event updated')->selectRaw('subject_id, count(*) c')->groupBy('subject_id')->having('c', '>', 0)->pluck('c', 'subject_id');
        if ($updates->isNotEmpty()) {
            $this->warn('Events saved before the id-preserving fix (each save deleted and recreated ALL their ticket types, giving new ids):');
            $this->table(['event_id', 'saves'], $updates->map(fn ($c, $id) => [$id, $c])->values()->all());
        } else {
            $this->line('No "Event updated" entries in the activity log.');
        }

        // 2) id-gap hint
        $gaps = DB::table('event_tickets')->selectRaw('event_id, count(*) n, min(id) lo, max(id) hi')->groupBy('event_id')->get()->filter(fn ($r) => ($r->hi - $r->lo + 1) > $r->n);
        $this->line('Events whose ticket-type ids are not contiguous (hint of earlier delete/recreate, not proof): '.$gaps->count());
        foreach ($gaps->take($limit) as $r) {
            $this->line("  event {$r->event_id}: {$r->n} types, ids {$r->lo}..{$r->hi}");
        }

        // 3) duplicate names inside an event (name-based legacy matching is ambiguous)
        $dups = DB::table('event_tickets')->selectRaw('event_id, lower(name) n, count(*) c')->groupBy('event_id', DB::raw('lower(name)'))->having('c', '>', 1)->get();
        $this->report('Ticket types sharing a name inside the same event', $dups->map(fn ($r) => "event {$r->event_id}: \"{$r->n}\" x{$r->c}")->all(), $limit, $problems);

        // 4) sales lines whose ticket type can no longer be found
        $events = DB::table('events')->pluck('name', 'id');
        $types = DB::table('event_tickets')->get(['id', 'event_id', 'name'])->groupBy('event_id');
        $orphans = [];
        DB::table('order_items')->whereNotNull('event_id')->orderBy('id')->select('id', 'order_id', 'event_id', 'event_ticket_id', 'ticket_name')->chunk(500, function ($rows) use ($types, $events, &$orphans) {
            foreach ($rows as $item) {
                $set = $types->get($item->event_id, collect());
                if ($item->event_ticket_id) {
                    if (! $set->contains('id', $item->event_ticket_id)) {
                        $orphans[] = "item {$item->id} (order {$item->order_id}): linked ticket type #{$item->event_ticket_id} no longer exists";
                    }
                    continue;
                }
                $prefix = ($events[$item->event_id] ?? '').' - ';
                $name = str_starts_with($item->ticket_name, $prefix) ? substr($item->ticket_name, strlen($prefix)) : $item->ticket_name;
                if (! $set->contains(fn ($t) => $t->name === $name || $t->name === $item->ticket_name)) {
                    $orphans[] = "item {$item->id} (order {$item->order_id}, event {$item->event_id}): \"{$item->ticket_name}\" matches no current ticket type";
                }
            }
        });
        $this->report('Sales lines whose ticket type cannot be found (renamed, deleted or recreated)', $orphans, $limit, $problems);

        // 5) lines without an event, legacy FK to the issued-tickets table, issued records without their line
        $noEvent = DB::table('order_items')->whereNull('event_id')->count();
        $this->line('Order items without an event (event deleted or very old data): '.$noEvent);
        $this->report('order_items.ticket_id pointing to a missing tickets row', DB::table('order_items as oi')->leftJoin('tickets as t', 't.id', '=', 'oi.ticket_id')->whereNotNull('oi.ticket_id')->whereNull('t.id')->pluck('oi.id')->map(fn ($id) => "item {$id}")->all(), $limit, $problems);
        $this->report('Issued tickets whose order item is missing', DB::table('issued_tickets as it')->leftJoin('order_items as oi', 'oi.id', '=', 'it.order_item_id')->whereNull('oi.id')->pluck('it.ticket_number')->map(fn ($n) => "issued {$n}")->all(), $limit, $problems);
        $this->report('Tickets (QR / check-in records) whose order item or order is missing', DB::table('tickets as t')->whereNotNull('t.order_item_id')->leftJoin('order_items as oi', 'oi.id', '=', 't.order_item_id')->whereNull('oi.id')->pluck('t.ticket_number')->map(fn ($n) => "ticket {$n}")->all(), $limit, $problems);
        $this->report('Scan logs pointing to a missing ticket', DB::table('scan_logs as s')->whereNotNull('s.ticket_id')->leftJoin('tickets as t', 't.id', '=', 's.ticket_id')->whereNull('t.id')->pluck('s.id')->map(fn ($id) => "scan log {$id}")->all(), $limit, $problems);

        $this->newLine();
        $this->line($problems === 0 ? 'No broken relations found.' : "{$problems} finding(s) above need a human decision. Nothing was changed.");

        return self::SUCCESS;
    }

    private function report(string $title, array $rows, int $limit, int &$problems): void
    {
        $count = count($rows);
        $problems += $count;
        $this->line("{$title}: {$count}");
        foreach (array_slice($rows, 0, $limit) as $r) {
            $this->line('  '.$r);
        }
        if ($count > $limit) {
            $this->line('  … '.($count - $limit).' more');
        }
    }
}
