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
use Illuminate\Support\Collection;

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
        $pdf = Pdf::loadView('emails.tickets.holder-pdf', [
            'order' => $this->order,
            'tickets' => $this->tickets,
        ])->output();

        return [
            Attachment::fromData(fn () => $pdf, 'tickets-'.$this->order->order_number.'.pdf')
                ->withMime('application/pdf'),
        ];
    }
}
