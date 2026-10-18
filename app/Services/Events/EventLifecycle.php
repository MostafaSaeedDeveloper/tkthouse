<?php

namespace App\Services\Events;

use App\Models\Event;
use App\Models\EventTicket;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Archive / restore / permanent deletion of events.
 *
 *  - Archiving only flips the status to "archived" (plus who/when/previous status). Event id, ticket-type ids, orders, payments,
 *    refunds, issued tickets, QR codes, check-ins and scan logs are not touched, and archiving never invalidates an issued ticket:
 *    the scanner and the customer's ticket pages do not look at the event status.
 *  - Permanent deletion is refused when ANY historical record points at the event, directly or indirectly (see dependencies()).
 *    The check runs inside the same transaction, with the event and its ticket types locked, that does the delete.
 *    Order placement takes the same lock first, so a delete and a checkout can never both succeed on the same event.
 */
final class EventLifecycle
{
    /**
     * Every kind of record that makes an event non-deletable. References are searched in all the ways this schema (and its history)
     * ties data to an event: event_id, ticket-type id, order item, and — for rows older than those columns — the "Event - Ticket" text.
     *
     * @return array{total:int, items:array<string,int>, messages:list<string>}
     */
    public function dependencies(Event $event): array
    {
        $typeIds = EventTicket::query()->where('event_id', $event->id)->pluck('id')->all();
        $like = fn (string $column) => fn ($q) => $q->whereRaw("{$column} LIKE ? ESCAPE '!'", [$this->escapeLike($event->name).' - %']);

        $itemIds = DB::table('order_items')->where(function ($q) use ($event, $typeIds, $like) {
            $q->where('event_id', $event->id);
            if ($typeIds) {
                $q->orWhereIn('event_ticket_id', $typeIds);
            }
            // very old items without an event id that only carry the text "Event - Ticket"
            $q->orWhere(fn ($w) => $w->whereNull('event_id')->where($like('ticket_name')));
        })->pluck('id', 'id');
        $orderIds = $itemIds->isEmpty() ? collect() : DB::table('order_items')->whereIn('id', $itemIds->keys())->pluck('order_id')->unique();

        $count = fn ($query) => (int) $query->count();
        $items = [
            'order items (sold tickets)' => $itemIds->count(),
            'orders' => $orderIds->count(),
            'payment attempts' => $orderIds->isEmpty() ? 0 : $count(DB::table('payment_attempts')->whereIn('order_id', $orderIds)),
            'refunds' => $orderIds->isEmpty() ? 0 : $count(DB::table('payment_refunds')->whereIn('order_id', $orderIds)),
            'issued tickets' => $itemIds->isEmpty() ? 0 : $count(DB::table('issued_tickets')->whereIn('order_item_id', $itemIds->keys())),
            // QR / check-in records, including guest-list attendees that have no order at all
            'tickets with QR / check-in (incl. guest list)' => $count(DB::table('tickets')->where(function ($q) use ($event, $itemIds, $like) {
                $q->where('event_id', $event->id);
                if ($itemIds->isNotEmpty()) {
                    $q->orWhereIn('order_item_id', $itemIds->keys());
                }
                $q->orWhere(fn ($w) => $w->whereNull('event_id')->where($like('name')));
            })),
            'scanner log entries' => $count(DB::table('scan_logs')->where(function ($q) use ($event) {
                $q->where('event_id', $event->id)->orWhere(fn ($w) => $w->whereNull('event_id')->where('event_name', $event->name));
            })),
        ];
        $items = array_filter($items);

        return [
            'total' => array_sum($items),
            'items' => $items,
            'messages' => array_map(fn ($n, $k) => "{$n} {$k}", $items, array_keys($items)),
        ];
    }

    public function archive(Event $event, ?User $by = null): Event
    {
        return DB::transaction(function () use ($event, $by) {
            $locked = Event::query()->whereKey($event->id)->lockForUpdate()->firstOrFail();
            if ($locked->isArchived()) {
                return $locked; // idempotent: a second click or request changes nothing
            }
            EventTicket::query()->where('event_id', $locked->id)->lockForUpdate()->get(['id']); // same lock order as checkout and ticket sync
            $locked->forceFill(['status_before_archive' => $locked->status, 'status' => Event::ARCHIVED, 'archived_at' => now(), 'archived_by' => $by?->id])->save();
            activity('events')->performedOn($locked)->causedBy($by)->withProperties(['previous_status' => $locked->status_before_archive])->log('Event archived');

            return $locked;
        });
    }

    /**
     * Brings an archived event back WITHOUT blindly re-publishing it: it returns to its previous status only if it is still ready to
     * sell; otherwise it comes back as a draft (not public) and the reasons are returned.
     *
     * @return array{event: Event, restored_to: string, issues: list<string>}
     */
    public function restore(Event $event, ?User $by = null): array
    {
        return DB::transaction(function () use ($event, $by) {
            $locked = Event::query()->whereKey($event->id)->lockForUpdate()->firstOrFail();
            if (! $locked->isArchived()) {
                return ['event' => $locked, 'restored_to' => $locked->status, 'issues' => []];
            }
            $previous = $locked->status_before_archive ?: 'draft';
            $issues = $this->readinessIssues($locked);
            $wasPublic = in_array($previous, ['active', 'sold_out'], true);
            $target = ($wasPublic && $issues === []) ? $previous : ($wasPublic ? 'draft' : $previous);

            $locked->forceFill(['status' => $target, 'archived_at' => null, 'archived_by' => null, 'status_before_archive' => null])->save();
            activity('events')->performedOn($locked)->causedBy($by)->withProperties(['restored_to' => $target, 'issues' => $issues])->log('Event restored');

            return ['event' => $locked, 'restored_to' => $target, 'issues' => $wasPublic ? $issues : []];
        });
    }

    /** What must be true before an event may be public again. Empty = ready. @return list<string> */
    public function readinessIssues(Event $event): array
    {
        $issues = [];
        foreach (['name' => 'a name', 'location' => 'a location', 'event_time' => 'a start time'] as $field => $label) {
            if (blank($event->{$field})) {
                $issues[] = "The event has no {$label}.";
            }
        }
        if ($event->event_date === null) {
            $issues[] = 'The event has no date.';
        } elseif ($event->event_date->lt(now()->startOfDay()) && ($event->end_date === null || $event->end_date->lt(now()->startOfDay()))) {
            $issues[] = 'The event date has already passed.';
        }
        $sellable = EventTicket::query()->where('event_id', $event->id)->where('status', 'active')->where('price', '>=', 0)->count();
        if ($sellable === 0) {
            $issues[] = 'The event has no active ticket type to sell.';
        }

        return $issues;
    }

    /** @throws ValidationException when the event still has history; nothing is deleted in that case. */
    public function delete(Event $event, ?User $by = null): void
    {
        DB::transaction(function () use ($event, $by) {
            $locked = Event::query()->whereKey($event->id)->lockForUpdate()->first();
            if (! $locked) {
                return; // already gone
            }
            EventTicket::query()->where('event_id', $locked->id)->lockForUpdate()->get(['id']);

            $deps = $this->dependencies($locked);
            if ($deps['total'] > 0) {
                throw ValidationException::withMessages(['event' => 'This event cannot be deleted permanently because it has history: '.implode(', ', $deps['messages']).'. Archive it instead — nothing is lost and it disappears from sale.']);
            }

            $name = $locked->name;
            // only configuration rows (ticket types without any sale, fees, images, lineup) go with it
            $locked->delete();
            activity('events')->causedBy($by)->log('Event deleted: '.$name);
        });
    }

    private function escapeLike(string $value): string
    {
        return str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $value);
    }
}
