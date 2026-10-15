<?php

namespace App\Services\Payments;

use App\Models\Order;
use App\Models\PaymentAttempt;
use App\Models\PaymentMethod;

interface PaymentGateway
{
    public function provider(): string;

    /** Whether the method has everything it needs to take a payment (so unusable methods are never offered). */
    public function isConfigured(PaymentMethod $method): bool;

    /**
     * Opens a payment at the gateway.
     *
     * @return array{url: string, transaction_id: ?string, reference: ?string}
     */
    public function initiate(Order $order, PaymentMethod $method, PaymentAttempt $attempt): array;

    /**
     * Asks the gateway for the real state of the payment. $hintTransactionId comes from the browser/callback
     * and is only a lookup key, never proof of payment.
     */
    public function inquire(PaymentAttempt $attempt, ?string $hintTransactionId = null): GatewayResult;
}
