<?php

namespace App\Services;

use App\Models\Order;
use App\Models\PaymentMethod;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

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

        $config = is_array($method->config) ? $method->config : [];
        $apiKey = trim((string) ($config['api_key'] ?? config('services.fawaterak.api_key', '')));

        if ($apiKey === '') {
            throw new RuntimeException('Fawaterak method is not fully configured yet. Please set API key.');
        }

        $paymentId = $this->resolvePaymentId($method, $apiKey);

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
            'payment_method_id' => (int) $paymentId,
            'cartTotal' => (string) $order->total_amount,
            'currency' => 'EGP',
            'cartId' => (string) $order->order_number,
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
                'pendingUrl' => $failUrl,
            ],
            'cartItems' => [[
                'name' => 'Order #'.$order->order_number,
                'price' => (string) $order->total_amount,
                'quantity' => '1',
            ]],
        ];

        $response = Http::baseUrl($this->apiBaseUrl())
            ->timeout(20)
            ->withToken($apiKey)
            ->withHeaders([
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
            ])
            ->post('invoiceInitPay', $payload);

        if (! $response->successful()) {
            $raw = (string) $response->body();
            Log::error('Fawaterak invoiceInitPay failed.', [
                'order_id' => $order->id,
                'status' => $response->status(),
                'response' => $raw,
            ]);
            throw new RuntimeException($raw);
        }

        $json = $response->json();
        $redirectUrl = (string) data_get($json, 'data.payment_data.redirectTo', '');

        if ($redirectUrl === '') {
            Log::error('Fawaterak checkout URL missing.', [
                'order_id' => $order->id,
                'response' => $json,
            ]);
            throw new RuntimeException('Fawaterak did not return a checkout URL.');
        }

        return $redirectUrl;
    }

    private function resolvePaymentId(PaymentMethod $method, string $apiKey): int
    {
        $paymentMethods = Cache::remember(
            'fawaterak_payment_methods_'.sha1($apiKey),
            now()->addDay(),
            function () use ($apiKey) {
                $base = Http::baseUrl($this->apiBaseUrl())
                    ->timeout(20)
                    ->withToken($apiKey)
                    ->withHeaders([
                        'Accept' => 'application/json',
                        'Content-Type' => 'application/json',
                    ]);

                $response = $base->get('getPaymentmethods');

                if (! $response->successful()) {
                    $raw = (string) $response->body();
                    Log::error('Fawaterak getPaymentmethods failed.', [
                        'status' => $response->status(),
                        'response' => $raw,
                    ]);
                    throw new RuntimeException('Could not fetch Fawaterak payment methods. '.$raw);
                }

                $json = $response->json();
                $methods = data_get($json, 'data', []);
                if (! is_array($methods) || isset($methods['paymentId'])) {
                    $methods = data_get($json, 'data.payment_data', data_get($json, 'payment_methods', $methods));
                }

                if (! is_array($methods) || $methods === []) {
                    Log::error('Fawaterak getPaymentmethods unexpected payload.', ['response' => $json]);
                    throw new RuntimeException('Could not fetch Fawaterak payment methods.');
                }

                return array_values(array_filter($methods, fn ($m) => is_array($m)));
            }
        );

        $configured = trim((string) data_get($method->config, 'provider_key', ''));
        $normalizedConfigured = preg_replace('/^[^0-9]*/', '', $configured ?? '');

        if (is_string($normalizedConfigured) && $normalizedConfigured !== '') {
            foreach ($paymentMethods as $item) {
                $id = (string) data_get($item, 'paymentId', '');
                $name = strtolower((string) data_get($item, 'name', ''));
                if ($id === $normalizedConfigured || $name === strtolower($configured)) {
                    return (int) $id;
                }
            }
        }

        foreach ($paymentMethods as $item) {
            $name = strtolower((string) data_get($item, 'name', ''));
            if (str_contains($name, 'card')) {
                return (int) data_get($item, 'paymentId');
            }
        }

        $firstId = (int) data_get($paymentMethods, '0.paymentId', 0);
        if ($firstId > 0) {
            return $firstId;
        }

        Log::error('Fawaterak payment methods list empty or invalid.', [
            'payment_method_code' => $method->code,
            'payment_methods' => $paymentMethods,
        ]);

        throw new RuntimeException('Could not fetch Fawaterak payment methods.');
    }

    private function apiBaseUrl(): string
    {
        $base = rtrim((string) config('services.fawaterak.api_url', 'https://app.fawaterk.com/api/v2'), '/');

        if (! str_contains(strtolower($base), '/api/v2')) {
            $base .= '/api/v2';
        }

        return $base;
    }
}
