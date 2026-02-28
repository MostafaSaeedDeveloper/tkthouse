<?php

namespace App\Mail;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class OrderApprovedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Order $order, public string $paymentLink)
    {
        $this->order->loadMissing('customer');
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Order approved - payment required #'.$this->order->order_number);
    }

    public function content(): Content
    {
        return new Content(view: 'emails.orders.approved', with: [
            'order' => $this->order,
            'paymentLink' => $this->paymentLink,
        ]);
    }

    public function attachments(): array
    {
        return [];
    }
}
