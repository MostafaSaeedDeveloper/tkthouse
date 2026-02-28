<?php

namespace App\Mail;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class OrderRejectedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Order $order)
    {
        $this->order->loadMissing('customer');
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Order update - booking not approved #'.$this->order->order_number);
    }

    public function content(): Content
    {
        return new Content(view: 'emails.orders.rejected', with: ['order' => $this->order]);
    }

    public function attachments(): array
    {
        return [];
    }
}
