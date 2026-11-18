<?php

namespace App\Mail;

use App\Models\Ticket;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class GuestInvitationMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Ticket $ticket,
        private string $pdfBinary
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Your invitation for '.$this->ticket->eventLabel());
    }

    public function content(): Content
    {
        return new Content(view: 'emails.tickets.guest-invitation', with: [
            'guestName' => (string) ($this->ticket->holder_name ?? 'Guest'),
            'eventName' => $this->ticket->eventLabel(),
        ]);
    }

    public function attachments(): array
    {
        return [
            Attachment::fromData(fn () => $this->pdfBinary, 'ticket-'.$this->ticket->ticket_number.'.pdf')
                ->withMime('application/pdf'),
        ];
    }
}
