<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PaymentAttempt extends Model
{
    public const INITIATED = 'initiated';
    public const PAID = 'paid';
    public const FAILED = 'failed';
    public const EXPIRED = 'expired';

    protected $fillable = [
        'order_id', 'provider', 'method_code', 'reference', 'transaction_id', 'amount', 'currency',
        'status', 'failure_reason', 'meta', 'settled_at', 'integration_account_id', 'routing_snapshot',
        'internal_reference', 'provider_order_id', 'provider_reference', 'paid_at', 'last_checked_at',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'meta' => 'array',
            'routing_snapshot' => 'array',
            'settled_at' => 'datetime',
            'paid_at' => 'datetime',
            'last_checked_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        // Immutable attempt-to-merchant binding: once an attempt is opened with an account (or deliberately with none = legacy),
        // neither the account nor the audit snapshot can change. Money collected through it is verified with that account only.
        static::updating(function (PaymentAttempt $attempt) {
            foreach (['integration_account_id', 'routing_snapshot'] as $field) {
                if ($attempt->isDirty($field) && $attempt->getOriginal($field) !== null) {
                    throw new \LogicException("Payment attempt {$field} is immutable once set.");
                }
            }
        });
    }

    /** Why the system flagged this payment "Needs review": [short label, what to do]. Null when it is not flagged. @return array{0: string, 1: string}|null */
    public function reviewReason(): ?array
    {
        $reason = data_get($this->meta, 'needs_review');
        if (! $reason) {
            return null;
        }

        return match ($reason) {
            'duplicate_payment' => ['Paid twice', 'This payment arrived after the order was already paid (a second payment for the same order). Check whether the customer was charged twice and refund the extra one.'],
            'order_closed' => ['Order was closed', 'The payment arrived for an order that was canceled, rejected or still awaiting approval. The money is recorded but nothing was issued automatically: reopen the order or refund.'],
            'event_ended' => ['Event ended', 'The payment arrived but the event has already ended, so the order was not fulfilled automatically. Decide whether to honour it or refund.'],
            'event_not_on_sale' => ['Event off sale', 'The payment arrived but the event is no longer on sale, so the order was not fulfilled automatically. Decide whether to honour it or refund.'],
            'event_missing' => ['Event missing', 'The payment arrived but the order\'s event no longer exists. Refund review required.'],
            'ticket_unavailable' => ['Ticket unavailable', 'The payment arrived but a ticket type on the order is no longer active, so the order was not fulfilled automatically. Refund review required.'],
            default => ['Needs review', 'Flagged for review: '.str_replace('_', ' ', (string) $reason).'.'],
        };
    }

    public function webhookEvents()
    {
        return $this->hasMany(WebhookEvent::class);
    }

    /** The "payment.failed" webhook recorded for this attempt, if any (Fawaterak reports failures only this way). */
    public function failedWebhookEvent()
    {
        return $this->hasOne(WebhookEvent::class)->where('event_type', 'payment.failed')->latestOfMany();
    }

    /**
     * Status to show people. A gateway may report a failure only by webhook, which is stored but never changes the attempt itself
     * (the attempt would otherwise keep reading "Processing"); such an open attempt is shown as failed. A paid attempt never is.
     */
    public function displayStatus(): string
    {
        return $this->status === self::INITIATED && $this->failedWebhookEvent !== null ? self::FAILED : (string) $this->status;
    }

    public static function newInternalReference(): string
    {
        do {
            $ref = 'PAY-'.strtoupper(\Illuminate\Support\Str::random(10));
        } while (static::query()->where('internal_reference', $ref)->exists());

        return $ref;
    }

    public function integrationAccount()
    {
        return $this->belongsTo(IntegrationAccount::class);
    }

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function isOpen(): bool
    {
        return $this->status === self::INITIATED;
    }
}
