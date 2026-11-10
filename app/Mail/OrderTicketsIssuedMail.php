<?php

namespace App\Mail;

use App\Models\Event;
use App\Models\IssuedTicket;
use App\Models\Order;
use App\Services\Invoices\InvoiceService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
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
        return new Envelope(subject: 'Your order is complete: invoice & tickets - Order #'.$this->order->order_number);
    }

    public function content(): Content
    {
        return new Content(view: 'emails.tickets.issued', with: ['order' => $this->order, 'invoice' => $this->invoice()]);
    }

    public function attachments(): array
    {
        $ticketPdfData = $this->order->issuedTickets->map(function (IssuedTicket $ticket) {
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

        $attachments = [
            \Illuminate\Mail\Mailables\Attachment::fromData(fn () => $pdf, 'tickets-'.$this->order->order_number.'.pdf')
                ->withMime('application/pdf'),
        ];

        // The completed-order e-mail doubles as the invoice. A problem rendering it must never stop the tickets from going out.
        if ($this->invoice() !== null) {
            try {
                $service = app(InvoiceService::class);
                $invoicePdf = $service->pdf($this->order);
                $attachments[] = \Illuminate\Mail\Mailables\Attachment::fromData(fn () => $invoicePdf, $service->filename($this->order))
                    ->withMime('application/pdf');
            } catch (\Throwable $e) {
                Log::warning('Invoice PDF could not be attached', ['order_id' => $this->order->id, 'error' => $e->getMessage()]);
            }
        }

        return $attachments;
    }

    /** @return array<string, mixed>|null the issued invoice snapshot, or null if it could not be issued */
    private function invoice(): ?array
    {
        try {
            return app(InvoiceService::class)->ensure($this->order)->invoice_snapshot;
        } catch (\Throwable $e) {
            Log::warning('Invoice could not be issued', ['order_id' => $this->order->id, 'error' => $e->getMessage()]);

            return null;
        }
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
