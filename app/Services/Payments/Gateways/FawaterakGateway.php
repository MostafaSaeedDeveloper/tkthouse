<?php

namespace App\Services\Payments\Gateways;

use App\Models\Order;
use App\Models\PaymentAttempt;
use App\Models\PaymentMethod;
use App\Services\FawaterakService;
use App\Services\Payments\GatewayResult;
use App\Services\Payments\PaymentGateway;
use App\Support\PaymentEnvironment;
use Illuminate\Support\Facades\Log;

/**
 * Fawaterak. Verification uses the official "Get Transaction Data" reference
 * (GET /api/v2/getInvoiceData/{invoice_id}, Bearer token; response fields data.invoice_id, data.total,
 * data.currency, data.paid, data.paid_at — fawaterak-api.readme.io). The docs do not explain the values of
 * `paid` beyond the example, so in production this gateway stays OFF until PAYMENTS_FAWATERAK_VERIFIED=true
 * (set only after a sandbox run), and anything we cannot positively confirm is left pending, never paid.
 */
class FawaterakGateway implements PaymentGateway
{
    public function __construct(private readonly FawaterakService $fawaterak)
    {
    }

    public function provider(): string
    {
        return 'fawaterak';
    }

    public function isConfigured(PaymentMethod $method, ?\App\Models\IntegrationAccount $account = null): bool
    {
        if (! PaymentEnvironment::fawaterakAllowed()) {
            return false;
        }

        if ($account) {
            return $account->provider === 'fawaterak' && filled($account->secret('api_key'))
                && (int) preg_replace('/[^0-9]/', '', (string) $account->field($account->methodField($method->code))) > 0;
        }

        $config = is_array($method->config) ? $method->config : [];

        return filled($config['api_key'] ?? config('services.fawaterak.api_key'))
            && (int) preg_replace('/[^0-9]/', '', (string) ($config['provider_key'] ?? '')) > 0;
    }

    public function initiate(Order $order, PaymentMethod $method, PaymentAttempt $attempt): array
    {
        if (! PaymentEnvironment::fawaterakAllowed()) {
            throw new \RuntimeException('Fawaterak is not enabled in this environment.');
        }

        $data = $this->fawaterak->createCheckoutData($order->loadMissing('customer'), $attempt->integrationAccount, $method);

        // Without the invoice id the payment could never be verified later, so refuse to open it.
        if (blank($data['transaction_id'] ?? null)) {
            throw new \RuntimeException('Fawaterak did not return an invoice id, so the payment cannot be verified.');
        }

        return [
            'url' => (string) ($data['url'] ?? ''),
            'transaction_id' => (string) $data['transaction_id'],
            'reference' => null,
            'provider_order_id' => (string) ($data['invoice_key'] ?? ''),
        ];
    }

    public function inquire(PaymentAttempt $attempt, ?string $hintTransactionId = null): GatewayResult
    {
        if (! PaymentEnvironment::fawaterakAllowed()) {
            return GatewayResult::unknown('Fawaterak is not enabled in this environment.');
        }

        // Only the invoice id stored when the payment was opened counts. A browser-supplied id is never used,
        // so a customer cannot point an order at somebody else's paid invoice.
        $invoiceId = $attempt->transaction_id;
        if (! $invoiceId) {
            return new GatewayResult(GatewayResult::PENDING, message: 'No invoice id stored for this attempt.');
        }

        // Bound attempts are verified with their own account only (see PaymobGateway::inquire).
        $account = $attempt->integration_account_id ? $attempt->integrationAccount : null;
        $method = PaymentMethod::query()->where('code', (string) $attempt->method_code)->where('provider', 'fawaterak')->first();
        if (! $method && ! $account) {
            return GatewayResult::unknown('Fawaterak payment method not found.');
        }

        try {
            $json = $this->fawaterak->inquireInvoice($method, (string) $invoiceId, $account);
        } catch (\Throwable $e) {
            Log::warning('Fawaterak inquiry failed.', ['attempt_id' => $attempt->id, 'error' => $e->getMessage()]);

            return GatewayResult::unknown($e->getMessage());
        }

        if (strtolower((string) data_get($json, 'status')) !== 'success') {
            return new GatewayResult(GatewayResult::UNKNOWN, message: 'Unexpected Fawaterak response status.', raw: (array) $json);
        }

        // The response must describe the very invoice we asked about.
        if ((string) data_get($json, 'data.invoice_id') !== (string) $invoiceId) {
            return new GatewayResult(GatewayResult::UNKNOWN, message: 'Fawaterak returned a different invoice.', raw: (array) $json);
        }

        $paid = in_array(data_get($json, 'data.paid'), [1, '1', true], true) && filled(data_get($json, 'data.paid_at'));
        $total = data_get($json, 'data.total');
        $currency = data_get($json, 'data.currency');

        return new GatewayResult(
            status: $paid ? GatewayResult::PAID : GatewayResult::PENDING,
            transactionId: (string) $invoiceId,
            amount: is_numeric($total) ? (float) $total : null,
            currency: is_string($currency) && $currency !== '' ? $currency : null,
            reference: null,
            raw: $json,
        );
    }
}
