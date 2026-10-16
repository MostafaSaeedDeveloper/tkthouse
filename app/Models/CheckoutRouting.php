<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Provisional (not final) agent + merchant account shown to one checkout session. See docs/v2-stage-e-design.md §3. */
class CheckoutRouting extends Model
{
    protected $fillable = ['token', 'session_hash', 'assignee_id', 'integration_account_id', 'source', 'snapshot', 'expires_at', 'consumed_order_id', 'released_at'];

    protected function casts(): array
    {
        return ['snapshot' => 'array', 'expires_at' => 'datetime', 'released_at' => 'datetime'];
    }

    public function account()
    {
        return $this->belongsTo(IntegrationAccount::class, 'integration_account_id');
    }

    public function scopeLive($query)
    {
        return $query->whereNull('consumed_order_id')->whereNull('released_at')->where('expires_at', '>', now());
    }
}
