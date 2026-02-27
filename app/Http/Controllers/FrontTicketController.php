<?php

namespace App\Http\Controllers;

use App\Models\IssuedTicket;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;

class FrontTicketController extends Controller
{
    public function show(Request $request, IssuedTicket $ticket)
    {
        $this->authorizeTicket($request, $ticket);

        return view('front.tickets.show', compact('ticket'));
    }

    public function download(Request $request, IssuedTicket $ticket)
    {
        $this->authorizeTicket($request, $ticket);

        $pdf = Pdf::loadView('front.tickets.pdf', compact('ticket'));

        return $pdf->download('ticket-'.$ticket->ticket_number.'.pdf');
    }

    private function authorizeTicket(Request $request, IssuedTicket $ticket): void
    {
        $user = $request->user();
        abort_unless($user, 403);

        $ticket->loadMissing('order.customer');

        $canView = (int) $ticket->order->user_id === (int) $user->id
            || $ticket->order->customer?->email === $user->email;

        abort_unless($canView, 403);
    }
}
