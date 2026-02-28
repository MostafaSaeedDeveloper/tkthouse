<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $legacy = DB::table('payment_methods')->where('code', 'paymob')->first();

        $defaultConfig = [
            'api_key' => '',
            'iframe_id' => '',
            'integration_id' => '',
        ];

        $legacyConfig = [];
        if ($legacy && ! empty($legacy->config)) {
            $decoded = json_decode((string) $legacy->config, true);
            if (is_array($decoded)) {
                $legacyConfig = $decoded;
            }
        }

        $apiKey = (string) ($legacyConfig['api_key'] ?? '');
        $iframeId = (string) ($legacyConfig['iframe_id'] ?? '');

        $targets = [
            'paymob_card' => [
                'name' => 'Paymob Card',
                'integration_id' => (string) ($legacyConfig['integration_id'] ?? $legacyConfig['integration_id_card'] ?? ''),
            ],
            'paymob_wallet' => [
                'name' => 'Paymob Wallet',
                'integration_id' => (string) ($legacyConfig['integration_id_wallet'] ?? ''),
            ],
            'paymob_apple_pay' => [
                'name' => 'Paymob Apple Pay',
                'integration_id' => '',
            ],
        ];

        foreach ($targets as $code => $meta) {
            DB::table('payment_methods')->updateOrInsert(
                ['code' => $code],
                [
                    'name' => $meta['name'],
                    'provider' => 'paymob',
                    'is_active' => false,
                    'config' => json_encode([
                        'api_key' => $apiKey,
                        'iframe_id' => $iframeId,
                        'integration_id' => $meta['integration_id'],
                    ] + $defaultConfig, JSON_UNESCAPED_UNICODE),
                    'updated_at' => now(),
                    'created_at' => now(),
                ]
            );
        }

        if ($legacy) {
            DB::table('payment_methods')->where('code', 'paymob')->update(['is_active' => false, 'updated_at' => now()]);
        }
    }

    public function down(): void
    {
        DB::table('payment_methods')->whereIn('code', ['paymob_card', 'paymob_wallet', 'paymob_apple_pay'])->delete();
    }
};
