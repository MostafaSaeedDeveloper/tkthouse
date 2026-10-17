<?php

namespace App\Services\Payments\Refunds;

use App\Models\PaymentRefund;
use App\Models\WebhookEvent;
use App\Services\Payments\Webhooks\WebhookService;

/**
 * Refund / void notifications. A notification is never trusted for its content:
 *  - Paymob: only an authentic (HMAC-valid) event is used, and only as a trigger for an inquiry with the refund's own account.
 *  - Fawaterak: the documented refund webhook carries no hash and "approved" is an approval, not a completed money movement; it is
 *    recorded as a HINT on the manual refund and a person confirms completion.
 */
class RefundEventHandler
{
    public function __construct(private readonly RefundService $refunds, private readonly WebhookService $webhooks)
    {
    }

    public function handle(WebhookEvent $event): string
    {
        $attempt = $event->attempt;
        if (! $attempt) {
            $this->webhooks->finish($event, 'needs_review', 'No payment attempt matches this refund notification.');

            return 'needs_review';
        }

        if ($event->provider === 'fawaterak') {
            $refund = PaymentRefund::query()->where('payment_attempt_id', $attempt->id)->where('mode', 'manual')->whereIn('status', [PaymentRefund::REQUESTED, PaymentRefund::PROCESSING])->latest('id')->first();
            if (! $refund) {
                $this->webhooks->finish($event, 'needs_review', 'Fawaterak reported a refund, but no manual refund is recorded for this payment.');

                return 'needs_review';
            }
            $meta = (array) $refund->meta;
            $meta['timeline'][] = ['at' => now()->toIso8601String(), 'event' => 'provider_reported_approval', 'source' => 'webhook', 'note' => 'Fawaterak sent a refund notification (status "approved", unauthenticated). Confirm completion with the provider reference.'];
            $meta['provider_reported_approval'] = true;
            $refund->forceFill(['meta' => $meta])->save();
            $this->webhooks->finish($event, 'processed', 'Recorded as an unverified hint; staff confirmation required.');

            return 'hint_recorded';
        }

        if ($event->signature_status !== 'valid') {
            $this->webhooks->finish($event, 'needs_review', 'Refund notification without a valid signature is not acted on.');

            return 'needs_review';
        }

        $ref = (string) $event->transaction_reference;
        $refunds = PaymentRefund::query()->where('payment_attempt_id', $attempt->id)->whereIn('status', [PaymentRefund::PROCESSING, PaymentRefund::UNKNOWN])->where('mode', 'api')->get();
        $matched = $refunds->filter(fn ($r) => (string) $r->provider_refund_id === $ref || $ref === (string) $attempt->transaction_id);
        if ($matched->isEmpty()) {
            $this->webhooks->finish($event, 'needs_review', 'No refund of ours is waiting for this notification.');

            return 'needs_review';
        }
        foreach ($matched as $r) {
            $this->refunds->inquire($r);
        }
        $this->webhooks->finish($event, 'processed');

        return 'inquired';
    }
}
