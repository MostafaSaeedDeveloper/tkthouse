<?php

namespace App\Mail;

use App\Models\PaymentRefund;
use App\Support\RefundMailData;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Customer refund e-mails (kind: received | approved | completed) in the standard TKT House e-mail layout.
 * Sent synchronously through the normal mailer (no queue needed). Customer-facing: the data comes from RefundMailData, which never carries
 * a payment provider, gateway, dashboard, reference of the provider or internal note.
 */
class RefundCustomerMail extends Mailable
{
    use Queueable, SerializesModels;

    public const KINDS = ['received', 'approved', 'completed'];

    public function __construct(public PaymentRefund $refund, public string $kind, ?string $locale = null)
    {
        $this->locale($locale ?: config('app.locale'));
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: __('refund_mail.'.$this->kind.'.subject'));
    }

    public function content(): Content
    {
        return new Content(view: 'emails.refunds.'.$this->kind, with: ['d' => RefundMailData::for($this->refund), 'kind' => $this->kind]);
    }

    public function attachments(): array
    {
        return [];
    }
}
