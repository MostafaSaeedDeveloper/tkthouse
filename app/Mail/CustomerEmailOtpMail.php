<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class CustomerEmailOtpMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public string $otpCode)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Your TKTHouse verification code',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.auth.customer-email-otp',
            with: ['otpCode' => $this->otpCode],
        );
    }
}
