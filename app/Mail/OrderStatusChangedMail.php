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
        $subject = $this->newStatus === 'canceled'
            ? 'Payment Time Expired #'.$this->order->order_number
            : 'Order status updated #'.$this->order->order_number;

        return new Envelope(subject: $subject);
    }

    public function content(): Content
    {
        $view = $this->newStatus === 'canceled'
            ? 'emails.orders.canceled'
            : 'emails.orders.status-changed';

        return new Content(view: $view, with: [
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

