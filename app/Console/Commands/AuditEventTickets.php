<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * READ-ONLY diagnostic for ticket-type data. It never writes, never re-links and never "fixes" anything.
 *
 * Confidence vocabulary used in the report:
 *   CONFIRMED  - the order item carries event_ticket_id and that ticket type still exists (an id link, written at checkout).
 *   EXACT TEXT - no id; exactly one ticket type of the SAME event has exactly the name in the item text, prefix = current event name.
 *                Still only text: a ticket renamed or recreated since the sale can produce a wrong "exact" match.
 *   PROBABLE   - no id; the part after " - " matches exactly one ticket type but the event name prefix differs (event renamed).
 *   AMBIGUOUS  - no id; more than one ticket type of the event could be the one (duplicate names).
 *   MISSING    - no id; no ticket type of the event matches (renamed, deleted or recreated since the sale).
 *   UNKNOWN    - no id and no event: nothing to compare against.
 * Only CONFIRMED is a link. Nothing else is ever treated as one.
 */
class AuditEventTickets extends Command
{
    protected $signature = 'events:audit-tickets {--limit=15 : max sample rows per finding} {--event= : only this event id}';

    protected $description = 'Read-only report: id-linked vs text-only sales, exact/ambiguous/missing matches, duplicate names, broken relations, events hit by the old delete-and-recreate (changes nothing).';

    public function handle(): int
    {
        $limit = max(1, (int) $this->option('limit'));
        $only = $this->option('event') ? (int) $this->option('event') : null;
        $this->info('Read-only audit — nothing is modified, nothing is re-linked.');

        $events = DB::table('events')->when($only, fn ($q) => $q->where('id', $only))->pluck('name', 'id');
        $types = DB::table('event_tickets')->get(['id', 'event_id', 'name'])->groupBy('event_id');
        $hasLink = Schema::hasColumn('order_items', 'event_ticket_id');

        $bucket = ['CONFIRMED' => 0, 'EXACT TEXT' => 0, 'PROBABLE' => 0, 'AMBIGUOUS' => 0, 'MISSING' => 0, 'UNKNOWN' => 0, 'LINKED-TYPE-GONE' => 0];
        $samples = array_fill_keys(array_keys($bucket), []);
        $affected = [];   // event id => legacy items that are not CONFIRMED/EXACT
        $total = 0;

        DB::table('order_items')->when($only, fn ($q) => $q->where('event_id', $only))->orderBy('id')
            ->select(array_filter(['id', 'order_id', 'event_id', $hasLink ? 'event_ticket_id' : null, 'ticket_name']))
            ->chunk(500, function ($rows) use (&$bucket, &$samples, &$affected, &$total, $types, $events, $hasLink) {
                foreach ($rows as $item) {
                    $total++;
                    $class = $this->classify($item, $types, $events, $hasLink);
                    $bucket[$class]++;
                    $samples[$class][] = "item {$item->id} (order {$item->order_id}, event ".($item->event_id ?? '—')."): \"{$item->ticket_name}\"".($hasLink && $item->event_ticket_id ? " -> ticket type #{$item->event_ticket_id}" : '');
                    if (! in_array($class, ['CONFIRMED', 'EXACT TEXT'], true) && $item->event_id) {
                        $affected[$item->event_id] = ($affected[$item->event_id] ?? 0) + 1;
                    }
                }
            });

        $this->newLine();
        $this->line("Order items examined: {$total}");
        $this->table(['Class', 'Items', 'Meaning'], [
            ['CONFIRMED', $bucket['CONFIRMED'], 'linked by id to an existing ticket type — the only real link'],
            ['LINKED-TYPE-GONE', $bucket['LINKED-TYPE-GONE'], 'carries an id whose ticket type no longer exists (broken relation)'],
            ['EXACT TEXT', $bucket['EXACT TEXT'], 'no id; exact name of exactly one ticket type of the event (text only, NOT a link)'],
            ['PROBABLE', $bucket['PROBABLE'], 'no id; ticket part matches one type but the event name differs (event renamed)'],
            ['AMBIGUOUS', $bucket['AMBIGUOUS'], 'no id; several ticket types of the event could match (duplicate names)'],
            ['MISSING', $bucket['MISSING'], 'no id; no ticket type matches (renamed / deleted / recreated since the sale)'],
            ['UNKNOWN', $bucket['UNKNOWN'], 'no id and no event; cannot be compared'],
        ]);
        $this->line('Legacy items without an id: '.($total - $bucket['CONFIRMED'] - $bucket['LINKED-TYPE-GONE']).'. None of them was, or will be, re-linked automatically.');
        foreach (['LINKED-TYPE-GONE', 'AMBIGUOUS', 'MISSING', 'PROBABLE', 'UNKNOWN'] as $c) {
            $this->sample($c, $samples[$c], $limit);
        }

        // duplicate names inside an event
        $dups = DB::table('event_tickets')->when($only, fn ($q) => $q->where('event_id', $only))->selectRaw('event_id, lower(name) n, count(*) c')->groupBy('event_id', DB::raw('lower(name)'))->having('c', '>', 1)->get();
        $this->sample('Duplicate ticket-type names inside one event', $dups->map(fn ($r) => "event {$r->event_id}: \"{$r->n}\" x{$r->c}")->all(), $limit);

        // events hit by the old delete-and-recreate behaviour
        $saves = DB::table('activity_log')->where('subject_type', \App\Models\Event::class)->where('description', 'Event updated')
            // saves made by the id-preserving code log a "tickets" result in their properties; older saves do not
            ->selectRaw("subject_id, sum(case when properties like '%\"tickets\"%' then 0 else 1 end) c")->groupBy('subject_id')->get()->filter(fn ($r) => $r->c > 0)->keyBy('subject_id');
        $gaps = DB::table('event_tickets')->selectRaw('event_id, count(*) n, min(id) lo, max(id) hi')->groupBy('event_id')->get()->filter(fn ($r) => ($r->hi - $r->lo + 1) > $r->n)->keyBy('event_id');
        $hit = collect($saves->keys())->merge($gaps->keys())->unique()->when($only, fn ($c) => $c->filter(fn ($id) => $id === $only))->sort()->values();
        $this->newLine();
        $this->line('Events possibly affected by the old delete-and-recreate (every save before the id-preserving fix replaced ALL ticket types):');
        if ($hit->isEmpty()) {
            $this->line('  none found (no "Event updated" entries, no id gaps).');
        } else {
            $this->table(['event_id', 'name', 'old-style saves logged', 'id gap hint', 'legacy items not CONFIRMED/EXACT'], $hit->map(fn ($id) => [
                $id, $events[$id] ?? '(deleted)', $saves[$id]->c ?? 0, isset($gaps[$id]) ? "{$gaps[$id]->n} types, ids {$gaps[$id]->lo}..{$gaps[$id]->hi}" : '—', $affected[$id] ?? 0,
            ])->all());
            $this->line('  "old-style saves" (logged before the id-preserving fix) are evidence that ticket ids were replaced; the id gap is only a hint. The activity log may be incomplete.');
        }

        // broken relations
        $this->newLine();
        $broken = 0;
        $broken += $this->sample('order_items.ticket_id pointing to a missing tickets row', DB::table('order_items as oi')->leftJoin('tickets as t', 't.id', '=', 'oi.ticket_id')->whereNotNull('oi.ticket_id')->whereNull('t.id')->pluck('oi.id')->map(fn ($id) => "item {$id}")->all(), $limit);
        $broken += $this->sample('Issued tickets whose order item is missing', DB::table('issued_tickets as it')->leftJoin('order_items as oi', 'oi.id', '=', 'it.order_item_id')->whereNull('oi.id')->pluck('it.ticket_number')->map(fn ($n) => "issued {$n}")->all(), $limit);
        $broken += $this->sample('QR / check-in tickets whose order item is missing (guest-list tickets have none by design and are excluded)', DB::table('tickets as t')->whereNotNull('t.order_item_id')->leftJoin('order_items as oi', 'oi.id', '=', 't.order_item_id')->whereNull('oi.id')->pluck('t.ticket_number')->map(fn ($n) => "ticket {$n}")->all(), $limit);
        $broken += $this->sample('Scan logs pointing to a missing ticket', DB::table('scan_logs as s')->whereNotNull('s.ticket_id')->leftJoin('tickets as t', 't.id', '=', 's.ticket_id')->whereNull('t.id')->pluck('s.id')->map(fn ($id) => "scan log {$id}")->all(), $limit);
        $broken += $this->sample('Payment attempts whose order is missing', DB::table('payment_attempts as p')->leftJoin('orders as o', 'o.id', '=', 'p.order_id')->whereNull('o.id')->pluck('p.id')->map(fn ($id) => "attempt {$id}")->all(), $limit);
        $this->line('Order items without an event: '.DB::table('order_items')->whereNull('event_id')->count().' (event deleted before the guard existed, or very old data).');

        $this->newLine();
        $this->line('Summary: '.($broken + $bucket['LINKED-TYPE-GONE']).' broken relation(s); '.($bucket['AMBIGUOUS'] + $bucket['MISSING'] + $bucket['PROBABLE'] + $bucket['UNKNOWN']).' legacy item(s) that need a human decision. Nothing was changed.');

        return self::SUCCESS;
    }

    private function classify(object $item, $types, $events, bool $hasLink): string
    {
        $set = $item->event_id ? $types->get($item->event_id, collect()) : collect();
        if ($hasLink && $item->event_ticket_id) {
            return DB::table('event_tickets')->where('id', $item->event_ticket_id)->exists() ? 'CONFIRMED' : 'LINKED-TYPE-GONE';
        }
        if (! $item->event_id) {
            return 'UNKNOWN';
        }
        $prefix = ($events[$item->event_id] ?? '').' - ';
        $text = (string) $item->ticket_name;
        $exact = str_starts_with($text, $prefix) ? substr($text, strlen($prefix)) : null;
        if ($exact !== null) {
            $n = $set->filter(fn ($t) => $t->name === $exact)->count();
            if ($n === 1) {
                return 'EXACT TEXT';
            }
            if ($n > 1) {
                return 'AMBIGUOUS';
            }
        }
        // event renamed since the sale: compare only the ticket part after the first separator
        $tail = str_contains($text, ' - ') ? substr($text, strpos($text, ' - ') + 3) : $text;
        $m = $set->filter(fn ($t) => $t->name === $tail)->count();
        if ($m === 1) {
            return 'PROBABLE';
        }

        return $m > 1 ? 'AMBIGUOUS' : 'MISSING';
    }

    /** @return int number of rows reported */
    private function sample(string $title, array $rows, int $limit): int
    {
        if ($rows === []) {
            return 0;
        }
        $this->line("{$title}: ".count($rows));
        foreach (array_slice($rows, 0, $limit) as $r) {
            $this->line('  '.$r);
        }
        if (count($rows) > $limit) {
            $this->line('  … '.(count($rows) - $limit).' more');
        }

        return count($rows);
    }
}
