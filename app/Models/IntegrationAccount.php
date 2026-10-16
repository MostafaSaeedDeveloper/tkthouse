<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * One set of credentials for one provider (e.g. "Paymob — Events"). Secrets are encrypted at rest, hidden from
 * serialisation, and only ever exposed to the UI as a masked hint.
 */
class IntegrationAccount extends Model
{
    public const UNVERIFIED = 'unverified';
    public const VERIFIED = 'verified';
    public const ERROR = 'error';

    protected $fillable = [
        'provider', 'name', 'environment', 'enabled', 'status', 'last_tested_at', 'last_success_at', 'last_error',
        'config', 'secrets', 'credential_fingerprint', 'source', 'legacy_ref', 'created_by', 'updated_by',
    ];

    protected $hidden = ['secrets'];

    protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
            'config' => 'array',
            'secrets' => 'encrypted:array',
            'last_tested_at' => 'datetime',
            'last_success_at' => 'datetime',
            'archived_at' => 'datetime',
        ];
    }

    /** Method codes this account may take payments for (payment_methods.code), stored as a comma list in config. */
    public function supportedMethods(): array
    {
        return array_values(array_filter(array_map('trim', explode(',', (string) ($this->config['supported_methods'] ?? '')))));
    }

    /** The account field holding the per-method id: Paymob integration_<suffix>, Fawaterak provider_<suffix> (suffix = method code minus provider prefix). */
    public function methodField(string $methodCode): string
    {
        $suffix = \Illuminate\Support\Str::after($methodCode, $this->provider.'_');

        return ($this->provider === 'paymob' ? 'integration_' : 'provider_').$suffix;
    }

    public function isReferenced(): bool
    {
        return \App\Models\PaymentAttempt::query()->where('integration_account_id', $this->id)->exists()
            || \App\Models\Order::withTrashed()->where('integration_account_id', $this->id)->exists()
            || \App\Models\User::query()->where('payment_account_id', $this->id)->exists()
            || \App\Models\CheckoutRouting::query()->where('integration_account_id', $this->id)->exists();
    }

    public function secret(string $field): ?string
    {
        try {
            $value = ($this->secrets ?? [])[$field] ?? null;
        } catch (\Throwable) {
            return null; // undecryptable (APP_KEY changed): treated as missing, never thrown in a request path
        }

        return is_string($value) && $value !== '' ? $value : null;
    }

    public function field(string $field): ?string
    {
        $value = $this->config[$field] ?? null;

        return is_string($value) && $value !== '' ? $value : $this->secret($field);
    }

    /** @return array<string, string> field => "••••1234" (or "••••" for short values); never the real value */
    public function maskedSecrets(): array
    {
        $out = [];
        try {
            $secrets = $this->secrets ?? [];
        } catch (\Throwable) {
            $secrets = [];
        }
        foreach ($secrets as $field => $value) {
            if (! is_string($value) || $value === '') {
                continue;
            }
            $out[$field] = strlen($value) >= 12 ? '••••'.substr($value, -4) : '••••';
        }

        return $out;
    }

    public function isUsable(): bool
    {
        return $this->enabled && $this->archived_at === null && $this->status === self::VERIFIED;
    }

    public static function fingerprint(array $config, array $secrets): string
    {
        ksort($config);
        ksort($secrets);

        return substr(hash_hmac('sha256', json_encode([$config, $secrets]), (string) config('app.key')), 0, 32);
    }
}
