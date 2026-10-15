<?php

namespace App\Services\Payments\Gateways;

use App\Models\Order;
use App\Models\PaymentAttempt;
use App\Models\PaymentMethod;
use App\Services\Payments\GatewayResult;
use App\Services\Payments\PaymentGateway;

/**
 * Local simulator for tests and dev/staging walkthroughs. The "customer" picks the outcome on a
 * simulator page; the state is kept on the attempt and read back through inquire(), exactly like a
 * real gateway would be queried. Disabled in production (see config/payments.php).
 */
class FakeGateway implements PaymentGateway
{
    public function provider(): string
    {
        return 'fake';
    }

    public function initiate(Order $order, PaymentMethod $method, PaymentAttempt $attempt): array
    {
        if (! config('payments.fake.enabled')) {
            throw new \RuntimeException('The fake payment gateway is disabled.');
        }

        $attempt->forceFill([
            'reference' => 'FAKE-'.$order->order_number.'-'.$attempt->id,
            'transaction_id' => 'fake_'.$attempt->id,
            'meta' => array_merge($attempt->meta ?? [], ['fake_state' => 'pending']),
        ])->save();

        return [
            'url' => route('front.payments.fake.show', $attempt),
            'transaction_id' => $attempt->transaction_id,
            'reference' => $attempt->reference,
        ];
    }

    public function inquire(PaymentAttempt $attempt, ?string $hintTransactionId = null): GatewayResult
    {
        if (! config('payments.fake.enabled')) {
            return GatewayResult::unknown('Fake gateway disabled.');
        }

        $state = (string) data_get($attempt->meta, 'fake_state', 'pending');

        return new GatewayResult(
            status: match ($state) {
                'paid' => GatewayResult::PAID,
                'failed' => GatewayResult::FAILED,
                default => GatewayResult::PENDING,
            },
            transactionId: $attempt->transaction_id,
            amount: (float) data_get($attempt->meta, 'fake_amount', $attempt->amount),
            currency: $attempt->currency,
            reference: $attempt->reference,
        );
    }
}
