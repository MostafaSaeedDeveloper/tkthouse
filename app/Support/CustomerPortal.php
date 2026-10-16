<?php

namespace App\Support;

use App\Models\Event;
use App\Models\IssuedTicket;
use App\Models\Order;

/** Labels and badge colours for the customer portal. It maps the statuses that already exist; it invents none. */
class CustomerPortal
{
    /** @return array{label: string, variant: string} */
    public static function orderBadge(string $status): array
    {
        return match ($status) {
            'paid' => ['Paid', 'success'],
            'pending_payment' => ['Awaiting payment', 'warning'],
            'pending_approval' => ['Awaiting approval', 'warning'],
            'on_hold' => ['On hold', 'warning'],
            'canceled', 'cancelled' => ['Canceled', 'danger'],
            'rejected' => ['Rejected', 'danger'],
            'expired' => ['Expired', 'danger'],
            'failed' => ['Failed', 'danger'],
            'refunded' => ['Refunded', 'default'],
            'partially_refunded' => ['Partially refunded', 'default'],
            default => [ucfirst(str_replace('_', ' ', $status)), 'default'],
        };
    }

    /** What the customer sees as "payment status", derived from the order and its real payment attempts. */
    public static function paymentBadge(Order $order): array
    {
        if ($order->status === 'paid') {
            return ['Paid', 'success'];
        }
        if (in_array($order->status, ['refunded', 'partially_refunded'], true)) {
            return self::orderBadge($order->status);
        }
        if (in_array($order->status, ['pending_approval', 'rejected'], true)) {
            return ['Not charged', 'default'];
        }
        if (in_array($order->status, ['canceled', 'cancelled', 'expired'], true)) {
            return ['Not paid', 'default'];
        }

        $attempts = $order->relationLoaded('paymentAttempts') ? $order->paymentAttempts : $order->paymentAttempts()->get();
        $latest = $attempts->sortByDesc('id')->first();

        return match (true) {
            $latest === null => ['Not paid yet', 'warning'],
            $attempts->contains('status', 'initiated') => ['Processing', 'warning'],
            $latest->status === 'failed' => ['Payment failed', 'danger'],
            default => ['Not paid yet', 'warning'],
        };
    }

    public static function paymentFailed(Order $order): bool
    {
        return self::paymentBadge($order)[0] === 'Payment failed';
    }

    public static function eventOf(Order $order): ?Event
    {
        return $order->items->pluck('event')->filter()->first();
    }

    public static function eventEnded(?Event $event): bool
    {
        if (! $event || ! $event->event_date) {
            return false;
        }

        return ($event->end_date ?? $event->event_date)->lt(now()->startOfDay());
    }

    /** Ticket badge from the real check-in status plus whether the event is over. */
    public static function ticketBadge(IssuedTicket $ticket, ?Event $event): array
    {
        $status = (string) ($ticket->dashboardTicket?->status ?? 'not_checked_in');

        return match (true) {
            $status === 'canceled' => ['Canceled', 'danger'],
            $status === 'checked_in' => ['Checked in', 'default'],
            self::eventEnded($event) => ['Event ended', 'default'],
            default => ['Valid', 'success'],
        };
    }

    /** The QR is shown only while the ticket can still be scanned. */
    public static function qrVisible(IssuedTicket $ticket, ?Event $event): bool
    {
        return self::ticketBadge($ticket, $event)[0] === 'Valid';
    }
}
