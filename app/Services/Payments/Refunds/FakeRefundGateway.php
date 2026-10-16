<?php

namespace App\Services\Payments\Refunds;

use App\Models\PaymentAttempt;
use App\Models\PaymentRefund;
use App\Support\PaymentEnvironment;

/**
 * Simulator for tests and Fake-gateway journeys (never in production). The outcome is chosen on the attempt's meta
 * (fake_refund: accept|reject|timeout|instant) and the later "inquiry" answers from refund meta (fake_refund_state).
 */
class FakeRefundGateway implements RefundGateway
{
    public function provider(): string
    {
        return 'fake';
    }

    public function unsupportedReason(PaymentAttempt $attempt): ?string
    {
        if (! PaymentEnvironment::fakeGatewayAllowed()) {
            return 'The fake gateway is disabled in this environment.';
        }
        $account = $attempt->integrationAccount;
        if ($account && $account->refund_enabled_at === null) {
            return 'Refunds are not yet confirmed for this merchant account.';
        }

        return null;
    }

    public function supportsVoid(PaymentAttempt $attempt): bool
    {
        return false;
    }

    public function refund(PaymentRefund $refund, PaymentAttempt $attempt): RefundOutcome
    {
        return match ((string) data_get($attempt->meta, 'fake_refund', 'accept')) {
            'timeout' => throw new \RuntimeException('simulated timeout after sending'),
            'reject' => new RefundOutcome(RefundOutcome::REJECTED, null, 'Fake gateway refused the refund.'),
            'instant' => new RefundOutcome(RefundOutcome::SUCCEEDED, 'FR-'.$refund->id, 'Fake gateway completed the refund.'),
            default => new RefundOutcome(RefundOutcome::ACCEPTED, 'FR-'.$refund->id, 'Fake gateway accepted the refund.'),
        };
    }

    public function inquire(PaymentRefund $refund, PaymentAttempt $attempt): RefundOutcome
    {
        return match ((string) data_get($refund->meta, 'fake_refund_state', 'pending')) {
            'succeeded' => new RefundOutcome(RefundOutcome::SUCCEEDED, $refund->provider_refund_id, 'Fake gateway: refund completed.', ['source' => 'fake']),
            'failed' => new RefundOutcome(RefundOutcome::FAILED, $refund->provider_refund_id, 'Fake gateway: refund failed.', ['source' => 'fake']),
            'unknown' => new RefundOutcome(RefundOutcome::UNKNOWN, null, 'Fake gateway: unknown.'),
            default => new RefundOutcome(RefundOutcome::PENDING, $refund->provider_refund_id, 'Fake gateway: still processing.'),
        };
    }
}
