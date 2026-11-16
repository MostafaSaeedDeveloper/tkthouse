<?php

namespace App\Services\Tickets;

use App\Models\IssuedTicket;
use App\Models\Ticket;
use Illuminate\Support\Str;

/**
 * Guest-list tickets are digital tickets too: each gets an issued-ticket record (no order) so it has its own ticket page,
 * opened by its holder after an e-mail code. Idempotent; also used to bring older guest tickets online the first time they are sent.
 */
class GuestTicketIssuer
{
    public function ensure(Ticket $ticket): IssuedTicket
    {
        return IssuedTicket::query()->where('ticket_number', $ticket->ticket_number)->first()
            ?? IssuedTicket::create([
                'order_id' => null,
                'order_item_id' => null,
                'uuid' => (string) Str::uuid(),
                'ticket_number' => $ticket->ticket_number,
                'holder_name' => (string) ($ticket->holder_name ?: 'Guest'),
                'holder_email' => (string) ($ticket->holder_email ?: ''),
                'holder_phone' => $ticket->holder_phone,
                'ticket_name' => (string) $ticket->name,
                'ticket_price' => 0,
                'seat_index' => 1,
                'event_id' => $ticket->event_id,
                'qr_locked_at_issue' => $ticket->event ? ! $ticket->event->qrIsVisible() : false,
            ]);
    }

    /** Keeps the digital ticket's holder in step with the dashboard ticket (the e-mail is what proves who may open it). */
    public function syncHolder(Ticket $ticket): void
    {
        IssuedTicket::query()->where('ticket_number', $ticket->ticket_number)->whereNull('order_id')->update([
            'holder_name' => (string) ($ticket->holder_name ?: 'Guest'),
            'holder_email' => (string) ($ticket->holder_email ?: ''),
            'holder_phone' => $ticket->holder_phone,
        ]);
    }
}
