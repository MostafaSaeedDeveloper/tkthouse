<?php

namespace App\Mail;

use App\Models\IssuedTicket;
use App\Models\Order;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use App\Models\Event;

class HolderTicketsIssuedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Order $order, public Collection $tickets, public string $holderEmail)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Your tickets are ready - Order #'.$this->order->order_number);
    }

    public function content(): Content
    {
        return new Content(view: 'emails.tickets.holder-issued', with: [
            'order' => $this->order,
            'tickets' => $this->tickets,
            'holderEmail' => $this->holderEmail,
        ]);
    }

    public function attachments(): array
    {
        $ticketPdfData = $this->tickets->map(function (IssuedTicket $ticket) {
            return [
                'ticket' => $ticket,
                'event' => $this->resolveEvent($ticket->ticket_name ?? ''),
                'qrDataUri' => $this->qrDataUri($ticket),
            ];
        });

        $pdf = Pdf::loadView('emails.tickets.unified-bundle-pdf', [
            'order' => $this->order,
            'ticketPdfData' => $ticketPdfData,
        ])->output();

        return [
            Attachment::fromData(fn () => $pdf, 'tickets-'.$this->order->order_number.'.pdf')
                ->withMime('application/pdf'),
        ];
    }



    private function qrDataUri(IssuedTicket $ticket): string
    {
        $payload = (string) $ticket->ticket_number;
        $url = 'https://api.qrserver.com/v1/create-qr-code/?size=260x260&data='.urlencode($payload);

        try {
            $response = Http::timeout(15)->get($url);
            if ($response->successful()) {
                return 'data:image/png;base64,'.base64_encode($response->body());
            }
        } catch (\Throwable) {
            // Ignore and fallback to URL
        }

        return $url;
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
