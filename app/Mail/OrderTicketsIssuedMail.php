<?php

namespace App\Mail;

use App\Models\Event;
use App\Models\Order;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Str;

class OrderTicketsIssuedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Order $order)
    {
        $this->order->loadMissing(['customer', 'issuedTickets']);
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Your tickets are ready - Order #'.$this->order->order_number);
    }

    public function content(): Content
    {
        return new Content(view: 'emails.tickets.issued', with: ['order' => $this->order]);
    }

    public function attachments(): array
    {
        $ticketPdfData = $this->order->issuedTickets->map(function ($ticket) {
            return [
                'ticket' => $ticket,
                'event' => $this->resolveEvent($ticket->ticket_name ?? ''),
                'qrDataUri' => $ticket->qrUrl(),
            ];
        });

        $pdf = Pdf::loadView('emails.tickets.unified-bundle-pdf', [
            'order' => $this->order,
            'ticketPdfData' => $ticketPdfData,
        ])->output();

        return [
            \Illuminate\Mail\Mailables\Attachment::fromData(fn () => $pdf, 'tickets-'.$this->order->order_number.'.pdf')
                ->withMime('application/pdf'),
        ];
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
