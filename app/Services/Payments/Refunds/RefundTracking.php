<?php

namespace App\Services\Payments\Refunds;

use App\Models\PaymentRefund;

/**
 * The tracking state of a refund, derived ONLY from what is stored in the database (so it is durable and the same whether or not anyone
 * has the tracking modal open). Reading it never changes anything. "completed" is true only for a refund that really reached `succeeded`.
 *
 * States: awaiting_approval → waiting_external → notification_received (unverified, needs a person) → completed
 *         with the side states needs_review and failed. ("Verifying provider status" does not exist: Fawaterak documents no refund
 *         inquiry API, so there is nothing TKT House could verify on its own.)
 */
final class RefundTracking
{
    public const STEPS = ['awaiting_approval' => 'Awaiting approval', 'waiting_external' => 'Waiting for external refund', 'notification_received' => 'Refund notification received', 'completed' => 'Refund completed'];

    public static function state(PaymentRefund $r): string
    {
        return match (true) {
            $r->status === PaymentRefund::SUCCEEDED => 'completed',
            in_array($r->status, [PaymentRefund::FAILED, PaymentRefund::REJECTED], true) => 'failed',
            $r->status === PaymentRefund::UNKNOWN || data_get($r->meta, 'needs_review') => 'needs_review',
            $r->awaiting_approval => 'awaiting_approval',
            filled(data_get($r->meta, 'provider_notified_at')) => 'notification_received',
            default => 'waiting_external',
        };
    }

    public static function label(string $state, PaymentRefund $r): string
    {
        return match ($state) {
            'awaiting_approval' => 'Awaiting approval',
            'waiting_external' => $r->status === PaymentRefund::PROCESSING ? 'Processing externally — waiting for the refund to complete' : 'Approved — waiting for the external refund',
            'notification_received' => 'Refund notification received — needs verification (not completed yet)',
            'completed' => 'Refund completed',
            'needs_review' => 'Refund needs review',
            'failed' => $r->status === PaymentRefund::REJECTED ? 'Refund rejected' : 'Refund failed',
        };
    }

    /** Safe, read-only snapshot for the tracking modal / polling endpoint. Never includes secrets, payloads or other orders' data. */
    public static function snapshot(PaymentRefund $r, bool $includeReferences = true): array
    {
        $r->loadMissing(['order', 'ticketLinks.ticket', 'attempt']);
        $state = self::state($r);
        $refs = FawaterakRefundLinks::references($r);
        $note = (array) data_get($r->meta, 'provider_notification', []);

        return [
            'refund' => $r->internal_reference,
            'order' => \App\Support\OrderPresenter::number($r->order),
            'state' => $state,
            'label' => self::label($state, $r),
            'status' => $r->status,
            'completed' => $r->status === PaymentRefund::SUCCEEDED,   // the ONLY flag the UI may use to show success
            'final' => $r->isFinal(),
            'steps' => self::STEPS,
            'type' => $r->isFull() ? 'Full refund' : 'Partial refund',
            'amount' => $r->amount(), 'currency' => $r->currency,
            'reason' => $r->reason,
            'tickets' => $r->ticketLinks->map(fn ($l) => '#'.$l->ticket?->ticket_number.($l->canceled_at ? ' (canceled)' : ''))->values()->all(),
            'provider_event_at' => data_get($r->meta, 'provider_notified_at'),
            'provider_notification' => $note ? ['status' => $note['status'] ?? null, 'amount' => $note['amount'] ?? null, 'currency' => $note['currency'] ?? null, 'authenticated' => false] : null,
            'last_verified_at' => $r->last_checked_at?->toIso8601String(),
            'verifiable_through_api' => false,
            'invoice_id' => $includeReferences ? $refs['invoice_id'] : null,
            'reference_number' => $includeReferences ? $refs['reference_number'] : null,
            'reference_source' => $refs['source'],
            'dashboard_url' => FawaterakRefundLinks::openUrl($refs['invoice_id'] ?? $refs['reference_number']),
            'direct_link' => FawaterakRefundLinks::transactionUrl($refs['invoice_id'] ?? $refs['reference_number']) !== null,
            'provider_reference' => $includeReferences && $r->status === PaymentRefund::SUCCEEDED ? $r->provider_reference : null,
            'completed_at' => $r->completed_at?->toIso8601String(),
            'effects_status' => $r->effects_status,
        ];
    }
}
