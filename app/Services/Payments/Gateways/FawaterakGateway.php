<?php

namespace App\Services\Payments\Gateways;

use App\Models\Order;
use App\Models\PaymentAttempt;
use App\Models\PaymentMethod;
use App\Services\FawaterakService;
use App\Services\Payments\GatewayResult;
use App\Services\Payments\PaymentGateway;
use Illuminate\Support\Facades\Log;

class FawaterakGateway implements PaymentGateway
{
    public function __construct(private readonly FawaterakService $fawaterak)
    {
    }

    public function provider(): string
    {
        return 'fawaterak';
    }

    public function isConfigured(PaymentMethod $method): bool
    {
        $config = is_array($method->config) ? $method->config : [];

        return filled($config['api_key'] ?? config('services.fawaterak.api_key'))
            && (int) preg_replace('/[^0-9]/', '', (string) ($config['provider_key'] ?? '')) > 0;
    }

    public function initiate(Order $order, PaymentMethod $method, PaymentAttempt $attempt): array
    {
        $data = $this->fawaterak->createCheckoutData($order->loadMissing('customer'));

        return [
            'url' => (string) ($data['url'] ?? ''),
            'transaction_id' => ($data['transaction_id'] ?? null) ?: null,
            'reference' => null,
        ];
    }

    public function inquire(PaymentAttempt $attempt, ?string $hintTransactionId = null): GatewayResult
    {
        // The invoice id we stored when the payment was opened is authoritative; a browser-supplied id is only
        // used when nothing was stored, and the invoice is then checked against the order below.
        $invoiceId = $attempt->transaction_id ?: $hintTransactionId;
        if (! $invoiceId) {
            return new GatewayResult(GatewayResult::PENDING, message: 'No invoice id yet.');
        }

        $method = PaymentMethod::query()->where('code', (string) $attempt->method_code)->where('provider', 'fawaterak')->first();
        if (! $method) {
            return GatewayResult::unknown('Fawaterak payment method not found.');
        }

        try {
            $invoice = $this->fawaterak->inquireInvoice($method, (string) $invoiceId);
        } catch (\Throwable $e) {
            Log::warning('Fawaterak inquiry failed.', ['attempt_id' => $attempt->id, 'error' => $e->getMessage()]);

            return GatewayResult::unknown($e->getMessage());
        }

        $paidRaw = data_get($invoice, (string) config('payments.fawaterak.paid_field'));
        if ($paidRaw === null) {
            return GatewayResult::unknown('Invoice status field not found in the Fawaterak response.');
        }

        $amountRaw = data_get($invoice, (string) config('payments.fawaterak.amount_field'));
        $paid = filter_var($paidRaw, FILTER_VALIDATE_BOOLEAN) || in_array(strtolower((string) $paidRaw), ['paid', 'success', 'completed'], true);

        return new GatewayResult(
            status: $paid ? GatewayResult::PAID : GatewayResult::PENDING,
            transactionId: (string) $invoiceId,
            amount: is_numeric($amountRaw) ? (float) $amountRaw : null,
            currency: 'EGP',
            raw: $invoice,
        );
    }
}
