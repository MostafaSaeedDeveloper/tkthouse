<?php

namespace App\Services\Payments\Webhooks;

use App\Models\IntegrationAccount;
use App\Models\PaymentAttempt;
use App\Models\WebhookEvent;
use Illuminate\Database\UniqueConstraintViolationException;

/**
 * Provider notifications: authenticate -> record (idempotent) -> hand to settlement. A webhook body is never proof of payment:
 * "paid" is only ever decided by PaymentService::settle() after a server-to-server inquiry with the attempt's own account.
 */
class WebhookService
{
    public const PAYMENT_TYPES = ['payment.succeeded', 'payment.pending', 'payment.failed', 'payment.cancelled'];

    /** Fields we keep. Card numbers, tokens, secrets and free-text customer data are never stored. */
    private const SAFE_KEYS = [
        'id', 'success', 'pending', 'amount_cents', 'currency', 'is_refunded', 'is_voided', 'has_parent_transaction', 'integration_id',
        'created_at', 'error_occured', 'order.id', 'order.merchant_order_id', 'parent_transaction', 'refunded_amount_cents',
        'invoice_id', 'invoice_key', 'invoice_status', 'payment_method', 'referenceNumber', 'amount', 'paidCurrency', 'errorMessage',
        'referenceId', 'status', 'paymentMethod', 'transactionId', 'transactionKey', 'reason', 'approvedAt',
    ];

    /** @return array{0: WebhookEvent, 1: bool} the event and whether this delivery was a duplicate */
    public function record(
        string $provider, string $type, array $dedupeParts, ?string $transactionReference, string $signature, array $rawPayload,
        ?IntegrationAccount $account = null, ?PaymentAttempt $attempt = null,
    ): array {
        $key = hash('sha256', $provider.'|'.$type.'|'.implode('|', array_map('strval', $dedupeParts)));
        $safe = [];
        foreach (self::SAFE_KEYS as $k) {
            if (($v = data_get($rawPayload, $k)) !== null && ! is_array($v)) {
                $safe[$k] = is_string($v) ? mb_substr($v, 0, 300) : $v;
            }
        }

        try {
            $event = WebhookEvent::create([
                'provider' => $provider, 'integration_account_id' => $account?->id, 'payment_attempt_id' => $attempt?->id, 'order_id' => $attempt?->order_id,
                'event_type' => $type, 'dedupe_key' => $key, 'transaction_reference' => $transactionReference ? mb_substr($transactionReference, 0, 250) : null,
                'signature_status' => $signature, 'processing_status' => 'received', 'payload' => $safe, 'received_at' => now(),
            ]);

            return [$event, false];
        } catch (UniqueConstraintViolationException) {
            $event = WebhookEvent::query()->where('provider', $provider)->where('dedupe_key', $key)->firstOrFail();
            $event->increment('deliveries');
            // A previously unauthenticated delivery can be upgraded by a later authentic one (never the other way round).
            if ($signature === 'valid' && $event->signature_status !== 'valid') {
                $event->update(['signature_status' => 'valid']);
            }

            return [$event->fresh(), true];
        }
    }

    public function finish(WebhookEvent $event, string $status, ?string $error = null): void
    {
        $event->forceFill([
            'processing_status' => $status, 'error' => $error ? mb_substr($error, 0, 300) : null,
            'processed_at' => now(), 'process_attempts' => $event->process_attempts + 1,
        ])->save();
    }

    /** Re-run processing of a stored event (admin "Retry"). Safe: settlement is idempotent and always re-asks the gateway. */
    public function retry(WebhookEvent $event): string
    {
        if ($event->signature_status === 'invalid') {
            throw new \DomainException('Events with an invalid signature are never processed.');
        }
        $attempt = $event->attempt;
        if (! $attempt) {
            $this->finish($event, 'needs_review', 'No payment attempt matches this event.');

            return 'needs_review';
        }

        if (in_array($event->event_type, self::PAYMENT_TYPES, true)) {
            try {
                $outcome = app(\App\Services\Payments\PaymentService::class)->refreshAttempt($attempt->fresh(), $event->event_type === 'payment.succeeded' ? $event->transaction_reference : null);
                $this->finish($event, 'processed', null);
                $event->forceFill(['error' => null])->save();

                return $outcome;
            } catch (\Throwable $e) {
                $this->finish($event, 'failed', 'Gateway check failed: '.class_basename($e));

                return 'failed';
            }
        }

        // Refund / void events are handled by the refund module (Phase 4).
        if (class_exists(\App\Services\Payments\Refunds\RefundEventHandler::class)) {
            return app(\App\Services\Payments\Refunds\RefundEventHandler::class)->handle($event);
        }
        $this->finish($event, 'ignored', 'Refund events are not processed yet.');

        return 'ignored';
    }
}
