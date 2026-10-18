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

    public function webhookEvents()
    {
        return $this->hasMany(WebhookEvent::class);
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
