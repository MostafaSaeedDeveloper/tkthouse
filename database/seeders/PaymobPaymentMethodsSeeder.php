<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PaymobPaymentMethodsSeeder extends Seeder
{
    public function run(): void
    {
        $apiKey = (string) config('services.paymob.api_key', '');
        $iframeId = (string) config('services.paymob.iframe_id', '');

        $definitions = [
            'paymob_card' => [
                'name' => 'Paymob Card',
                'label' => 'Credit / Debit Card',
                'icon' => 'public/uploads/payment-method-icons/card.webp',
                'description' => 'Secure online payment with Visa / Mastercard.',
                'integration_id' => (string) config('services.paymob.integration_card', ''),
            ],
            'paymob_wallet' => [
                'name' => 'Paymob Wallet',
                'label' => 'Mobile Wallet',
                'icon' => 'public/uploads/payment-method-icons/wallet.webp',
                'description' => 'Pay instantly using your mobile wallet.',
                'integration_id' => (string) config('services.paymob.integration_wallet', ''),
            ],
        ];


        DB::table('payment_methods')
            ->whereIn('code', ['visa', 'wallet'])
            ->delete();

        foreach ($definitions as $code => $data) {
            DB::table('payment_methods')->updateOrInsert(
                ['code' => $code],
                [
                    'name' => $data['name'],
                    'checkout_label' => $data['label'],
                    'checkout_icon' => $data['icon'],
                    'checkout_description' => $data['description'],
                    'provider' => 'paymob',
                    'is_active' => true,
                    'config' => json_encode([
                        'api_key' => $apiKey,
                        'iframe_id' => $iframeId,
                        'integration_id' => $data['integration_id'],
                    ], JSON_UNESCAPED_UNICODE),
                    'updated_at' => now(),
                    'created_at' => now(),
                ]
            );
        }
    }
}
