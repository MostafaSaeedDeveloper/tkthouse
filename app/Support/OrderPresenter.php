<?php

namespace App\Support;

use App\Models\Order;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Display-only helpers for the admin order screens. Nothing here writes data or decides access;
 * ticket types are dynamic, so ticket names are never parsed to guess a "type" or category.
 */
final class OrderPresenter
{
    /** Customer-facing reference: digits of the order number (falls back to the raw value). */
    public static function number(Order $order): string
    {
        return preg_replace('/\D+/', '', (string) $order->order_number) ?: (string) $order->order_number;
    }

    /**
     * Distinct event names on the order. Uses the stored event relation; orders created before
     * `order_items.event_id` existed fall back to the "Event - Ticket" prefix of the stored ticket name.
     */
    public static function eventNames(Order $order): Collection
    {
        return $order->items
            ->map(function ($item) {
                if ($item->relationLoaded('event') && $item->event) {
                    return trim((string) $item->event->name);
                }

                $name = (string) $item->ticket_name;

                return str_contains($name, ' - ') ? trim((string) Str::before($name, ' - ')) : null;
            })
            ->filter()
            ->unique()
            ->values();
    }

    /** Total number of tickets (sum of item quantities). */
    public static function ticketCount(Order $order): int
    {
        return (int) $order->items->sum(fn ($item) => max(1, (int) $item->quantity));
    }

    /**
     * Ticket types on the order with their total quantity, in order of first appearance.
     *
     * @return \Illuminate\Support\Collection<int, array{name: string, qty: int}>
     */
    public static function ticketTypes(Order $order): \Illuminate\Support\Collection
    {
        return $order->items
            // ticket_name is stored as "<event name> - <ticket>"; the list already shows the event, so keep just the ticket.
            ->groupBy(function ($item) {
                $name = trim((string) $item->ticket_name);
                $prefix = trim((string) $item->event?->name).' - ';
                if ($prefix !== ' - ' && str_starts_with($name, $prefix)) {
                    $name = trim(substr($name, strlen($prefix)));
                }

                return $name;
            })
            ->reject(fn ($group, $name) => $name === '')
            ->map(fn ($group, $name) => ['name' => (string) $name, 'qty' => (int) $group->sum(fn ($item) => max(1, (int) $item->quantity))])
            ->values();
    }

    /** Number of different ticket names on the order. */
    public static function ticketTypeCount(Order $order): int
    {
        return $order->items->pluck('ticket_name')->map(fn ($name) => trim((string) $name))->filter()->unique()->count();
    }
}
