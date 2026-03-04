<?php

namespace App\Services;

use App\Models\Order;
use App\Models\PaymentMethod;
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
            throw new RuntimeException('Fawaterak API key is not configured.');
        }

        $providerKey = trim((string) ($config['provider_key'] ?? ''));

        if ($providerKey === '') {
            throw new RuntimeException('Fawaterak payment method ID (provider_key) is not configured.');
        }

        $paymentId = (int) preg_replace('/[^0-9]/', '', $providerKey);

        if ($paymentId === 0) {
            throw new RuntimeException('Fawaterak provider_key must be a numeric payment method ID.');
        }

        $customer  = $order->loadMissing('customer')->customer;
        $firstName = trim((string) ($customer->first_name ?? '')) ?: 'Customer';
        $lastName  = trim((string) ($customer->last_name  ?? '')) ?: '-';
        $phone     = trim((string) ($customer->phone      ?? '')) ?: '01000000000';
        $email     = trim((string) ($customer->email      ?? '')) ?: 'customer@example.com';

        $successUrl = route('front.checkout.thank-you', [
            'flow'  => 'payment_success',
            'order' => $order->order_number,
        ]);

        $failUrl = route('front.checkout.thank-you', [
            'flow'  => 'payment_failed',
            'order' => $order->order_number,
        ]);

        $payload = [
            'payment_method_id' => $paymentId,
            'cartTotal'         => number_format((float) $order->total_amount, 2, '.', ''),
            'currency'          => 'EGP',
            'cartId'            => $order->order_number . '-' . time(),
            'redirectOption'    => true, // forces Fawaterak to return redirectTo for ALL payment methods
            'customer'          => [
                'first_name' => $firstName,
                'last_name'  => $lastName,
                'email'      => $email,
                'phone'      => $phone,
                'address'    => 'NA',
            ],
            'redirectionUrls'   => [
                'successUrl' => $successUrl,
                'failUrl'    => $failUrl,
                'pendingUrl' => $failUrl,
            ],
            'cartItems' => [
                [
                    'name'     => 'Order #' . $order->order_number,
                    'price'    => number_format((float) $order->total_amount, 2, '.', ''),
                    'quantity' => '1',
                ],
            ],
        ];

        $response = Http::baseUrl($this->apiBaseUrl())
            ->timeout(20)
            ->withToken($apiKey)
            ->withHeaders([
                'Accept'       => 'application/json',
                'Content-Type' => 'application/json',
            ])
            ->post('invoiceInitPay', $payload);

        if (! $response->successful()) {
            $raw = (string) $response->body();
            Log::error('Fawaterak invoiceInitPay failed.', [
                'order_id' => $order->id,
                'status'   => $response->status(),
                'response' => $raw,
            ]);
            throw new RuntimeException($raw);
        }

        $json = $response->json();

        if (is_array($json) && strtolower((string) data_get($json, 'status', '')) === 'error') {
            $apiError = $this->extractApiErrorMessage($json);
            Log::error('Fawaterak invoiceInitPay returned error.', [
                'order_id' => $order->id,
                'response' => $json,
            ]);
            throw new RuntimeException($apiError ?: 'Unable to initialize Fawaterak payment.');
        }

        $redirectUrl = trim((string) (
            data_get($json, 'data.payment_data.redirectTo') ?:
            data_get($json, 'data.payment_data.redirect_to') ?:
            data_get($json, 'data.payment_data.redirectUrl') ?:
            data_get($json, 'data.redirectTo') ?:
            data_get($json, 'data.url') ?:
            ''
        ));

        if ($redirectUrl === '') {
            Log::error('Fawaterak checkout URL missing.', [
                'order_id' => $order->id,
                'response' => $json,
            ]);
            throw new RuntimeException('Fawaterak did not return a checkout URL.');
        }

        return $redirectUrl;
    }

    private function extractApiErrorMessage(array $json): string
    {
        $candidates = [
            data_get($json, 'message'),
            data_get($json, 'error'),
            data_get($json, 'data.message'),
            data_get($json, 'data.error'),
        ];

        foreach ($candidates as $candidate) {
            if (is_string($candidate) && trim($candidate) !== '') {
                return trim($candidate);
            }
            if (is_array($candidate)) {
                $flat = [];
                array_walk_recursive($candidate, function ($value) use (&$flat) {
                    if (is_scalar($value) && trim((string) $value) !== '') {
                        $flat[] = trim((string) $value);
                    }
                });
                if ($flat !== []) {
                    return implode(' | ', $flat);
                }
            }
        }

        return '';
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
