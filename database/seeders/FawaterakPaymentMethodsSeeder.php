<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Creates the Fawaterak payment METHODS only when they do not exist yet. Safe to run repeatedly and on a live system:
 *  - never overwrites or deletes an existing method (its keys, label, status and order stay as configured in the admin),
 *  - never contains a credential: the API key comes only from the environment (FAWATERAK_API_KEY) and, when absent, the method is
 *    created INACTIVE with no key so an operator must configure it in Settings → Payments → Payment Methods / Providers.
 * Real credentials must never be written in a seeder, a test or a document.
 */
class FawaterakPaymentMethodsSeeder extends Seeder
{
    public function run(): void
    {
        $apiKey = (string) config('services.fawaterak.api_key', '');
        $defaultProviderKey = (string) config('services.fawaterak.provider_default', '');

        $definitions = [
            'fawaterak_card' => ['Fawaterak Card', 'Credit / Debit Card', 'public/uploads/payment-method-icons/card.webp', 'Pay securely by bank card via Fawaterak.', config('services.fawaterak.provider_card', $defaultProviderKey), true],
            'fawaterak_wallet' => ['Fawaterak Wallet', 'Mobile Wallet', 'public/uploads/payment-method-icons/wallet.webp', 'Pay using your mobile wallet via Fawaterak.', config('services.fawaterak.provider_wallet', $defaultProviderKey), true],
            'fawaterak_apple_pay' => ['Fawaterak Apple Pay', 'Apple Pay', 'public/uploads/payment-method-icons/apple-pay.webp', 'Pay quickly with Apple Pay via Fawaterak.', config('services.fawaterak.provider_apple_pay', $defaultProviderKey), false],
        ];

        foreach ($definitions as $code => [$name, $label, $icon, $description, $providerKey, $wantActive]) {
            if (DB::table('payment_methods')->where('code', $code)->exists()) {
                continue; // never touch a configured method
            }
            $configured = $apiKey !== '' && (string) $providerKey !== '';
            DB::table('payment_methods')->insert([
                'code' => $code,
                'name' => $name,
                'checkout_label' => $label,
                'checkout_icon' => $icon,
                'checkout_description' => $description,
                'provider' => 'fawaterak',
                'is_active' => $wantActive && $configured,
                'config' => json_encode(['api_key' => $apiKey, 'provider_key' => (string) $providerKey], JSON_UNESCAPED_UNICODE),
                'updated_at' => now(),
                'created_at' => now(),
            ]);
        }
    }
}
