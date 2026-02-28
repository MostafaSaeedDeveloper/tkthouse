<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PaymentMethod extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'checkout_label',
        'checkout_icon',
        'checkout_description',
        'code',
        'provider',
        'is_active',
        'config',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'config' => 'array',
        ];
    }

    public static function activeCodes(): array
    {
        $codes = static::query()->where('is_active', true)->orderBy('id')->pluck('code')->all();

        return empty($codes) ? ['visa', 'wallet'] : $codes;
    }
}
