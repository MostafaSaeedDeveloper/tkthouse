<?php

namespace App\Services;

use App\Models\Order;
use App\Models\PaymentMethod;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

class FawaterakService
{
    public function createCheckoutUrl(Order $order): string
    {
        $method = PaymentMethod::query()
            ->where('code', (string) $order->payment_method)
            ->where('provider', 'fawaterak')
            ->where('is_active', true)
            ->first();

        if (! $method) {
            throw new RuntimeException('Selected Fawaterak payment method is disabled.');
        }

        $config = $this->normalizeConfig($method->config ?? []);
        $apiKey = $this->resolveApiKey($config);
        $providerKey = $this->resolveProviderKey($method, $config);

        if ($apiKey === '' || $providerKey === '') {
            throw new RuntimeException('Fawaterak method is not fully configured yet. Please set API key and payment method id.');
        }

        Log::info('Fawaterak config resolved for payment method.', [
            'payment_method_id' => $method->id,
            'payment_method_code' => $method->code,
            'provider' => $method->provider,
            'api_key_source' => $this->apiKeySource($config),
            'provider_key_source' => $this->providerKeySource($method, $config),
        ]);

        $customer = $order->loadMissing('customer')->customer;
        $firstName = trim((string) ($customer->first_name ?? '')) ?: 'Customer';
        $lastName = trim((string) ($customer->last_name ?? '')) ?: '-';
        $phone = trim((string) ($customer->phone ?? '')) ?: '01000000000';
        $email = trim((string) ($customer->email ?? '')) ?: 'customer@example.com';

        $successUrl = route('front.checkout.thank-you', [
            'flow' => 'payment_success',
            'order' => $order->order_number,
        ]);
        $failUrl = route('front.checkout.thank-you', [
            'flow' => 'payment_failed',
            'order' => $order->order_number,
        ]);

        $payload = [
            'payment_method_id' => ctype_digit($providerKey) ? (int) $providerKey : $providerKey,
            'cartTotal' => (string) $order->total_amount,
            'currency' => 'EGP',
            'cartId' => (string) $order->order_number,
            'cartItems' => [
                [
                    'name' => 'Order #'.$order->order_number,
                    'price' => (string) $order->total_amount,
                    'quantity' => '1',
                ],
            ],
            'customer' => [
                'first_name' => $firstName,
                'last_name' => $lastName,
                'email' => $email,
                'phone' => $phone,
                'address' => 'NA',
            ],
            'redirectionUrls' => [
                'successUrl' => $successUrl,
                'failUrl' => $failUrl,
                'pendingUrl' => $successUrl,
            ],
        ];

        $baseUrl = (string) config('services.fawaterak.api_url', 'https://app.fawaterk.com/api/v2');

        try {
            $resp = Http::baseUrl($baseUrl)
                ->timeout(20)
                ->withToken($apiKey)
                ->withHeaders(['Accept' => 'application/json'])
                ->post('/invoiceInitPay', $payload);
        } catch (ConnectionException $exception) {
            throw new RuntimeException('Unable to connect to Fawaterak gateway. '.$exception->getMessage(), previous: $exception);
        }

        if (! $resp->successful()) {
            $status = $resp->status();
            $body = (string) $resp->body();
            throw new RuntimeException($this->humanizeGatewayError($status, $body));
        }

        $response = $resp->json();
        $paymentUrl = (string) (data_get($response, 'data.payment_data.redirectTo')
            ?: data_get($response, 'data.payment_data.redirect_to')
            ?: data_get($response, 'data.redirectTo')
            ?: data_get($response, 'data.payment_url')
            ?: data_get($response, 'data.url')
            ?: '');

        if ($paymentUrl === '') {
            throw new RuntimeException('Fawaterak did not return a checkout URL.');
        }

        return $paymentUrl;
    }

    private function humanizeGatewayError(int $status, string $body): string
    {
        try {
            $decoded = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
        } catch (Throwable) {
            return 'Unable to initialize Fawaterak payment right now. HTTP '.$status.': '.$body;
        }

        if (($decoded['status'] ?? null) === 'error') {
            $tokenError = data_get($decoded, 'message.token.0');
            if (is_string($tokenError) && str_contains(strtolower($tokenError), 'invalid token')) {
                return 'Unable to initialize Fawaterak payment right now. Fawaterak API Key is invalid or your vendor is inactive.';
            }

            $methodError = data_get($decoded, 'message.payment_method_id.0');
            if (is_string($methodError) && $methodError !== '') {
                return 'Unable to initialize Fawaterak payment right now. Invalid Fawaterak Payment Method ID / Provider Key.';
            }
        }

        return 'Unable to initialize Fawaterak payment right now. HTTP '.$status.': '.$body;
    }

    private function resolveProviderKey(PaymentMethod $method, array $config): string
    {
        $direct = trim((string) ($config['provider_key'] ?? $config['providerKey'] ?? $config['payment_method_id'] ?? ''));
        if ($direct !== '') {
            return $direct;
        }

        $code = strtolower((string) $method->code);
        if (str_contains($code, 'apple')) {
            return trim((string) config('services.fawaterak.provider_apple_pay', config('services.fawaterak.provider_default', '')));
        }

        if (str_contains($code, 'wallet')) {
            return trim((string) config('services.fawaterak.provider_wallet', config('services.fawaterak.provider_default', '')));
        }

        return trim((string) config('services.fawaterak.provider_card', config('services.fawaterak.provider_default', '')));
    }

    private function resolveApiKey(array $config): string
    {
        return trim((string) ($config['api_key'] ?? $config['apiKey'] ?? $config['token'] ?? config('services.fawaterak.api_key', '')));
    }

    private function normalizeConfig(array|string|null $config): array
    {
        if (is_array($config)) {
            return $config;
        }

        if (is_string($config) && trim($config) !== '') {
            $decoded = json_decode($config, true);
            return is_array($decoded) ? $decoded : [];
        }

        return [];
    }

    private function apiKeySource(array $config): string
    {
        if (trim((string) ($config['api_key'] ?? '')) !== '' || trim((string) ($config['apiKey'] ?? '')) !== '' || trim((string) ($config['token'] ?? '')) !== '') {
            return 'payment_method_config';
        }

        return 'services_config';
    }

    private function providerKeySource(PaymentMethod $method, array $config): string
    {
        if (trim((string) ($config['provider_key'] ?? $config['providerKey'] ?? $config['payment_method_id'] ?? '')) !== '') {
            return 'payment_method_config';
        }

        $code = strtolower((string) $method->code);
        if (str_contains($code, 'apple')) {
            return 'services.fawaterak.provider_apple_pay|provider_default';
        }
        if (str_contains($code, 'wallet')) {
            return 'services.fawaterak.provider_wallet|provider_default';
        }

        return 'services.fawaterak.provider_card|provider_default';
    }
}
