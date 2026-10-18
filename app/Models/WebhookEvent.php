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
}
