<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Ticket;
use App\Services\TicketDeliveryService;
use App\Services\TicketsPdfService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class TicketController extends Controller
{
    public function index(): View
    {
        return view('admin.tickets.index', [
            'tickets' => Ticket::with(['event', 'ticketType', 'order'])->latest()->paginate(20),
        ]);
    }

    public function show(Ticket $ticket): View
    {
        $ticket->loadMissing(['event', 'ticketType', 'order.user']);

        return view('admin.tickets.show', compact('ticket'));
    }

    public function resend(Ticket $ticket): RedirectResponse
    {
        $order = $ticket->order()->with(['tickets.event.ticketTemplate', 'user'])->firstOrFail();
        app(TicketDeliveryService::class)->deliverOrderTickets($order);

        return back()->with('status', 'Ticket delivery has been queued.');
    }

    public function download(Ticket $ticket): BinaryFileResponse
    {
        $path = app(TicketsPdfService::class)->generate($ticket);

        return response()->download(storage_path('app/'.$path));
    }
}
