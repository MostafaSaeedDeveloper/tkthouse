<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** One verified-or-not provider notification. The payload holds only whitelisted, non-sensitive fields and is encrypted at rest. */
class WebhookEvent extends Model
{
    protected $fillable = [
        'provider', 'integration_account_id', 'payment_attempt_id', 'order_id', 'event_type', 'dedupe_key', 'transaction_reference',
        'signature_status', 'processing_status', 'error', 'payload', 'deliveries', 'process_attempts', 'received_at', 'processed_at',
    ];

    protected $hidden = ['payload'];

    protected function casts(): array
    {
        return ['payload' => 'encrypted:array', 'received_at' => 'datetime', 'processed_at' => 'datetime'];
    }

    public function account()
    {
        return $this->belongsTo(IntegrationAccount::class, 'integration_account_id');
    }

    public function attempt()
    {
        return $this->belongsTo(PaymentAttempt::class, 'payment_attempt_id');
    }

    public function isAuthentic(): bool
    {
        return $this->signature_status === 'valid';
    }

    /** How the signature check reads to an operator. No vendor key / no documented hash is normal for some setups, so it is shown neutrally, not as a warning. */
    public function signatureLabel(): string
    {
        return match ($this->signature_status) {
            'valid' => 'Verified',
            'invalid' => 'Invalid',
            'missing' => 'Missing',
            'unverifiable', 'unsupported' => 'Not signed',
            default => (string) $this->signature_status,
        };
    }

    public function signatureVariant(): string
    {
        return match ($this->signature_status) {
            'valid' => 'success',
            'invalid' => 'danger',
            'missing' => 'warning',
            default => 'default',
        };
    }
}
