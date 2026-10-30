<?php

namespace App\Services\Payments\Refunds;

use App\Mail\RefundCustomerMail;
use App\Models\Order;
use App\Models\PaymentRefund;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * The ONE customer e-mail about a refund: "completed". Nothing is sent for a request, an approval, processing, a provider notification or
 * an unverified refund. It is sent only when ALL of these are true (re-checked under a row lock):
 *   - the refund is `succeeded` (verified: audited confirmation or trusted provider evidence),
 *   - its ticket effects are done (`applied`, or `not_applicable` for a money-only refund) - so the tickets it covers are really canceled,
 *   - the order status already follows it (`refunded` / `partially_refunded`).
 * Further guarantees: dispatched only AFTER the commit (callers use DB::afterCommit), synchronous (no queue); a failure is logged/recorded and can
 * never undo anything; exactly one per refund (row-locked claim in the refund meta, so webhooks/repeated confirmations/retries never duplicate).
 */
class RefundCustomerNotifier
{
    private const STALE_CLAIM_MINUTES = 10;
    private const MAX_ATTEMPTS = 3;

    public function completed(PaymentRefund $r): void
    {
        $this->send($r->id, 'completed');
    }

    /** Manual re-send after a failure (never re-sends something already delivered). */
    public function retry(PaymentRefund $r, string $kind): void
    {
        $this->send($r->id, $kind);
    }

    private function eligible(PaymentRefund $r, string $kind): bool
    {
        if ($kind !== 'completed' || $r->status !== PaymentRefund::SUCCEEDED) {
            return false;
        }
        $ticketsDone = in_array($r->effects_status, [PaymentRefund::EFFECTS_APPLIED, PaymentRefund::EFFECTS_NONE], true);
        $orderStatus = Order::query()->withTrashed()->whereKey($r->order_id)->value('status');

        return $ticketsDone && in_array($orderStatus, Order::REFUND_STATUSES, true);
    }

    private function send(int $id, string $kind): void
    {
        try {
            $refund = $this->claim($id, $kind);
            if (! $refund) {
                return;
            }
            $email = trim((string) $refund->order?->customer?->email);
            if ($email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $this->record($id, $kind, ['skipped' => 'no_valid_recipient']);

                return;
            }

            Mail::to($email)->send(new RefundCustomerMail($refund, $kind));

            $this->record($id, $kind, ['sent_at' => now()->toIso8601String(), 'failed_at' => null, 'error' => null], 'sent');
        } catch (\Throwable $e) {
            report($e);
            Log::error('Refund customer e-mail failed.', ['refund_id' => $id, 'kind' => $kind, 'error' => class_basename($e)]);
            try {
                $this->record($id, $kind, ['failed_at' => now()->toIso8601String(), 'error' => class_basename($e)], 'failed');
            } catch (\Throwable) {
                // recording is best effort too
            }
        }
    }

    /** Takes the right to send this kind exactly once (or again after a failure / a stale claim). Returns the fresh refund, or null. */
    private function claim(int $id, string $kind): ?PaymentRefund
    {
        return DB::transaction(function () use ($id, $kind) {
            $r = PaymentRefund::query()->lockForUpdate()->find($id);
            if (! $r || ! $this->eligible($r, $kind)) {
                return null;
            }
            $meta = (array) $r->meta;
            $e = (array) ($meta['customer_emails'][$kind] ?? []);
            if (! empty($e['sent_at']) || ! empty($e['skipped'])) {
                return null;
            }
            $busy = ! empty($e['claimed_at']) && empty($e['failed_at']) && now()->diffInMinutes(\Illuminate\Support\Carbon::parse($e['claimed_at'])) < self::STALE_CLAIM_MINUTES;
            if ($busy || (int) ($e['attempts'] ?? 0) >= self::MAX_ATTEMPTS) {
                return null;
            }
            $meta['customer_emails'][$kind] = ['claimed_at' => now()->toIso8601String(), 'attempts' => (int) ($e['attempts'] ?? 0) + 1, 'failed_at' => null, 'sent_at' => null];
            $r->forceFill(['meta' => $meta])->save();

            return $r->load(['order.customer', 'order.items.event', 'ticketLinks']);
        });
    }

    /** Result is kept in the refund's timeline (staff only) and the standard delivery log, like the other order e-mails. */
    private function record(int $id, string $kind, array $fields, ?string $log = null): void
    {
        DB::transaction(function () use ($id, $kind, $fields, $log) {
            $r = PaymentRefund::query()->lockForUpdate()->findOrFail($id);
            $meta = (array) $r->meta;
            $meta['customer_emails'][$kind] = array_merge((array) ($meta['customer_emails'][$kind] ?? []), $fields);
            if ($log) {
                $meta['timeline'][] = ['at' => now()->toIso8601String(), 'event' => 'customer_email_'.$log, 'source' => 'system', 'note' => 'Customer e-mail "'.$kind.'" '.$log.($log === 'failed' ? ' ('.($fields['error'] ?? 'error').'); the refund is unaffected.' : '.')];
            }
            $r->forceFill(['meta' => $meta])->save();
            if ($log && $r->order) {
                activity('order_delivery')->performedOn($r->order)->withProperties(['refund' => $r->internal_reference, 'kind' => $kind])->log(($log === 'sent' ? 'Delivery sent: ' : 'Delivery failed: ').'refund_'.$kind);
            }
        });
    }
}
