<?php

namespace App\Support;

use App\Models\Order;
use Illuminate\Support\Collection;

/**
 * Where the "VIEW YOUR TICKETS" button of a booking e-mail leads. The target is always inside the customer's account:
 * a visitor who is not signed in is sent to the ticket-access page (e-mail code) and returned here afterwards.
 */
class TicketLinks
{
    /** The buyer: one ticket opens it directly, several open the order's tickets in one organised list. */
    public static function orderTickets(Order $order): string
    {
        $order->loadMissing('issuedTickets');

        return self::forTickets($order, $order->issuedTickets);
    }

    /** A holder the tickets were bought for: only the tickets issued to their address. */
    public static function holderTickets(Order $order, Collection $tickets): string
    {
        return self::forTickets($order, $tickets);
    }

    private static function forTickets(Order $order, Collection $tickets): string
    {
        if ($tickets->count() === 1) {
            return route('front.tickets.show', $tickets->first());
        }

        return route('front.account.tickets', ['order' => $order->order_number, 'when' => 'all']);
    }
}
