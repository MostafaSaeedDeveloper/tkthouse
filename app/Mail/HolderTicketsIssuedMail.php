<?php

namespace App\Mail;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
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

    /** Tickets are viewed in the customer's account via the link in the e-mail; no PDF is attached. */
    public function attachments(): array
    {
        return [];
    }
}
