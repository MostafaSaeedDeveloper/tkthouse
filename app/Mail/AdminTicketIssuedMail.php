<?php

namespace App\Mail;

use App\Models\Ticket;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class AdminTicketIssuedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Ticket $ticket,
        public string $showUrl,
        public string $recipientEmail,
        private ?string $pdfBinary = null
    ) {
        $this->ticket->loadMissing('order.customer');
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Your Ticket #'.$this->ticket->ticket_number);
    }

    public function content(): Content
    {
        return new Content(view: 'emails.tickets.admin-issued', with: [
            'ticket' => $this->ticket,
            'showUrl' => $this->showUrl,
            'recipientEmail' => $this->recipientEmail,
            'hasPdf' => $this->pdfBinary !== null,
        ]);
    }

    public function attachments(): array
    {
        // Tickets bought online are digital (the e-mail links to the customer's account), so no PDF goes with them.
        if ($this->pdfBinary === null) {
            return [];
        }

        return [
            Attachment::fromData(fn () => $this->pdfBinary, 'ticket-'.$this->ticket->ticket_number.'.pdf')
                ->withMime('application/pdf'),
        ];
    }
}
