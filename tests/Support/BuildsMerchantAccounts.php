<?php

namespace Tests\Support;

use App\Models\IntegrationAccount;
use App\Models\PaymentAttempt;
use Illuminate\Support\Facades\Http;

/** Mock merchant accounts + a stateful Paymob/Fawaterak double. No real gateway is ever contacted. */
trait BuildsMerchantAccounts
{
    /** @var array<string, array<string, mixed>> paymob transactions keyed by id; owner = the API key that may read them */
    protected array $paymobTx = [];
    /** @var array<string, array<string, mixed>> fawaterak invoices keyed by id; owner = bearer key */
    protected array $fawInvoices = [];
    protected array $httpLog = [];
    /** accept | reject | server_error | timeout */
    protected string $paymobRefundMode = 'accept';
    protected int $paymobRefundSeq = 9000;

    protected function account(string $provider, string $name, array $config = [], array $secrets = [], array $o = []): IntegrationAccount
    {
        $defaults = match ($provider) {
            'paymob' => [['iframe_id' => 'IF-'.$name, 'integration_card' => (string) $this->numericId($name), 'integration_wallet' => (string) ($this->numericId($name) + 1), 'supported_methods' => 'paymob_card,paymob_wallet'], ['api_key' => 'KEY-'.$name, 'hmac_secret' => 'HMAC-'.$name, 'secret_key' => 'SECRET-'.$name]],
            'fawaterak' => [['provider_card' => '27416', 'supported_methods' => 'fawaterak_card'], ['api_key' => 'FKEY-'.$name]],
            default => [['supported_methods' => 'fake_card,fake_wallet'], []],
        };
        $config = $config + $defaults[0];
        $secrets = $secrets + $defaults[1];

        return IntegrationAccount::create($o + [
            'provider' => $provider, 'name' => $name, 'environment' => 'sandbox', 'enabled' => true, 'status' => 'verified',
            'config' => $config, 'secrets' => $secrets, 'credential_fingerprint' => IntegrationAccount::fingerprint($config, $secrets),
        ]);
    }

    protected function numericId(string $name): int
    {
        return 1000 + (crc32($name) % 8000);
    }

    protected function fakeProviders(): void
    {
        config(['payments.live_gateways.enabled' => true, 'payments.fawaterak.verified' => false]);

        Http::fake(function ($req) {
            $url = $req->url();
            $this->httpLog[] = ['url' => $url, 'method' => $req->method(), 'key' => $req['api_key'] ?? null, 'bearer' => $req->header('Authorization')[0] ?? null, 'data' => $req->data()];

            // ---- Paymob
            if (str_ends_with($url, '/auth/tokens')) {
                return Http::response(['token' => 'tok-'.$req['api_key']], 201);
            }
            if (str_contains($url, '/ecommerce/orders')) {
                return Http::response(['id' => random_int(1000, 999999), 'merchant_order_id' => $req['merchant_order_id']], 201);
            }
            if (str_contains($url, '/acceptance/payment_keys')) {
                return Http::response(['token' => 'pk-'.$req['integration_id'].'-'.$req['auth_token']], 201);
            }
            if (preg_match('#/acceptance/void_refund/(refund|void)$#', $url, $m)) {
                $this->httpLog[count($this->httpLog) - 1]['refund_call'] = ['kind' => $m[1], 'authorization' => $req->header('Authorization')[0] ?? null, 'body' => $req->data()];
                if ($this->paymobRefundMode === 'timeout') {
                    throw new \Illuminate\Http\Client\ConnectionException('cURL error 28: timed out');
                }
                if ($this->paymobRefundMode === 'reject') {
                    return Http::response(['message' => 'Amount exceeds the transaction amount'], 422);
                }
                if ($this->paymobRefundMode === 'server_error') {
                    return Http::response('upstream error', 502);
                }
                $parent = $this->paymobTx[(string) $req['transaction_id']] ?? null;
                $id = ++$this->paymobRefundSeq;
                $token = str_replace('Token ', '', $req->header('Authorization')[0] ?? '');
                $this->paymobTx[(string) $id] = ['id' => $id, 'owner' => $parent['owner'] ?? $token, 'merchant_order_id' => $parent['merchant_order_id'] ?? '', 'amount_cents' => $req['amount_cents'] ?? ($parent['amount_cents'] ?? 0),
                    'success' => true, 'pending' => $this->paymobRefundMode === 'pending', 'has_parent_transaction' => true, 'is_voided' => false, 'is_refunded' => false];
                if ($m[1] === 'void' && $parent) {
                    $this->paymobTx[(string) $req['transaction_id']]['is_voided'] = true;
                }

                return Http::response(['id' => $id, 'success' => true], 200);
            }
            if (preg_match('#/acceptance/transactions/([^/?]+)#', $url, $m)) {
                $tx = $this->paymobTx[urldecode($m[1])] ?? null;
                $bearer = str_replace('Bearer ', '', $req->header('Authorization')[0] ?? '');
                if (! $tx || 'tok-'.$tx['owner'] !== $bearer) {
                    return Http::response(['detail' => 'Not found'], 404); // another merchant's transaction is invisible to this key
                }

                return Http::response([
                    'id' => $tx['id'], 'success' => $tx['success'] ?? true, 'pending' => $tx['pending'] ?? false, 'is_voided' => $tx['is_voided'] ?? false, 'is_refunded' => $tx['is_refunded'] ?? false, 'has_parent_transaction' => $tx['has_parent_transaction'] ?? false,
                    'amount_cents' => $tx['amount_cents'], 'currency' => 'EGP', 'order' => ['merchant_order_id' => $tx['merchant_order_id']],
                ]);
            }

            // ---- Fawaterak
            if (str_ends_with($url, '/invoiceInitPay')) {
                $id = 'INV-'.random_int(10000, 99999);
                $this->fawInvoices[$id] = ['owner' => str_replace('Bearer ', '', $req->header('Authorization')[0] ?? ''), 'paid' => false, 'total' => (float) $req['cartTotal']];

                return Http::response(['status' => 'success', 'data' => ['invoice_id' => $id, 'payment_data' => ['redirectTo' => 'https://pay.example.test/'.$id]]]);
            }
            if (preg_match('#/getInvoiceData/([^/?]+)#', $url, $m)) {
                $inv = $this->fawInvoices[urldecode($m[1])] ?? null;
                $bearer = str_replace('Bearer ', '', $req->header('Authorization')[0] ?? '');
                if (! $inv || $inv['owner'] !== $bearer) {
                    return Http::response(['status' => 'error'], 404);
                }

                return Http::response(['status' => 'success', 'data' => ['invoice_id' => urldecode($m[1]), 'total' => $inv['total'], 'currency' => 'EGP', 'paid' => $inv['paid'] ? 1 : 0, 'paid_at' => $inv['paid'] ? now()->toDateTimeString() : null]]);
            }

            return Http::response([], 404);
        });
    }

    /** Registers a successful Paymob transaction for an attempt, owned by the given API key. */
    protected function paymobPays(PaymentAttempt $attempt, string $ownerKey, string $txId, array $o = []): void
    {
        $this->paymobTx[$txId] = $o + ['id' => $txId, 'owner' => $ownerKey, 'merchant_order_id' => $attempt->reference, 'amount_cents' => (int) round($attempt->amount * 100), 'success' => true];
    }

    protected function paymobSign(array $obj, string $secret): string
    {
        $keys = ['amount_cents', 'created_at', 'currency', 'error_occured', 'has_parent_transaction', 'id', 'integration_id', 'is_3d_secure', 'is_auth', 'is_capture', 'is_refunded', 'is_standalone_payment', 'is_voided', 'order.id', 'owner', 'pending', 'source_data.pan', 'source_data.sub_type', 'source_data.type', 'success'];
        $b = fn ($v) => is_bool($v) ? ($v ? 'true' : 'false') : (string) $v;

        return hash_hmac('sha512', collect($keys)->map(fn ($k) => $b(data_get($obj, $k, '')))->implode(''), $secret);
    }

    protected function paymobCallbackObj(PaymentAttempt $attempt, string $txId): array
    {
        return ['id' => $txId, 'success' => true, 'pending' => false, 'amount_cents' => (int) round($attempt->amount * 100), 'currency' => 'EGP', 'is_voided' => false, 'is_refunded' => false,
            'order' => ['id' => 55, 'merchant_order_id' => $attempt->reference]];
    }
}
