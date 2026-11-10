<?php

namespace App\Mail;

use App\Models\Order;
use App\Services\Invoices\Invoice;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Payment confirmation + invoice PDF. Sent only from TicketIssuanceService (which runs for paid orders only, once per order),
 * or on demand from the admin "Resend invoice" button.
 */
class OrderInvoicePaidMail extends Mailable
{
    use Queueable, SerializesModels;

    public const SUBJECT = 'Your Order Is Confirmed — Payment Invoice Attached | TKT House';

    public function __construct(public Order $order)
    {
        $this->order->loadMissing(['customer', 'items.event', 'issuedTickets']);
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: self::SUBJECT);
    }

    public function content(): Content
    {
        return new Content(view: 'emails.tickets.invoice-paid', with: [
            'order' => $this->order,
            'invoice' => Invoice::make($this->order),
            'orderUrl' => $this->order->user_id ? route('front.account.orders.show', $this->order->order_number) : null,
            'ticketsUrl' => $this->order->user_id && $this->order->issuedTickets->isNotEmpty() ? route('front.account.tickets') : null,
        ]);
    }

    public function attachments(): array
    {
        $invoice = Invoice::make($this->order);
        $pdf = $invoice->pdf();

        return [
            Attachment::fromData(fn () => $pdf, $invoice->filename())->withMime('application/pdf'),
        ];
    }
}
