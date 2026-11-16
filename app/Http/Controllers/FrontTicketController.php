<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\IssuedTicket;
use App\Models\Ticket;
use App\Support\CustomerPortal;
use App\Support\TicketQr;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

class FrontTicketController extends Controller
{
    public function show(Request $request, IssuedTicket $ticket)
    {
        $this->authorizeTicket($request, $ticket);

        $ticket->load(['order', 'dashboardTicket.event', 'orderItem.event']);
        $event = $ticket->event();
        $qr = CustomerPortal::qrState($ticket, $event);

        // Tickets of the same order the visitor may open: lets them page through "Ticket 2 of 4".
        $siblings = $ticket->order_id === null
            ? collect([$ticket])
            : IssuedTicket::query()->ownedByCustomer($request->user())->where('order_id', $ticket->order_id)->orderBy('id')->get(['id', 'uuid', 'holder_name', 'ticket_name']);

        return response()->view('front.tickets.show', [
            'ticket' => $ticket,
            'event' => $event,
            'qr' => $qr,
            // The QR image is rendered into the page only when it may be shown; otherwise nothing about it reaches the browser.
            'qrSvg' => $qr['state'] === 'visible' ? TicketQr::svg($ticket->qrPayload(), 320) : null,
            'siblings' => $siblings,
            // Order and payment details belong to the buyer; a holder the ticket was bought for only sees their ticket.
            'isBuyer' => $ticket->order_id !== null && \App\Models\Order::query()->ownedByCustomer($request->user())->whereKey($ticket->order_id)->exists(),
        ])->header('Cache-Control', 'private, no-store');
    }

    /**
     * Live QR state for the open ticket page (countdown reached zero / periodic check): the QR is returned only while it is
     * visible, so an admin hiding it is honoured on the next poll and a hidden QR is never sent.
     */
    public function qr(Request $request, IssuedTicket $ticket)
    {
        $this->authorizeTicket($request, $ticket);

        $ticket->load(['order', 'dashboardTicket.event', 'orderItem.event']);
        $qr = CustomerPortal::qrState($ticket, $ticket->event());

        return response()->json([
            'state' => $qr['state'],
            'refunded' => $qr['refunded'],
            'reveals_at' => $qr['reveals_at']?->toIso8601String(),
            'server_now' => now()->toIso8601String(),
            'svg' => $qr['state'] === 'visible' ? TicketQr::svg($ticket->qrPayload(), 320) : null,
        ])->header('Cache-Control', 'private, no-store');
    }

    /**
     * Legacy PDF of a ticket issued before digital tickets. Tickets with a token QR have no PDF at all, and a legacy PDF is
     * only produced while the event's QR is visible (so it can never be used to get around a hidden QR).
     */
    public function download(Request $request, IssuedTicket $ticket)
    {
        $this->authorizeTicket($request, $ticket);
        $ticket->load(['order', 'dashboardTicket.event', 'orderItem.event']);
        abort_if($ticket->dashboardTicket?->isRefundCanceled(), 410, 'This ticket was canceled after a refund.');

        if ($ticket->dashboardTicket?->hasTokenQr() || ! CustomerPortal::qrVisible($ticket, $ticket->event())) {
            return redirect()->route('front.tickets.show', $ticket);
        }

        $event = $this->resolveEvent($ticket->ticket_name ?? '');
        $qrDataUri = $ticket->qrUrl();

        $pdf = Pdf::loadView('front.tickets.pdf', compact('ticket', 'event', 'qrDataUri'));

        return $pdf->download('ticket-'.$ticket->ticket_number.'.pdf');
    }

    /**
     * Apple Wallet pass (.pkpass) for one of the signed-in customer's own tickets. The pass carries the QR, so it follows the same rule as the
     * ticket page: only while the QR is visible and the ticket is still valid (hidden / canceled / refunded / used → no pass).
     */
    public function wallet(Request $request, IssuedTicket $ticket)
    {
        $this->authorizeTicket($request, $ticket);
        abort_unless(\App\Services\Wallet\ApplePassBuilder::configured(), 404);

        $ticket->load(['order', 'dashboardTicket.event', 'orderItem.event']);
        $event = $ticket->event() ?? $this->resolveEvent($ticket->ticket_name ?? '');
        abort_unless(CustomerPortal::qrVisible($ticket, $event), 410, 'This ticket\'s QR code is not available.');

        $path = app(\App\Services\Wallet\ApplePassBuilder::class)->build($ticket, $event);

        return response()->download($path, 'ticket-'.$ticket->ticket_number.'.pkpass', [
            'Content-Type' => 'application/vnd.apple.pkpass',
            'Cache-Control' => 'private, no-store',
        ])->deleteFileAfterSend(true);
    }

    public function shortDownload(string $ticketNumber)
    {
        $ticket = Ticket::query()->where('ticket_number', $ticketNumber)->first();
        // Digital tickets have no public PDF link. (Legacy links keep working; the signed link below re-checks everything.)
        abort_if(! $ticket || $ticket->hasTokenQr(), 404);

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
        abort_if($ticket->isRefundCanceled(), 410, 'This ticket was canceled after a refund.');
        abort_if($ticket->hasTokenQr(), 404);

        // A hidden QR stays hidden here too, for tickets issued before digital tickets.
        $issued = IssuedTicket::query()->with(['order', 'dashboardTicket.event', 'orderItem.event'])->where('ticket_number', $ticketNumber)->first();
        abort_if($issued && ! CustomerPortal::qrVisible($issued, $issued->event()), 403, 'This ticket\'s QR code is not available yet.');

        $event = $this->resolveEvent($ticket->name ?? '');
        $qrDataUri = TicketQr::dataUri($ticket->qrContent());

        $pdf = Pdf::loadView('admin.tickets.pdf', compact('ticket', 'event', 'qrDataUri'));

        return $pdf->download('ticket-'.$ticket->ticket_number.'.pdf');
    }

    /** Only the account that owns the paid order (or the verified holder of a gifted ticket) may open a ticket. */
    private function authorizeTicket(Request $request, IssuedTicket $ticket): void
    {
        $user = $request->user();
        abort_unless($user, 403);

        abort_unless(IssuedTicket::query()->ownedByCustomer($user)->whereKey($ticket->id)->exists(), 403);
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
