<?php

namespace App\Mail;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class OrderStatusChangedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Order $order, public string $oldStatus, public string $newStatus)
    {
        $this->order->loadMissing('customer');
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Order status updated #'.$this->order->order_number);
    }

    public function content(): Content
    {
        return new Content(view: 'emails.orders.status-changed', with: [
            'order' => $this->order,
            'oldStatus' => $this->oldStatus,
            'newStatus' => $this->newStatus,
        ]);
    }

    public function attachments(): array
    {
        return [];
    }
}

