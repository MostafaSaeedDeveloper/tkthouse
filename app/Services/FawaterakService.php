<?php

namespace App\Services;

use App\Models\Order;
use App\Models\PaymentMethod;
use RuntimeException;
use Illuminate\Support\Facades\Http;

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

        $response = Http::baseUrl((string) config('services.fawaterak.api_url', 'https://app.fawaterk.com/api/v2'))
            ->withHeaders([
                'Authorization' => 'Bearer '.$apiKey,
                'Accept' => 'application/json',
            ])
            ->post('/invoiceInitPay', [
                'invoice_value' => (float) $order->total_amount,
                'payment_method' => $providerKey,
                'customer_name' => trim(($customer->first_name ?? '').' '.($customer->last_name ?? '')) ?: 'Customer',
                'customer_email' => $customer->email,
                'customer_mobile' => $customer->phone,
                'currency' => 'EGP',
                'redirect_url' => route('front.checkout.thank-you', [
                    'flow' => 'payment_success',
                    'order' => $order->order_number,
                ]),
            ])
            ->throw()
            ->json();

        $paymentUrl = (string) (data_get($response, 'data.payment_data.redirectTo')
            ?: data_get($response, 'data.payment_url')
            ?: data_get($response, 'data.url')
            ?: '');

        if ($paymentUrl === '') {
            throw new RuntimeException('Fawaterak did not return a checkout URL.');
        }

        return $paymentUrl;
    }
}

