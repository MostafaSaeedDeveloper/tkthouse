<?php

namespace App\Services;

use App\Models\Order;
use App\Models\PaymentMethod;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
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
            throw new RuntimeException('Fawaterak method is not fully configured yet. Please set API key and provider key.');
        }

        $customer = $order->loadMissing('customer')->customer;
        $firstName = trim((string) ($customer->first_name ?? '')) ?: 'Customer';
        $lastName = trim((string) ($customer->last_name ?? '')) ?: '-';
        $phone = trim((string) ($customer->phone ?? '')) ?: '01000000000';
        $email = trim((string) ($customer->email ?? '')) ?: 'customer@example.com';

        $common = [
            'cartTotal' => (float) $order->total_amount,
            'currency' => 'EGP',
            'cartId' => (string) $order->order_number,
            'redirect_url' => route('front.checkout.thank-you', [
                'flow' => 'payment_success',
                'order' => $order->order_number,
            ]),
            'return_url' => route('front.checkout.thank-you', [
                'flow' => 'payment_success',
                'order' => $order->order_number,
            ]),
        ];

        $primaryPayload = $common + [
            'payment_method_id' => $providerKey,
            'customer' => [
                'first_name' => $firstName,
                'last_name' => $lastName,
                'email' => $email,
                'phone' => $phone,
                'address' => 'NA',
            ],
        ];

        $legacyPayload = [
            'invoice_value' => (float) $order->total_amount,
            'payment_method' => $providerKey,
            'customer_name' => trim($firstName.' '.$lastName),
            'customer_email' => $email,
            'customer_mobile' => $phone,
            'currency' => 'EGP',
            'redirect_url' => $common['redirect_url'],
        ];

        $http = Http::baseUrl((string) config('services.fawaterak.api_url', 'https://app.fawaterk.com/api/v2'))
            ->withHeaders([
                'Authorization' => 'Bearer '.$apiKey,
                'Accept' => 'application/json',
            ]);

        try {
            $response = $http->post('/invoiceInitPay', $primaryPayload)->throw()->json();
        } catch (RequestException $exception) {
            if ($exception->response?->status() !== 422) {
                throw $exception;
            }

            $response = $http->post('/invoiceInitPay', $legacyPayload)->throw()->json();
        }

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
}
