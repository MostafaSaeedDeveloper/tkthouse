<?php

namespace App\Mail;

use App\Models\Event;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * "Your QR code is available": event details and one QR per ticket of THIS recipient (name + type), never the order, payment or anybody
 * else's ticket. $items = [['holder' => , 'type' => , 'png' => binary|null, 'uuid' => ], …]. With $test the e-mail is a clearly marked
 * sample whose QR codes are not valid for entry.
 */
class QrReleaseMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Event $event, public array $items, public string $viewUrl, public bool $test = false, public ?string $testNote = null)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: ($this->test ? '[TEST] ' : '').'Your QR code is ready — '.$this->event->name);
    }

    public function content(): Content
    {
        return new Content(view: 'emails.tickets.qr-release', with: ['event' => $this->event, 'items' => $this->items, 'viewUrl' => $this->viewUrl, 'test' => $this->test, 'testNote' => $this->testNote]);
    }
}
