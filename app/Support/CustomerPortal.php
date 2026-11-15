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
        // Only an admin marking the event as Ended ends it; the date never does.
        return $event?->hasEnded() ?? false;
    }

    /**
     * The single place that decides what a customer sees in place of a ticket's QR code.
     *
     *   canceled  — canceled or refunded (never a usable QR)       used — already scanned at the gate
     *   locked    — valid, but the event's QR is not revealed yet   visible — show the code
     *
     * @return array{state: string, refunded: bool, reveals_at: ?\Illuminate\Support\Carbon}
     */
    public static function qrState(IssuedTicket $ticket, ?Event $event): array
    {
        $dash = $ticket->dashboardTicket;
        $orderStatus = (string) ($ticket->order?->status ?? 'paid');
        $refunded = ($dash?->isRefundCanceled() ?? false) || in_array($orderStatus, ['refunded'], true);
        $dead = $refunded || ($dash?->status === 'canceled') || in_array($orderStatus, ['canceled', 'cancelled'], true);

        return match (true) {
            $dead => ['state' => 'canceled', 'refunded' => $refunded, 'reveals_at' => null],
            ($dash?->status) === 'checked_in' => ['state' => 'used', 'refunded' => false, 'reveals_at' => null],
            $event !== null && ! $event->qrIsVisible() => ['state' => 'locked', 'refunded' => false, 'reveals_at' => $event->qr_override === null ? $event->qrRevealsAt() : null],
            default => ['state' => 'visible', 'refunded' => false, 'reveals_at' => null],
        };
    }

    /** Ticket badge from the real ticket / order status plus whether the event is over. */
    public static function ticketBadge(IssuedTicket $ticket, ?Event $event): array
    {
        $qr = self::qrState($ticket, $event);

        return match (true) {
            $qr['state'] === 'canceled' && $qr['refunded'] => ['Refunded', 'default'],
            $qr['state'] === 'canceled' => ['Canceled', 'danger'],
            $qr['state'] === 'used' => ['Checked in', 'default'],
            self::eventEnded($event) => ['Event ended', 'default'],
            default => ['Valid', 'success'],
        };
    }

    /** Which My Tickets list a ticket belongs to: canceled (incl. refunded) → used → past (event ended) → upcoming. */
    public static function ticketGroup(IssuedTicket $ticket, ?Event $event): string
    {
        return match (self::qrState($ticket, $event)['state']) {
            'canceled' => 'canceled',
            'used' => 'used',
            default => self::eventEnded($event) || $event === null ? 'past' : 'upcoming',
        };
    }

    /** True only when the QR itself may be rendered and sent to the browser. */
    public static function qrVisible(IssuedTicket $ticket, ?Event $event): bool
    {
        return self::qrState($ticket, $event)['state'] === 'visible';
    }
}
