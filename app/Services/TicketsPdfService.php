<?php

namespace App\Services;

use App\Models\Ticket;
use Illuminate\Support\Facades\Storage;

class TicketsPdfService
{
    public function generate(Ticket $ticket): string
    {
        $ticket->loadMissing('event.ticketTemplate');
        $view = $ticket->event->ticketTemplate?->view_key ?? 'tickets.templates.classic';
        $html = view($view, compact('ticket'))->render();

        $path = "tickets/{$ticket->event_id}/{$ticket->code}.pdf";
        Storage::disk('local')->put($path, $html);

        return $path;
    }
}
