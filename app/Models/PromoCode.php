<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class PromoCode extends Model
{
    use HasFactory, LogsActivity;

    protected $fillable = [
        'code',
        'discount_type',
        'discount_value',
        'usage_limit',
        'used_count',
        'is_active',
        'starts_at',
        'ends_at',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'discount_value' => 'decimal:2',
        ];
    }

    /** Who created / changed / deleted a code. used_count is left out: it moves on every order and would flood the log. */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('promo_codes')
            ->logOnly(['code', 'discount_type', 'discount_value', 'usage_limit', 'is_active', 'starts_at', 'ends_at'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->setDescriptionForEvent(fn (string $event) => "Promo code {$this->code} {$event}");
    }
}
