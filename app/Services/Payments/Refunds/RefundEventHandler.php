<?php

namespace App\Services\Payments\Refunds;

use App\Models\PaymentAttempt;
use App\Models\PaymentRefund;
use Illuminate\Support\Facades\DB;
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
        if (! $attempt && $event->provider !== 'fawaterak') {
            $this->webhooks->finish($event, 'needs_review', 'No payment attempt matches this refund notification.');

            return 'needs_review';
        }

        if ($event->provider === 'fawaterak') {
            return $this->fawaterakRefund($event);
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

    /**
     * Fawaterak refund notification (official sample: transactionId, amount, currency, status "approved", reason, approvedAt; NO hash).
     * It can never complete a refund: it is matched strictly, recorded once, and only marks the refund "notification received — needs
     * verification". Money is confirmed by a person with provider evidence (RefundService::confirmSucceeded), because Fawaterak documents no
     * refund inquiry API and "approved" is an approval, not proof that funds were returned.
     */
    private function fawaterakRefund(WebhookEvent $event): string
    {
        $p = (array) $event->payload;
        $tx = trim((string) ($p['transactionId'] ?? ''));
        $amountMinor = PaymentRefund::decimalToMinor((string) ($p['amount'] ?? ''));
        $currency = strtoupper(trim((string) ($p['currency'] ?? '')));
        $status = strtolower(trim((string) ($p['status'] ?? '')));

        $review = function (string $why, ?PaymentRefund $refund = null) use ($event) {
            $this->webhooks->finish($event, 'needs_review', $why);
            $this->notifier()->refundNotificationUnmatched($event, $why, $refund);

            return 'needs_review';
        };

        // 1. shape
        if ($tx === '' || mb_strlen($tx) > 250 || $amountMinor === null || $amountMinor <= 0 || ! preg_match('/^[A-Z]{3}$/', $currency)) {
            return $review('Malformed refund notification (transactionId, amount and a 3-letter currency are required).');
        }

        // 2. the ORIGINAL payment: exact stored id only (never by amount, e-mail or order number)
        $attempts = PaymentAttempt::query()->where('provider', 'fawaterak')->where(fn ($q) => $q->where('transaction_id', $tx)->orWhere('provider_reference', $tx))->get();
        $legacy = PaymentRefund::query()->whereNull('payment_attempt_id')->where('mode', 'manual')->where('meta->original_transaction_ref', $tx)->get();
        if ($attempts->count() > 1 || ($attempts->isNotEmpty() && $legacy->isNotEmpty())) {
            return $review('The transaction id matches more than one payment: ambiguous, not applied.');
        }
        if ($attempts->isEmpty() && $legacy->isEmpty()) {
            return $review('No Fawaterak payment or recorded legacy transaction matches this transactionId.');
        }
        $attempt = $attempts->first();
        $account = $attempt?->integration_account_id;

        // 3. the outstanding manual refund requests of THAT payment only
        $open = PaymentRefund::query()->where('mode', 'manual')->whereIn('status', [PaymentRefund::REQUESTED, PaymentRefund::PROCESSING])
            ->when($attempt, fn ($q) => $q->where('payment_attempt_id', $attempt->id)->where('integration_account_id', $account), fn ($q) => $q->whereIn('id', $legacy->pluck('id')))
            ->get();
        if ($open->isEmpty()) {
            $done = PaymentRefund::query()->where('mode', 'manual')->where('status', PaymentRefund::SUCCEEDED)->where('amount_minor', $amountMinor)
                ->when($attempt, fn ($q) => $q->where('payment_attempt_id', $attempt->id), fn ($q) => $q->whereIn('id', $legacy->pluck('id')))->exists();
            if ($done) {
                $this->webhooks->finish($event, 'ignored', 'This refund is already completed; the notification adds nothing.');

                return 'ignored';
            }

            return $review('There is no outstanding manual refund request for this payment (or it was already resolved).');
        }
        $sameCurrency = $open->filter(fn ($r) => strtoupper((string) $r->currency) === $currency && (! $attempt || strtoupper((string) $attempt->currency) === $currency));
        if ($sameCurrency->isEmpty()) {
            return $review('Currency does not match the refund request.', $open->first());
        }
        $exact = $sameCurrency->filter(fn ($r) => (int) $r->amount_minor === $amountMinor);
        if ($exact->isEmpty()) {
            return $review('Amount does not match any outstanding refund request ('.PaymentRefund::minorToDecimal($amountMinor).' '.$currency.').', $open->first());
        }
        if ($exact->count() > 1) {
            return $review('More than one outstanding refund request has this exact amount: ambiguous, not applied.', $exact->first());
        }
        /** @var PaymentRefund $refund */
        $refund = $exact->first();
        if ($refund->awaiting_approval) {
            return $review('A notification arrived for a refund that is still waiting for approval.', $refund);
        }

        // 4. status: only "approved" is a documented value, and even that is only an approval
        if ($status !== 'approved') {
            return $review('Unrecognised provider status "'.mb_substr($status, 0, 40).'": not treated as a refund.', $refund);
        }

        // 5. record once per delivery (replays and duplicates add nothing); never changes status, tickets or order
        $firstTime = DB::transaction(function () use ($refund, $event, $p, $amountMinor, $currency) {
            $r = PaymentRefund::query()->lockForUpdate()->findOrFail($refund->id);
            if ($r->isFinal()) {
                return null; // a final state is never touched by a late signal
            }
            $meta = (array) $r->meta;
            $seen = (array) ($meta['provider_notification_events'] ?? []);
            if (in_array($event->id, $seen, true)) {
                return false;
            }
            $first = blank($meta['provider_notified_at'] ?? null);
            $meta['provider_notification_events'] = array_slice(array_merge($seen, [$event->id]), -20);
            $meta['provider_notified_at'] = now()->toIso8601String();
            $meta['provider_reported_approval'] = true;
            $meta['provider_notification'] = ['event_id' => $event->id, 'status' => 'approved', 'amount' => PaymentRefund::minorToDecimal($amountMinor), 'currency' => $currency,
                'approved_at' => isset($p['approvedAt']) ? mb_substr((string) $p['approvedAt'], 0, 40) : null, 'authenticated' => false, 'verification' => 'needs_verification'];
            $meta['timeline'][] = ['at' => now()->toIso8601String(), 'event' => 'provider_notification_received', 'source' => 'webhook',
                'note' => 'Fawaterak sent an unsigned refund notification ("approved", '.PaymentRefund::minorToDecimal($amountMinor).' '.$currency.'). It is NOT proof that the money was returned: verify in the Fawaterak dashboard, then confirm with the reference and date.'];
            $r->forceFill(['meta' => $meta])->save();

            return $first;
        });

        if ($firstTime === null) {
            $this->webhooks->finish($event, 'ignored', 'The refund is already final.');

            return 'ignored';
        }
        $this->webhooks->finish($event, 'processed', 'Recorded as an unverified notification; staff confirmation with provider evidence required.');
        if ($firstTime === true) {
            $this->notifier()->refundNotificationReceived($refund->fresh());
        }

        return 'hint_recorded';
    }

    private function notifier(): \App\Services\Notifications\AdminNotifier
    {
        return app(\App\Services\Notifications\AdminNotifier::class);
    }
}
