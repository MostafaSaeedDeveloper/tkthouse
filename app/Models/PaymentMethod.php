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

    public function getCheckoutIconUrlAttribute(): ?string
    {
        $iconPath = trim((string) $this->checkout_icon);
        if ($iconPath === '') {
            return null;
        }

        $iconPath = str_replace('\\', '/', $iconPath);

        if (str_starts_with($iconPath, 'http://') || str_starts_with($iconPath, 'https://')) {
            return $iconPath;
        }

        if (str_starts_with($iconPath, 'payment-method-icons/')) {
            return asset('storage/'.$iconPath);
        }

        if (str_starts_with($iconPath, 'public/')) {
            $iconPath = substr($iconPath, 7);
        }

        return asset(ltrim($iconPath, '/'));
    }
}
