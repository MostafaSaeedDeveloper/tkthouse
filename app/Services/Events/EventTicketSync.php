<?php

namespace App\Services\Events;

use App\Models\Event;
use App\Models\EventTicket;
use App\Models\OrderItem;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Applies the ticket-type part of an event form to the database WITHOUT ever recreating a ticket type that already exists.
 *
 *  - a row with an id (that belongs to THIS event) updates that very record: id, history and relations stay;
 *  - a row without an id creates a new ticket type;
 *  - a ticket type is removed ONLY when its id is explicitly listed in $removedIds. Absence from the request proves nothing
 *    (partial request, failed page load), so absent tickets are left alone;
 *  - a ticket type that has sales evidence is never deleted: it is hidden instead (history and issued tickets stay intact);
 *  - everything runs in one transaction with the event's ticket rows locked, so a concurrent checkout either commits first
 *    (the sale is then seen and the ticket is hidden, not deleted) or waits for this transaction.
 *
 * There is no per-ticket stock/capacity column in this system; max_per_order only limits a single cart and never affects history.
 */
final class EventTicketSync
{
    public const HIDDEN = 'hidden';

    /**
     * Sales evidence for a ticket type, counted over ALL order items including those of soft-deleted orders.
     * linked = items stamped with event_ticket_id; legacy = older items matched by the exact "Event - Ticket" / "Ticket" text.
     *
     * @return array{linked:int, legacy:int, total:int}
     */
    public function usage(Event $event, EventTicket $ticket): array
    {
        $linked = OrderItem::query()->where('event_ticket_id', $ticket->id)->count();
        $legacy = $this->legacyItems($event, $ticket)->count();

        return ['linked' => $linked, 'legacy' => $legacy, 'total' => $linked + $legacy];
    }

    /** Order items of this event created before event_ticket_id existed whose text exactly names this ticket type. */
    private function legacyItems(Event $event, EventTicket $ticket, ?string $name = null)
    {
        $name ??= $ticket->name;

        return OrderItem::query()->whereNull('event_ticket_id')->where('event_id', $event->id)
            ->whereIn('ticket_name', array_unique([$event->name.' - '.$name, $name]));
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows       ticket rows in display order (id optional)
     * @param  array<int, int|string>  $removedIds   ids the user explicitly removed
     * @return array{created: list<string>, updated: list<string>, deleted: list<string>, hidden: list<string>}
     */
    public function sync(Event $event, array $rows, array $removedIds = []): array
    {
        return DB::transaction(function () use ($event, $rows, $removedIds) {
            /** @var Collection<int, EventTicket> $existing */
            $existing = EventTicket::query()->where('event_id', $event->id)->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            $out = ['created' => [], 'updated' => [], 'deleted' => [], 'hidden' => []];

            $rows = $this->inDisplayOrder($rows);
            $submittedIds = [];
            foreach ($rows as $i => $row) {
                $id = $this->id($row['id'] ?? null);
                if ($id === null) {
                    continue;
                }
                if (! $existing->has($id)) {
                    $this->fail("tickets.$i.id", 'One of the ticket types does not belong to this event. Reload the page and try again.');
                }
                if (isset($submittedIds[$id])) {
                    $this->fail("tickets.$i.id", 'The same ticket type was submitted twice.');
                }
                $submittedIds[$id] = $i;
            }

            $removed = [];
            foreach ($removedIds as $raw) {
                $id = $this->id($raw);
                if ($id === null || ! $existing->has($id)) {
                    $this->fail('removed_ticket_ids', 'A ticket type marked for removal does not belong to this event.');
                }
                if (isset($submittedIds[$id])) {
                    $this->fail('removed_ticket_ids', 'A ticket type cannot be both edited and removed.');
                }
                $removed[$id] = true;
            }

            $this->assertUniqueNames($rows, $existing, $removed);

            // 1) update existing / create new, in the submitted order
            foreach ($rows as $position => $row) {
                $attributes = $this->attributes($row) + ['sort_order' => $position];
                $id = $this->id($row['id'] ?? null);

                if ($id === null) {
                    $event->tickets()->create($attributes);
                    $out['created'][] = $attributes['name'];

                    continue;
                }

                $ticket = $existing->get($id);
                if ($ticket->name !== $attributes['name']) {
                    // before the text that ties old sales to this ticket changes, stamp the id on the ones that match it exactly today
                    $this->legacyItems($event, $ticket)->update(['event_ticket_id' => $ticket->id]);
                }
                $ticket->fill($attributes);
                if ($ticket->isDirty()) {
                    $ticket->save();
                    $out['updated'][] = $attributes['name'];
                }
            }

            // 2) explicit removals only
            foreach (array_keys($removed) as $id) {
                $ticket = $existing->get($id);
                if ($this->usage($event, $ticket)['total'] > 0) {
                    if ($ticket->status !== self::HIDDEN) {
                        $this->legacyItems($event, $ticket)->update(['event_ticket_id' => $ticket->id]);
                        $ticket->forceFill(['status' => self::HIDDEN])->save();
                    }
                    $out['hidden'][] = $ticket->name;
                } else {
                    $ticket->delete();
                    $out['deleted'][] = $ticket->name;
                }
            }

            return $out;
        });
    }

    /** Rows ordered by their explicit "position" (set by the form's up/down buttons); without positions the submitted order is kept. */
    private function inDisplayOrder(array $rows): array
    {
        $rows = array_values($rows);
        $keyed = array_map(fn ($row, $i) => [is_numeric($row['position'] ?? null) ? (int) $row['position'] : $i, $i, $row], $rows, array_keys($rows));
        usort($keyed, fn ($a, $b) => [$a[0], $a[1]] <=> [$b[0], $b[1]]);

        return array_map(fn ($k) => $k[2], $keyed);
    }

    /** @return array<string, mixed> */
    private function attributes(array $row): array
    {
        $isCouple = (bool) ($row['is_couple'] ?? false);

        return [
            'name' => trim((string) $row['name']),
            'price' => $row['price'],
            'status' => $row['status'],
            'color' => $row['color'] ?? '#0d6efd',
            'label' => $row['label'] ?? null,
            'description' => $row['description'] ?? null,
            'is_couple' => $isCouple,
            'max_per_order' => $isCouple ? 2 : max(1, (int) ($row['max_per_order'] ?? 10)),
        ];
    }

    /**
     * Names identify a ticket type in legacy reports and order text, so a new or renamed ticket may not collide with another one.
     * Two untouched legacy tickets that already share a name are left alone (not this edit's problem).
     */
    private function assertUniqueNames(array $rows, Collection $existing, array $removed): void
    {
        $entries = [];
        foreach ($rows as $row) {
            $id = $this->id($row['id'] ?? null);
            $name = mb_strtolower(trim((string) $row['name']));
            $changed = $id === null || mb_strtolower($existing->get($id)->name) !== $name;
            $entries[] = [$name, $changed];
        }
        $submitted = array_filter(array_map(fn ($r) => $this->id($r['id'] ?? null), $rows));
        foreach ($existing as $id => $ticket) {
            if (! isset($removed[$id]) && ! in_array($id, $submitted, true)) {
                $entries[] = [mb_strtolower($ticket->name), false];
            }
        }
        $seen = [];
        foreach ($entries as [$name, $changed]) {
            if (isset($seen[$name]) && ($changed || $seen[$name])) {
                $this->fail('tickets', 'Two ticket types of this event cannot have the same name ("'.$name.'").');
            }
            $seen[$name] = $changed ?: ($seen[$name] ?? false);
        }
    }

    private function id(mixed $value): ?int
    {
        return is_numeric($value) && (int) $value > 0 ? (int) $value : null;
    }

    private function fail(string $field, string $message): never
    {
        throw ValidationException::withMessages([$field => $message]);
    }
}
