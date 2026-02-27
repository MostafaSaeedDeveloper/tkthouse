<?php

namespace App\Services;

use App\Models\Order;
use App\Models\PaymentMethod;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class PaymobService
{
    public function createCheckoutUrl(Order $order): string
    {
        $paymobMethod = PaymentMethod::query()
            ->where('code', 'paymob')
            ->where('provider', 'paymob')
            ->where('is_active', true)
            ->first();

        if (! $paymobMethod) {
            throw new RuntimeException('Paymob payment method is disabled. Please enable it from Payment Methods settings.');
        }

        $config = $paymobMethod->config ?? [];
        $apiKey = (string) ($config['api_key'] ?? '');
        $iframeId = (string) ($config['iframe_id'] ?? '');

        if ($apiKey === '' || $iframeId === '') {
            throw new RuntimeException('Paymob is not fully configured yet. Please complete Paymob settings in Payment Methods page.');
        }

        $integrationId = $this->resolveIntegrationId((string) $order->payment_method, $config);
        $authToken = $this->authenticate($apiKey);
        $paymobOrderId = $this->registerOrder($authToken, $order);
        $paymentKey = $this->createPaymentKey($authToken, $order, $paymobOrderId, $integrationId);

        return 'https://accept.paymob.com/api/acceptance/iframes/'.$iframeId.'?payment_token='.$paymentKey;
    }

    private function resolveIntegrationId(string $method, array $config): string
    {
        if ($method === 'wallet') {
            return (string) ($config['integration_id_wallet'] ?? '');
        }

        return (string) ($config['integration_id_card'] ?? '');
    }

    private function authenticate(string $apiKey): string
    {
        $response = Http::baseUrl('https://accept.paymob.com/api')
            ->post('/auth/tokens', ['api_key' => $apiKey])
            ->throw()
            ->json();

        return (string) ($response['token'] ?? '');
    }

    private function registerOrder(string $token, Order $order): int
    {
        $response = Http::baseUrl('https://accept.paymob.com/api')
            ->post('/ecommerce/orders', [
                'auth_token' => $token,
                'delivery_needed' => false,
                'amount_cents' => (int) round(((float) $order->total_amount) * 100),
                'currency' => 'EGP',
                'merchant_order_id' => $order->order_number,
                'items' => [],
            ])
            ->throw()
            ->json();

        return (int) ($response['id'] ?? 0);
    }

    private function createPaymentKey(string $token, Order $order, int $paymobOrderId, string $integrationId): string
    {
        if ($integrationId === '') {
            throw new RuntimeException('Missing Paymob integration id for this payment method.');
        }

        $customer = $order->customer;

        $response = Http::baseUrl('https://accept.paymob.com/api')
            ->post('/acceptance/payment_keys', [
                'auth_token' => $token,
                'amount_cents' => (int) round(((float) $order->total_amount) * 100),
                'expiration' => 3600,
                'order_id' => $paymobOrderId,
                'currency' => 'EGP',
                'integration_id' => (int) $integrationId,
                'billing_data' => [
                    'first_name' => $customer->name ?: 'Customer',
                    'last_name' => '-',
                    'email' => $customer->email ?: 'customer@example.com',
                    'phone_number' => $customer->phone ?: '01000000000',
                    'apartment' => 'NA',
                    'floor' => 'NA',
                    'street' => 'NA',
                    'building' => 'NA',
                    'shipping_method' => 'NA',
                    'postal_code' => 'NA',
                    'city' => 'Cairo',
                    'country' => 'EG',
                    'state' => 'Cairo',
                ],
            ])
            ->throw()
            ->json();

        return (string) ($response['token'] ?? '');
    }
}
