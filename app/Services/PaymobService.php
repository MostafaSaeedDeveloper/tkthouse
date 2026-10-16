<?php

namespace App\Services;

use App\Models\Order;
use App\Models\PaymentMethod;
use App\Support\PaymentEnvironment;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class PaymobService
{
    public function createCheckoutUrl(Order $order): string
    {
        return $this->createCheckout($order)['url'];
    }

    /** @return array{url: string, reference: string, paymob_order_id: int} */
    public function createCheckout(Order $order): array
    {
        PaymentEnvironment::assertLiveGatewaysAllowed('paymob');

        $paymobMethod = PaymentMethod::query()
            ->where('code', (string) $order->payment_method)
            ->where('provider', 'paymob')
            ->where('is_active', true)
            ->first();

        if (! $paymobMethod) {
            throw new RuntimeException('Selected Paymob payment method is disabled. Please enable it from Payment Methods settings.');
        }

        $config = $paymobMethod->config ?? [];
        $apiKey = (string) ($config['api_key'] ?? config('services.paymob.api_key', ''));
        $iframeId = (string) ($config['iframe_id'] ?? config('services.paymob.iframe_id', ''));
        $integrationId = (string) ($config['integration_id'] ?? '');
        $currency = strtoupper((string) config('services.paymob.currency', 'EGP'));

        if ($apiKey === '' || $iframeId === '' || $integrationId === '') {
            throw new RuntimeException('Paymob method is not fully configured yet. Please complete API key, iframe id and integration id.');
        }

        $authToken = $this->authenticate($apiKey);
        $registered = $this->registerOrder($authToken, $order, $currency);
        $paymentKey = $this->createPaymentKey($authToken, $order, $registered['id'], $integrationId, $currency);

        return [
            'url' => 'https://accept.paymob.com/api/acceptance/iframes/'.$iframeId.'?payment_token='.$paymentKey,
            'reference' => $registered['merchant_order_id'],
            'paymob_order_id' => $registered['id'],
        ];
    }

    /**
     * Server-to-server lookup of a Paymob transaction. This, not the callback body, decides whether an
     * order is paid.
     *
     * @return array<string, mixed>
     */
    public function inquireTransaction(PaymentMethod $method, string $transactionId): array
    {
        PaymentEnvironment::assertLiveGatewaysAllowed('paymob');

        $config = $method->config ?? [];
        $apiKey = (string) ($config['api_key'] ?? config('services.paymob.api_key', ''));

        if ($apiKey === '') {
            throw new RuntimeException('Paymob API key is not configured.');
        }

        $token = $this->authenticate($apiKey);

        return (array) Http::baseUrl('https://accept.paymob.com/api')
            ->withToken($token)
            ->timeout(20)
            ->get('/acceptance/transactions/'.rawurlencode($transactionId))
            ->throw()
            ->json();
    }

    /**
     * HMAC check for the browser return (GET): same fields as the processed callback, but flattened query keys
     * (`order`, `source_data.pan`; PHP turns the dots into underscores).
     */
    public static function hmacIsValidFlat(array $query, string $receivedHmac, string $secret): bool
    {
        $get = fn (string $key) => $query[$key] ?? $query[str_replace('.', '_', $key)] ?? '';

        $obj = [];
        foreach (['amount_cents', 'created_at', 'currency', 'error_occured', 'has_parent_transaction', 'id', 'integration_id', 'is_3d_secure',
            'is_auth', 'is_capture', 'is_refunded', 'is_standalone_payment', 'is_voided', 'owner', 'pending', 'success'] as $key) {
            $obj[$key] = $get($key);
        }
        $obj['order'] = ['id' => $get('order')];
        $obj['source_data'] = ['pan' => $get('source_data.pan'), 'sub_type' => $get('source_data.sub_type'), 'type' => $get('source_data.type')];

        return self::hmacIsValid($obj, $receivedHmac, $secret);
    }

    /** HMAC-SHA512 check of a "transaction processed" callback (https://developers.paymob.com). */
    public static function hmacIsValid(array $obj, string $receivedHmac, string $secret): bool
    {
        if ($secret === '' || $receivedHmac === '') {
            return false;
        }

        $bool = fn ($value) => is_bool($value) ? ($value ? 'true' : 'false') : (string) $value;
        $keys = [
            'amount_cents', 'created_at', 'currency', 'error_occured', 'has_parent_transaction', 'id',
            'integration_id', 'is_3d_secure', 'is_auth', 'is_capture', 'is_refunded', 'is_standalone_payment',
            'is_voided', 'order.id', 'owner', 'pending', 'source_data.pan', 'source_data.sub_type',
            'source_data.type', 'success',
        ];

        $concatenated = '';
        foreach ($keys as $key) {
            $concatenated .= $bool(data_get($obj, $key, ''));
        }

        return hash_equals(hash_hmac('sha512', $concatenated, $secret), strtolower($receivedHmac));
    }

    private function authenticate(string $apiKey): string
    {
        $response = Http::baseUrl('https://accept.paymob.com/api')
            ->post('/auth/tokens', ['api_key' => $apiKey])
            ->throw()
            ->json();

        return (string) ($response['token'] ?? '');
    }

    /** @return array{id: int, merchant_order_id: string} */
    private function registerOrder(string $token, Order $order, string $currency): array
    {
        $payload = [
            'auth_token' => $token,
            'delivery_needed' => false,
            'amount_cents' => (int) round(((float) $order->total_amount) * 100),
            'currency' => $currency,
            'merchant_order_id' => $this->merchantOrderId($order),
            'items' => [],
        ];

        try {
            $response = Http::baseUrl('https://accept.paymob.com/api')
                ->post('/ecommerce/orders', $payload)
                ->throw()
                ->json();
        } catch (RequestException $exception) {
            if ($exception->response?->status() !== 422) {
                throw $exception;
            }

            $payload['merchant_order_id'] = $this->merchantOrderId($order);
            $response = Http::baseUrl('https://accept.paymob.com/api')
                ->post('/ecommerce/orders', $payload)
                ->throw()
                ->json();
        }

        return ['id' => (int) ($response['id'] ?? 0), 'merchant_order_id' => (string) $payload['merchant_order_id']];
    }

    private function merchantOrderId(Order $order): string
    {
        return $order->order_number.'-'.strtolower(bin2hex(random_bytes(4)));
    }

    private function createPaymentKey(string $token, Order $order, int $paymobOrderId, string $integrationId, string $currency): string
    {
        $customer = $order->customer;

        $response = Http::baseUrl('https://accept.paymob.com/api')
            ->post('/acceptance/payment_keys', [
                'auth_token' => $token,
                'amount_cents' => (int) round(((float) $order->total_amount) * 100),
                'expiration' => 3600,
                'order_id' => $paymobOrderId,
                'currency' => $currency,
                'integration_id' => (int) $integrationId,
                'redirection_url' => route('front.paymob.callback'),
                'billing_data' => [
                    'first_name' => $customer->first_name ?: 'Customer',
                    'last_name' => $customer->last_name ?: '-',
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
