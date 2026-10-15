<?php

namespace App\Services\Checkout;

use App\Models\Event;
use App\Models\EventTicket;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * Turns a ticket cart into a checkout selection and is the single place that enforces what may be
 * booked: bookable event, active tickets of that event, couple tickets in pairs, per-ticket maximums.
 * A selection is a list of per-person "units" (one per ticket), never a client-supplied price.
 */
class CheckoutSelection
{
    /** Same rule the event page applies: an active event that has not started yet. */
    public function isEventBookable(Event $event): bool
    {
        return $event->status === 'active'
            && $event->event_date !== null
            && ! $event->event_date->lt(now()->startOfDay());
    }

    public function maxAllowedQuantity(EventTicket $ticket): int
    {
        return (bool) ($ticket->is_couple ?? false) ? 2 : max(1, (int) ($ticket->max_per_order ?? 10));
    }

    /** @return array{event_id: int, event_name: string, requires_approval: bool, units: array<int, array<string, mixed>>} */
    public function fromEncodedCart(Event $event, string $encodedCart): array
    {
        $decoded = base64_decode(strtr($encodedCart, '-_', '+/'), true);
        if ($decoded === false) {
            throw ValidationException::withMessages(['cart' => 'Invalid cart data.']);
        }

        $payload = json_decode($decoded, true);
        if (! is_array($payload)) {
            throw ValidationException::withMessages(['cart' => 'Invalid cart format.']);
        }

        // Merge repeated ticket rows so per-order limits cannot be bypassed by splitting a ticket across rows.
        $requested = collect($payload)
            ->filter(fn ($row) => is_array($row))
            ->map(fn ($row) => ['ticket_id' => (int) ($row['ticket_id'] ?? 0), 'qty' => max(1, (int) ($row['qty'] ?? 1))])
            ->filter(fn ($row) => $row['ticket_id'] > 0)
            ->groupBy('ticket_id')
            ->map(fn ($rows, $ticketId) => ['ticket_id' => (int) $ticketId, 'qty' => (int) $rows->sum('qty')])
            ->values();

        return $this->build($event, $requested);
    }

    /** @param Collection<int, array{ticket_id: int, qty: int}> $requested */
    public function build(Event $event, Collection $requested): array
    {
        if (! $this->isEventBookable($event)) {
            throw ValidationException::withMessages(['cart' => 'Booking is closed for this event.']);
        }

        if ($requested->isEmpty()) {
            throw ValidationException::withMessages(['cart' => 'No tickets selected.']);
        }

        $tickets = EventTicket::query()
            ->where('event_id', $event->id)
            ->where('status', 'active')
            ->whereIn('id', $requested->pluck('ticket_id'))
            ->get()
            ->keyBy('id');

        $units = [];
        foreach ($requested as $row) {
            $ticket = $tickets->get($row['ticket_id']);
            if (! $ticket) {
                throw ValidationException::withMessages(['cart' => 'One of the selected tickets is no longer available.']);
            }

            if ((bool) ($ticket->is_couple ?? false) && (int) $row['qty'] !== 2) {
                throw ValidationException::withMessages(['cart' => "{$ticket->name} is couples only and must be booked as 2 tickets."]);
            }

            $max = $this->maxAllowedQuantity($ticket);
            if ((int) $row['qty'] > $max) {
                throw ValidationException::withMessages(['cart' => "Maximum allowed quantity for {$ticket->name} is {$max}."]);
            }

            for ($i = 0; $i < $row['qty']; $i++) {
                $units[] = [
                    'ticket_id' => $ticket->id,
                    'ticket_name' => $ticket->name,
                    'ticket_price' => (float) $ticket->price,
                    'is_couple' => (bool) $ticket->is_couple,
                    'event_name' => $event->name,
                ];
            }
        }

        return [
            'event_id' => $event->id,
            'event_name' => $event->name,
            'requires_approval' => (bool) $event->requires_booking_approval,
            'units' => $units,
        ];
    }

    /**
     * Re-validates a stored selection against current data and returns it rebuilt with CURRENT prices.
     *
     * @param Collection<int, array<string, mixed>> $units
     */
    public function revalidate(Event $event, Collection $units): array
    {
        $requested = $units
            ->groupBy(fn ($unit) => (int) ($unit['ticket_id'] ?? 0))
            ->map(fn ($rows, $ticketId) => ['ticket_id' => (int) $ticketId, 'qty' => $rows->count()])
            ->filter(fn ($row) => $row['ticket_id'] > 0)
            ->values();

        return $this->build($event, $requested);
    }
}
