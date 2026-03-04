<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class FawaterakPaymentMethodsSeeder extends Seeder
{
    public function run(): void
    {
        $apiKey = (string) config('services.fawaterak.api_key', '');
        $defaultProviderKey = (string) config('services.fawaterak.provider_default', '');

        $definitions = [
            'fawaterak_card' => [
                'name' => 'Fawaterak Card',
                'label' => 'Credit / Debit Card',
                'icon' => 'public/uploads/payment-method-icons/card.webp',
                'description' => 'Pay securely by bank card via Fawaterak.',
                'provider_key' => (string) config('services.fawaterak.provider_card', $defaultProviderKey),
                'is_active' => true,
            ],
            'fawaterak_wallet' => [
                'name' => 'Fawaterak Wallet',
                'label' => 'Mobile Wallet',
                'icon' => 'public/uploads/payment-method-icons/wallet.webp',
                'description' => 'Pay using your mobile wallet via Fawaterak.',
                'provider_key' => (string) config('services.fawaterak.provider_wallet', $defaultProviderKey),
                'is_active' => true,
            ],
            'fawaterak_apple_pay' => [
                'name' => 'Fawaterak Apple Pay',
                'label' => 'Apple Pay',
                'icon' => 'public/uploads/payment-method-icons/apple-pay.webp',
                'description' => 'Pay quickly with Apple Pay via Fawaterak.',
                'provider_key' => (string) config('services.fawaterak.provider_apple_pay', $defaultProviderKey),
                'is_active' => false,
            ],
        ];

        foreach ($definitions as $code => $data) {
            DB::table('payment_methods')->updateOrInsert(
                ['code' => $code],
                [
                    'name' => $data['name'],
                    'checkout_label' => $data['label'],
                    'checkout_icon' => $data['icon'],
                    'checkout_description' => $data['description'],
                    'provider' => 'fawaterak',
                    'is_active' => (bool) ($data['is_active'] ?? true),
                    'config' => json_encode([
                        'api_key' => $apiKey,
                        'provider_key' => $data['provider_key'],
                    ], JSON_UNESCAPED_UNICODE),
                    'updated_at' => now(),
                    'created_at' => now(),
                ]
            );
        }
    }
}
