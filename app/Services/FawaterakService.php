<?php

namespace App\Services;

use App\Models\Order;
use App\Models\PaymentMethod;
use Illuminate\Http\Client\ConnectionException;
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

        $config = $method->config ?? [];
        $apiKey = (string) ($config['api_key'] ?? '');
        $providerKey = (string) ($config['provider_key'] ?? '');

        if ($apiKey === '' || $providerKey === '') {
            throw new RuntimeException('Fawaterak method is not fully configured yet. Please set API key and payment method id.');
        }

        $customer = $order->loadMissing('customer')->customer;
        $firstName = trim((string) ($customer->first_name ?? '')) ?: 'Customer';
        $lastName = trim((string) ($customer->last_name ?? '')) ?: '-';
        $phone = trim((string) ($customer->phone ?? '')) ?: '01000000000';
        $email = trim((string) ($customer->email ?? '')) ?: 'customer@example.com';

        $successUrl = route('front.checkout.thank-you', [
            'flow' => 'payment_success',
            'order' => $order->order_number,
        ]);

        $common = [
            'cartTotal' => (float) $order->total_amount,
            'currency' => 'EGP',
            'cartId' => (string) $order->order_number,
            'redirect_url' => $successUrl,
            'return_url' => $successUrl,
        ];

        $providerKeyAsInt = ctype_digit($providerKey) ? (int) $providerKey : $providerKey;

        $payloads = [
            'v2' => $common + [
                'payment_method_id' => $providerKeyAsInt,
                'customer' => [
                    'first_name' => $firstName,
                    'last_name' => $lastName,
                    'email' => $email,
                    'phone' => $phone,
                    'address' => 'NA',
                ],
            ],
            'legacy' => [
                'invoice_value' => (float) $order->total_amount,
                'payment_method' => $providerKey,
                'customer_name' => trim($firstName.' '.$lastName),
                'customer_email' => $email,
                'customer_mobile' => $phone,
                'currency' => 'EGP',
                'redirect_url' => $successUrl,
            ],
        ];

        $baseUrls = array_values(array_unique(array_filter([
            (string) config('services.fawaterak.api_url', 'https://app.fawaterk.com/api/v2'),
            'https://app.fawaterk.com/api/v2',
            'https://app.fawaterak.com/api/v2',
        ])));

        $lastError = 'Unknown error.';

        foreach ($baseUrls as $baseUrl) {
            $http = Http::baseUrl($baseUrl)
                ->withHeaders([
                    'Authorization' => 'Bearer '.$apiKey,
                    'Accept' => 'application/json',
                ]);

            foreach ($payloads as $mode => $payload) {
                try {
                    $resp = $http->post('/invoiceInitPay', $payload);
                } catch (ConnectionException $exception) {
                    $lastError = $exception->getMessage();
                    Log::warning('Fawaterak connection failed.', [
                        'base_url' => $baseUrl,
                        'mode' => $mode,
                        'order_id' => $order->id,
                        'error' => $lastError,
                    ]);
                    continue;
                }

                if (! $resp->successful()) {
                    $status = $resp->status();
                    $body = (string) $resp->body();
                    $lastError = 'HTTP '.$status.': '.$body;

                    Log::warning('Fawaterak checkout init failed.', [
                        'base_url' => $baseUrl,
                        'mode' => $mode,
                        'order_id' => $order->id,
                        'status' => $status,
                        'body' => $body,
                    ]);

                    continue;
                }

                $response = $resp->json();
                $paymentUrl = (string) (data_get($response, 'data.payment_data.redirectTo')
                    ?: data_get($response, 'data.payment_data.redirect_to')
                    ?: data_get($response, 'data.redirectTo')
                    ?: data_get($response, 'data.payment_url')
                    ?: data_get($response, 'data.url')
                    ?: '');

                if ($paymentUrl !== '') {
                    return $paymentUrl;
                }

                $lastError = 'Fawaterak response did not include a checkout URL.';
                Log::warning('Fawaterak response missing checkout URL.', [
                    'base_url' => $baseUrl,
                    'mode' => $mode,
                    'order_id' => $order->id,
                    'response' => $response,
                ]);
            }
        }

        throw new RuntimeException('Unable to initialize Fawaterak payment right now. '.$lastError);
    }
}
