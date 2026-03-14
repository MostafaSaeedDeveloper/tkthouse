<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\IssuedTicket;
use App\Models\Ticket;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

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

        $event = $this->resolveEvent($ticket->ticket_name ?? '');
        $qrDataUri = $ticket->qrUrl();

        $pdf = Pdf::loadView('front.tickets.pdf', compact('ticket', 'event', 'qrDataUri'));

        return $pdf->download('ticket-'.$ticket->ticket_number.'.pdf');
    }

    public function shortDownload(string $ticketNumber)
    {
        $signedUrl = URL::temporarySignedRoute(
            'front.tickets.public-download',
            now()->addDays(7),
            ['ticketNumber' => $ticketNumber]
        );

        return redirect($signedUrl);
    }

    public function publicDownloadByNumber(Request $request, string $ticketNumber)
    {
        abort_unless($request->hasValidSignature(), 403);

        $ticket = Ticket::query()->where('ticket_number', $ticketNumber)->firstOrFail();
        $event = $this->resolveEvent($ticket->name ?? '');
        $qrDataUri = $ticket->qr_payload
            ? 'https://api.qrserver.com/v1/create-qr-code/?size=260x260&data='.urlencode((string) $ticket->qr_payload)
            : 'https://api.qrserver.com/v1/create-qr-code/?size=260x260&data='.urlencode((string) $ticket->ticket_number);

        $pdf = Pdf::loadView('admin.tickets.pdf', compact('ticket', 'event', 'qrDataUri'));

        return $pdf->download('ticket-'.$ticket->ticket_number.'.pdf');
    }

    private function authorizeTicket(Request $request, IssuedTicket $ticket): void
    {
        $user = $request->user();
        abort_unless($user, 403);

        $ticket->loadMissing('order.customer');

        $orderUserId = $ticket->order?->user_id;
        $orderCustomerEmail = $ticket->order?->customer?->email;
        $holderEmail = $ticket->holder_email;

        $canView = ($orderUserId !== null && (int) $orderUserId === (int) $user->id)
            || ($orderCustomerEmail !== null && strcasecmp($orderCustomerEmail, (string) $user->email) === 0)
            || ($holderEmail !== null && strcasecmp($holderEmail, (string) $user->email) === 0);

        abort_unless($canView, 403);
    }

    private function resolveEvent(string $ticketName): ?Event
    {
        $candidates = collect([
            trim((string) Str::beforeLast($ticketName, ' - ')),
            trim((string) Str::before($ticketName, ' - ')),
            trim($ticketName),
        ])->filter()->unique()->values();

        foreach ($candidates as $eventName) {
            $event = Event::query()
                ->with('images')
                ->where('name', $eventName)
                ->first();

            if ($event) {
                return $event;
            }
        }

        return null;
    }
}
