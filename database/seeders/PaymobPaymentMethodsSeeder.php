<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Creates the Paymob payment METHODS only when they do not exist yet (see FawaterakPaymentMethodsSeeder for the rules):
 * never overwrites or deletes anything, never contains credentials, created inactive when the environment is not configured.
 * Legacy method codes (visa / wallet / card) are NOT removed: existing orders and payment attempts reference them.
 */
class PaymobPaymentMethodsSeeder extends Seeder
{
    public function run(): void
    {
        $apiKey = (string) config('services.paymob.api_key', '');
        $iframeId = (string) config('services.paymob.iframe_id', '');

        $definitions = [
            'paymob_card' => ['Paymob Card', 'Credit / Debit Card', 'public/uploads/payment-method-icons/card.webp', 'Secure online payment with Visa / Mastercard.', (string) config('services.paymob.integration_card', ''), true],
            'paymob_wallet' => ['Paymob Wallet', 'Mobile Wallet', 'public/uploads/payment-method-icons/wallet.webp', 'Pay instantly using your mobile wallet.', (string) config('services.paymob.integration_wallet', ''), true],
            'paymob_apple_pay' => ['Paymob Apple Pay', 'Apple Pay', 'public/uploads/payment-method-icons/apple-pay.webp', 'Pay quickly with Apple Pay.', (string) config('services.paymob.integration_apple_pay', ''), false],
        ];

        foreach ($definitions as $code => [$name, $label, $icon, $description, $integrationId, $wantActive]) {
            if (DB::table('payment_methods')->where('code', $code)->exists()) {
                continue;
            }
            $configured = $apiKey !== '' && $iframeId !== '' && $integrationId !== '';
            DB::table('payment_methods')->insert([
                'code' => $code,
                'name' => $name,
                'checkout_label' => $label,
                'checkout_icon' => $icon,
                'checkout_description' => $description,
                'provider' => 'paymob',
                'is_active' => $wantActive && $configured,
                'config' => json_encode(['api_key' => $apiKey, 'iframe_id' => $iframeId, 'integration_id' => $integrationId], JSON_UNESCAPED_UNICODE),
                'updated_at' => now(),
                'created_at' => now(),
            ]);
        }
    }
}
