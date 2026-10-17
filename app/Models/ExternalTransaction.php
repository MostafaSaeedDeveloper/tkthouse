<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** A transaction known from the provider (lookup by id or CSV), NOT created by TKT House. Never an order, never a settlement. */
class ExternalTransaction extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'is_refunded' => 'boolean', 'is_voided' => 'boolean', 'snapshot' => 'array',
            'provider_created_at' => 'datetime', 'paid_at' => 'datetime', 'verified_at' => 'datetime', 'last_checked_at' => 'datetime',
        ];
    }

    public function account()
    {
        return $this->belongsTo(IntegrationAccount::class, 'integration_account_id');
    }

    public function attempt()
    {
        return $this->belongsTo(PaymentAttempt::class, 'matched_attempt_id');
    }

    public function amount(): ?string
    {
        return $this->amount_minor === null ? null : number_format($this->amount_minor / 100, 2, '.', '');
    }

    public function isLinked(): bool
    {
        return $this->matched_attempt_id !== null;
    }
}
