<?php

namespace App\Services\Wallet;

use App\Support\SystemSettings;
use Illuminate\Support\Facades\Crypt;

/** Apple Wallet credentials as saved in Settings → Apple Wallet (the certificate and its password are stored encrypted). */
final class WalletSettings
{
    /** @return array{p12: ?string, password: string, wwdr: ?string} raw certificate bytes, password and WWDR PEM */
    public static function secrets(): array
    {
        $empty = ['p12' => null, 'password' => '', 'wwdr' => null];
        $stored = SystemSettings::get('wallet_apple_secrets');
        if (! is_string($stored) || $stored === '') {
            return $empty;
        }

        try {
            $data = json_decode(Crypt::decryptString($stored), true, 512, JSON_THROW_ON_ERROR);
        } catch (\Throwable) {
            return $empty; // undecryptable (APP_KEY changed): treated as not configured
        }

        return [
            'p12' => ! empty($data['p12']) ? base64_decode($data['p12']) : null,
            'password' => (string) ($data['password'] ?? ''),
            'wwdr' => $data['wwdr'] ?? null,
        ];
    }

    public static function passTypeId(): ?string
    {
        return SystemSettings::get('wallet_apple_pass_type_id') ?: null;
    }

    public static function teamId(): ?string
    {
        return SystemSettings::get('wallet_apple_team_id') ?: null;
    }

    public static function organizationName(): string
    {
        return (string) (SystemSettings::get('wallet_apple_org_name') ?: SystemSettings::get('site_name', 'Tickets'));
    }

    public static function hasCertificate(): bool
    {
        $s = self::secrets();

        return $s['p12'] !== null && $s['wwdr'] !== null;
    }
}
