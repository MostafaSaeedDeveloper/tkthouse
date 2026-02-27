<?php

namespace App\Mail;

use App\Models\Order;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

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
        $pdf = Pdf::loadView('emails.tickets.bundle-pdf', ['order' => $this->order])->output();

        return [
            \Illuminate\Mail\Mailables\Attachment::fromData(fn () => $pdf, 'tickets-'.$this->order->order_number.'.pdf')
                ->withMime('application/pdf'),
        ];
    }
}
