<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FeesRule extends Model
{
    use HasFactory;

    protected $guarded = [];

    public function policy(): BelongsTo
    {
        return $this->belongsTo(FeesPolicy::class, 'fees_policy_id');
    }
}
