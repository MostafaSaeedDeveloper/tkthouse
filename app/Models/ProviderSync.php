<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProviderSync extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['rate_limited' => 'boolean', 'started_at' => 'datetime', 'finished_at' => 'datetime'];
    }

    public function account()
    {
        return $this->belongsTo(IntegrationAccount::class, 'integration_account_id');
    }
}
