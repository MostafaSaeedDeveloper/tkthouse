<?php

namespace App\Mail;

use App\Models\Ticket;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;

class TicketMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Ticket $ticket, public string $pdfPath) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'تذكرة TKT House');
    }

    public function content(): Content
    {
        return new Content(view: 'emails.ticket');
    }

    public function attachments(): array
    {
        return [
            Attachment::fromPath(Storage::disk('local')->path($this->pdfPath))
                ->as($this->ticket->code.'.pdf')
                ->withMime('application/pdf'),
        ];
    }
}
