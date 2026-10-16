<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PaymentRefund extends Model
{
    public const REQUESTED = 'requested';
    public const PROCESSING = 'processing';
    public const SUCCEEDED = 'succeeded';
    public const FAILED = 'failed';
    public const REJECTED = 'rejected';
    public const UNKNOWN = 'unknown';

    /** Statuses that still hold part of the refundable balance (money may leave, or already left). */
    public const HOLDING = [self::REQUESTED, self::PROCESSING, self::UNKNOWN, self::SUCCEEDED];
    /** Statuses in which a refund may still be completing at the provider (at most one per attempt). */
    public const IN_FLIGHT = [self::REQUESTED, self::PROCESSING, self::UNKNOWN];

    protected $fillable = [
        'internal_reference', 'payment_attempt_id', 'order_id', 'integration_account_id', 'provider', 'mode', 'type', 'status', 'awaiting_approval',
        'currency', 'amount_minor', 'reason', 'idempotency_key', 'provider_refund_id', 'provider_reference', 'requested_by', 'approved_by',
        'requested_at', 'submitted_at', 'completed_at', 'last_checked_at', 'failure_reason', 'meta',
    ];

    protected function casts(): array
    {
        return [
            'awaiting_approval' => 'boolean', 'amount_minor' => 'integer', 'meta' => 'array',
            'requested_at' => 'datetime', 'submitted_at' => 'datetime', 'completed_at' => 'datetime', 'last_checked_at' => 'datetime',
        ];
    }

    public function attempt()
    {
        return $this->belongsTo(PaymentAttempt::class, 'payment_attempt_id');
    }

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function account()
    {
        return $this->belongsTo(IntegrationAccount::class, 'integration_account_id');
    }

    public function requester()
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function amount(): string
    {
        return self::minorToDecimal($this->amount_minor);
    }

    public static function minorToDecimal(int $minor): string
    {
        return intdiv($minor, 100).'.'.str_pad((string) ($minor % 100), 2, '0', STR_PAD_LEFT);
    }

    /** Strict decimal-string parser: "50", "50.5", "50.25" only. No floats, no negatives, no more than 2 decimals. */
    public static function decimalToMinor(string $value): ?int
    {
        $value = trim($value);
        if (! preg_match('/^(\d{1,9})(?:\.(\d{1,2}))?$/', $value, $m)) {
            return null;
        }

        return ((int) $m[1]) * 100 + (int) str_pad($m[2] ?? '0', 2, '0');
    }

    public function isFinal(): bool
    {
        return in_array($this->status, [self::SUCCEEDED, self::FAILED, self::REJECTED], true);
    }

    public function timeline(): array
    {
        return (array) ($this->meta['timeline'] ?? []);
    }
}
