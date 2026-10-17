<?php

namespace App\Services\Payments\Refunds;

use App\Models\PaymentAttempt;
use App\Models\PaymentRefund;

/** Provider refund API. Only implemented where the provider documents one (see docs/v2-payment-capabilities-matrix.md). */
interface RefundGateway
{
    public function provider(): string;

    /** null = this attempt can be refunded through the API; otherwise the (safe) reason it cannot. */
    public function unsupportedReason(PaymentAttempt $attempt): ?string;

    public function supportsVoid(PaymentAttempt $attempt): bool;

    /** Sends the request. Must NOT throw for definitive provider answers; transport failures may throw (the caller treats them as "unknown"). */
    public function refund(PaymentRefund $refund, PaymentAttempt $attempt): RefundOutcome;

    /** Asks the provider (with the attempt's own account) what happened. Only positive evidence changes a refund's state. */
    public function inquire(PaymentRefund $refund, PaymentAttempt $attempt): RefundOutcome;
}
