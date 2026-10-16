<?php

namespace App\Integrations;

/** Static description of each integration: which fields exist and which of them are secret. */
final class Definitions
{
    /** @return array<string, array<string, mixed>> */
    public static function all(): array
    {
        $all = self::definitions();
        if (! \App\Support\PaymentEnvironment::fakeGatewayAllowed()) {
            unset($all['fake']); // the simulator provider exists only where the fake gateway may run (never production)
        }

        return $all;
    }

    private static function definitions(): array
    {
        return [
            'fake' => [
                'label' => 'Fake gateway (test only)', 'icon' => 'fa-flask', 'kind' => 'payment',
                'fields' => [['name' => 'supported_methods', 'label' => 'Payment methods this account may take', 'secret' => false, 'type' => 'methods']],
            ],
            'paymob' => [
                'label' => 'Paymob', 'icon' => 'fa-credit-card', 'kind' => 'payment',
                'fields' => [
                    ['name' => 'api_key', 'label' => 'API key', 'secret' => true, 'required' => true],
                    ['name' => 'secret_key', 'label' => 'Secret key', 'secret' => true],
                    ['name' => 'hmac_secret', 'label' => 'HMAC secret (callback signature)', 'secret' => true, 'required' => true],
                    ['name' => 'public_key', 'label' => 'Public key', 'secret' => false],
                    ['name' => 'iframe_id', 'label' => 'Iframe ID', 'secret' => false, 'required' => true],
                    ['name' => 'integration_card', 'label' => 'Card integration ID', 'secret' => false],
                    ['name' => 'integration_wallet', 'label' => 'Wallet integration ID', 'secret' => false],
                    ['name' => 'integration_apple_pay', 'label' => 'Apple Pay integration ID', 'secret' => false],
                    ['name' => 'supported_methods', 'label' => 'Payment methods this account may take', 'secret' => false, 'type' => 'methods'],
                ],
            ],
            'fawaterak' => [
                'label' => 'Fawaterak', 'icon' => 'fa-file-invoice-dollar', 'kind' => 'payment',
                'fields' => [
                    ['name' => 'api_key', 'label' => 'API key (Bearer token)', 'secret' => true, 'required' => true],
                    ['name' => 'vendor_key', 'label' => 'Vendor key (webhook hash verification)', 'secret' => true],
                    ['name' => 'api_url', 'label' => 'API base URL', 'secret' => false, 'default' => 'https://app.fawaterk.com/api/v2'],
                    ['name' => 'provider_card', 'label' => 'Card provider key', 'secret' => false],
                    ['name' => 'provider_wallet', 'label' => 'Wallet provider key', 'secret' => false],
                    ['name' => 'provider_apple_pay', 'label' => 'Apple Pay provider key', 'secret' => false],
                    ['name' => 'supported_methods', 'label' => 'Payment methods this account may take', 'secret' => false, 'type' => 'methods'],
                ],
            ],
            'ultramsg' => [
                'label' => 'UltraMsg / WhatsApp', 'icon' => 'fa-whatsapp', 'kind' => 'messaging',
                'fields' => [
                    ['name' => 'instance_id', 'label' => 'Instance ID', 'secret' => false, 'required' => true],
                    ['name' => 'token', 'label' => 'Token', 'secret' => true, 'required' => true],
                    ['name' => 'base_url', 'label' => 'API base URL', 'secret' => false, 'default' => 'https://api.ultramsg.com'],
                ],
            ],
            'email' => [
                'label' => 'E-mail delivery (SMTP)', 'icon' => 'fa-envelope', 'kind' => 'messaging',
                'fields' => [
                    ['name' => 'host', 'label' => 'SMTP host', 'secret' => false, 'required' => true],
                    ['name' => 'port', 'label' => 'Port', 'secret' => false, 'required' => true, 'default' => '587'],
                    ['name' => 'encryption', 'label' => 'Encryption (tls / ssl / none)', 'secret' => false, 'default' => 'tls'],
                    ['name' => 'username', 'label' => 'Username', 'secret' => false],
                    ['name' => 'password', 'label' => 'Password', 'secret' => true],
                    ['name' => 'from_address', 'label' => 'From address', 'secret' => false, 'required' => true],
                    ['name' => 'from_name', 'label' => 'From name', 'secret' => false],
                ],
            ],
        ];
    }

    public static function get(string $provider): ?array
    {
        return self::all()[$provider] ?? self::definitions()[$provider] ?? null; // archived fake accounts stay readable
    }

    public static function isSecret(string $provider, string $field): bool
    {
        foreach (self::get($provider)['fields'] ?? [] as $f) {
            if ($f['name'] === $field) {
                return (bool) $f['secret'];
            }
        }

        return false;
    }

    /** @return list<string> required field names that are empty */
    public static function missing(string $provider, array $config, array $secrets): array
    {
        $missing = [];
        foreach (self::get($provider)['fields'] ?? [] as $f) {
            if (! empty($f['required']) && blank($f['secret'] ? ($secrets[$f['name']] ?? null) : ($config[$f['name']] ?? null))) {
                $missing[] = $f['name'];
            }
        }

        return $missing;
    }
}
