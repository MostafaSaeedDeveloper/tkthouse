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
        'is_default',
        'sort_order',
        'config',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'is_default' => 'boolean',
            'config' => 'array',
        ];
    }

    /** The provider-side identifier of this method (legacy: stored in the method's own config). Never includes any key. */
    public function providerMethodIdentifier(): ?string
    {
        $config = is_array($this->config) ? $this->config : [];
        $value = match ($this->provider) {
            'paymob' => $config['integration_id'] ?? null,
            'fawaterak' => $config['provider_key'] ?? null,
            default => null,
        };

        return filled($value) ? (string) $value : null;
    }

    /**
     * Codes of the active methods flagged "default", or null while none is flagged (then nothing is narrowed: every active method is offered, as before).
     * These are what a customer sees when no agent-level selection applies.
     */
    public static function defaultCodes(): ?array
    {
        $codes = static::query()->where('is_active', true)->where('is_default', true)->pluck('code')->all();

        return $codes === [] ? null : $codes;
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
