<?php

namespace App\Mail;

use App\Models\Order;
use App\Services\Invoices\InvoiceService;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

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
        // Tickets are no longer attached as a PDF: the e-mail links to each ticket in the customer's account.
        $attachments = [];

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
}
