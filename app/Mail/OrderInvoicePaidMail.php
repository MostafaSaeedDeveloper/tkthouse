<?php

namespace App\Mail;

use App\Models\Order;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class OrderInvoicePaidMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Order $order)
    {
        $this->order->loadMissing(['customer', 'items', 'issuedTickets']);
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Payment invoice - Order #'.$this->order->order_number);
    }

    public function content(): Content
    {
        return new Content(view: 'emails.tickets.invoice-paid', with: ['order' => $this->order]);
    }

    public function attachments(): array
    {
        $pdf = Pdf::loadView('emails.tickets.invoice-pdf', ['order' => $this->order])->output();

        return [
            Attachment::fromData(fn () => $pdf, 'invoice-'.$this->order->order_number.'.pdf')
                ->withMime('application/pdf'),
        ];
    }
}
