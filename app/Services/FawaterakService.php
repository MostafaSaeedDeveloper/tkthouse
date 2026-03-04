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

        $configuredProviderKey = trim((string) data_get($method->config, 'provider_key', ''));
        $paymentId = $configuredProviderKey !== '' ? $this->resolvePaymentId($method, $apiKey) : null;

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

        if ($paymentId !== null) {
            $payload['payment_method_id'] = (int) $paymentId;
        }

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
        $redirectUrl = $this->extractRedirectUrl($json);

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

        if ($configured !== '') {
            foreach ($paymentMethods as $item) {
                $id = (string) data_get($item, 'paymentId', '');
                $name = strtolower((string) data_get($item, 'name', ''));
                $providerKey = strtolower(trim((string) (data_get($item, 'providerKey') ?: data_get($item, 'provider_key') ?: data_get($item, 'key') ?: '')));

                if (
                    ($normalizedConfigured !== '' && $id === $normalizedConfigured)
                    || $name === strtolower($configured)
                    || ($providerKey !== '' && $providerKey === strtolower($configured))
                ) {
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


    private function extractRedirectUrl(array $json): string
    {
        $candidates = [
            data_get($json, 'data.payment_data.redirectTo'),
            data_get($json, 'data.payment_data.redirect_to'),
            data_get($json, 'data.payment_data.url'),
            data_get($json, 'data.payment_data.payment_url'),
            data_get($json, 'data.redirectTo'),
            data_get($json, 'data.redirect_to'),
            data_get($json, 'data.url'),
            data_get($json, 'data.payment_url'),
            data_get($json, 'payment_data.redirectTo'),
            data_get($json, 'payment_data.redirect_to'),
            data_get($json, 'payment_data.url'),
            data_get($json, 'payment_data.payment_url'),
            data_get($json, 'payment_data.redirectUrl'),
            data_get($json, 'data.payment_data.redirectUrl'),
            data_get($json, 'payment_url'),
            data_get($json, 'redirectUrl'),
            data_get($json, 'data.redirectUrl'),
            data_get($json, 'invoice_url'),
            data_get($json, 'data.invoice_url'),
            data_get($json, 'url'),
        ];

        foreach ($candidates as $value) {
            $url = $this->normalizePossibleUrl($value);
            if ($url !== '') {
                return $this->canonicalizeCheckoutUrl($url);
            }
        }

        $recursiveUrl = $this->findFirstUrlRecursive($json);
        if ($recursiveUrl !== '') {
            return $recursiveUrl;
        }

        return '';
    }

    private function normalizePossibleUrl(mixed $value): string
    {
        if (is_array($value)) {
            return $this->findFirstUrlRecursive($value);
        }

        $url = trim((string) $value);
        if ($url === '') {
            return '';
        }

        if (str_starts_with($url, 'http://') || str_starts_with($url, 'https://')) {
            return $this->canonicalizeCheckoutUrl($url);
        }

        if (str_starts_with($url, '/')) {
            return $this->canonicalizeCheckoutUrl($this->baseHost().$url);
        }

        if (preg_match('/^www\./i', $url) === 1) {
            return $this->canonicalizeCheckoutUrl('https://'.$url);
        }

        if (preg_match('#^[a-z0-9.-]+\.[a-z]{2,}(/.*)?$#i', $url) === 1) {
            return $this->canonicalizeCheckoutUrl('https://'.$url);
        }

        if (preg_match('#^[a-z0-9][a-z0-9_\-/]*$#i', $url) === 1 && str_contains($url, '/')) {
            return $this->canonicalizeCheckoutUrl($this->baseHost().'/'.ltrim($url, '/'));
        }

        return '';
    }

    private function findFirstUrlRecursive(mixed $data): string
    {
        if (is_array($data)) {
            foreach ($data as $item) {
                $found = $this->findFirstUrlRecursive($item);
                if ($found !== '') {
                    return $found;
                }
            }

            return '';
        }

        $normalized = $this->normalizePossibleUrl($data);
        if ($normalized !== '') {
            return $normalized;
        }

        $text = trim((string) $data);
        if ($text === '') {
            return '';
        }

        if (preg_match('#https?://[^\s"\']+#i', $text, $matches) === 1) {
            return $matches[0];
        }

        return '';
    }


    private function canonicalizeCheckoutUrl(string $url): string
    {
        return trim($url);
    }

    private function baseHost(): string
    {
        $base = $this->apiBaseUrl();
        $parts = parse_url($base);
        if (! is_array($parts) || empty($parts['host'])) {
            return 'https://app.fawaterk.com';
        }

        $scheme = $parts['scheme'] ?? 'https';
        return $scheme.'://'.$parts['host'];
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
