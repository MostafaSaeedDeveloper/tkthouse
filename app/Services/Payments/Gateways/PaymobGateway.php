<?php

namespace App\Services\Payments\Gateways;

use App\Models\Order;
use App\Models\PaymentAttempt;
use App\Models\PaymentMethod;
use App\Services\Payments\GatewayResult;
use App\Services\Payments\PaymentGateway;
use App\Services\PaymobService;
use Illuminate\Support\Facades\Log;

class PaymobGateway implements PaymentGateway
{
    public function __construct(private readonly PaymobService $paymob)
    {
    }

    public function provider(): string
    {
        return 'paymob';
    }

    public function isConfigured(PaymentMethod $method): bool
    {
        $config = $method->config ?? [];

        return filled($config['api_key'] ?? config('services.paymob.api_key'))
            && filled($config['iframe_id'] ?? config('services.paymob.iframe_id'))
            && filled($config['integration_id'] ?? null);
    }

    public function initiate(Order $order, PaymentMethod $method, PaymentAttempt $attempt): array
    {
        $checkout = $this->paymob->createCheckout($order);

        return ['url' => $checkout['url'], 'transaction_id' => null, 'reference' => $checkout['reference']];
    }

    public function inquire(PaymentAttempt $attempt, ?string $hintTransactionId = null): GatewayResult
    {
        $transactionId = $hintTransactionId ?: $attempt->transaction_id;
        if (! $transactionId) {
            // Nothing to look up yet (customer has not reached the payment step, or the callback is still on its way).
            return new GatewayResult(GatewayResult::PENDING, message: 'No transaction id yet.');
        }

        $method = PaymentMethod::query()->where('code', (string) $attempt->method_code)->where('provider', 'paymob')->first();
        if (! $method) {
            return GatewayResult::unknown('Paymob payment method not found.');
        }

        try {
            $tx = $this->paymob->inquireTransaction($method, (string) $transactionId);
        } catch (\Throwable $e) {
            Log::warning('Paymob inquiry failed.', ['attempt_id' => $attempt->id, 'error' => $e->getMessage()]);

            return GatewayResult::unknown($e->getMessage());
        }

        $merchantOrderId = (string) data_get($tx, 'order.merchant_order_id', '');
        $amount = data_get($tx, 'amount_cents') !== null ? ((float) data_get($tx, 'amount_cents')) / 100 : null;

        // The transaction must belong to this attempt: never settle against someone else's transaction id.
        if ($attempt->reference && $merchantOrderId !== '' && $merchantOrderId !== $attempt->reference) {
            // Not a failure of this attempt: the id came from the browser and points at someone else's payment.
            return GatewayResult::unknown('Transaction belongs to a different order.');
        }

        $success = filter_var(data_get($tx, 'success'), FILTER_VALIDATE_BOOLEAN);
        $pending = filter_var(data_get($tx, 'pending'), FILTER_VALIDATE_BOOLEAN);
        $voided = filter_var(data_get($tx, 'is_voided'), FILTER_VALIDATE_BOOLEAN);
        $refunded = filter_var(data_get($tx, 'is_refunded'), FILTER_VALIDATE_BOOLEAN);

        $status = match (true) {
            $success && ! $voided && ! $refunded => GatewayResult::PAID,
            $pending => GatewayResult::PENDING,
            default => GatewayResult::FAILED,
        };

        return new GatewayResult($status, (string) $transactionId, $amount, (string) data_get($tx, 'currency'), $merchantOrderId ?: null, raw: $tx);
    }
}
