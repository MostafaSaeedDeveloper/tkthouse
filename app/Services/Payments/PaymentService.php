<?php

namespace App\Services\Payments;

use App\Models\Event;
use App\Models\EventTicket;
use App\Models\Order;
use App\Models\PaymentAttempt;
use App\Models\PaymentMethod;
use App\Services\TicketIssuanceService;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Collection;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Spatie\Activitylog\Models\Activity;

/**
 * One place that opens payments and decides, idempotently, whether an order is paid.
 *
 * Callbacks, browser redirects and the reconciliation job all end up in settle(); whichever arrives
 * first wins, the rest are no-ops, and tickets are issued exactly once.
 */
class PaymentService
{
    public const OUTCOME_PAID = 'paid';
    public const OUTCOME_ALREADY_PAID = 'already_paid';
    public const OUTCOME_FAILED = 'failed';
    public const OUTCOME_PENDING = 'pending';
    public const OUTCOME_MISMATCH = 'mismatch';
    public const OUTCOME_CLOSED_ORDER = 'closed_order';
    public const OUTCOME_REVIEW_REQUIRED = 'review_required';

    public function __construct(
        private readonly PaymentGatewayManager $gateways,
        private readonly TicketIssuanceService $issuance,
        private readonly \App\Services\Payments\Routing\PaymentAccountResolver $resolver,
        private readonly \App\Services\Payments\Routing\OfferedMethods $offered,
    ) {
    }

    /** Opens a payment at the gateway for a pending order and returns the URL to send the customer to. */
    public function start(Order $order, PaymentMethod $method): string
    {
        // Serialise double clicks / refreshes; a recent open attempt for the same method is reused so the
        // customer is not charged through two parallel gateway sessions.
        $lock = Cache::lock('pay-start:'.$order->id, 30);
        try {
            $lock->block(10);
        } catch (LockTimeoutException) {
            throw new \RuntimeException('A payment is already being opened for this order. Please wait a moment and try again.');
        }

        try {
            return $this->open($order, $method);
        } finally {
            $lock->release();
        }
    }

    private function open(Order $order, PaymentMethod $method): string
    {
        // Which merchant account takes this payment? Pinned account > latest attempt's account > fresh decision. Never browser input.
        $resolution = $this->resolver->forOrder($order);
        if ($resolution->blocked) {
            throw new \RuntimeException('Online payment is not available for this order right now ('.$resolution->reason.').');
        }
        if (! $this->offered->offers($resolution, $method->code)) {
            throw new \RuntimeException("Payment method [{$method->code}] is not available for this order's merchant account.");
        }
        $accountId = $resolution->account?->id;

        // A payment session of another merchant account is never reused, and a second account is never opened next to a live one.
        $liveOther = $order->paymentAttempts()->where('status', PaymentAttempt::INITIATED)
            ->where(fn ($q) => $accountId === null ? $q->whereNotNull('integration_account_id') : $q->where(fn ($w) => $w->whereNull('integration_account_id')->orWhere('integration_account_id', '!=', $accountId)))
            ->where('created_at', '>=', now()->subMinutes(60))->exists();
        if ($liveOther) {
            throw new \RuntimeException('A payment is already open with a different merchant account. Use "Switch payment account" after checking it.');
        }

        $reusable = $order->paymentAttempts()
            ->where('status', PaymentAttempt::INITIATED)
            ->where('method_code', $method->code)
            ->when($accountId === null, fn ($q) => $q->whereNull('integration_account_id'), fn ($q) => $q->where('integration_account_id', $accountId))
            ->where('created_at', '>=', now()->subMinutes(30))
            ->orderByDesc('id')
            ->get()
            ->first(fn (PaymentAttempt $a) => filled(data_get($a->meta, 'url')) && (float) $a->amount === (float) $order->total_amount);

        if ($reusable) {
            return (string) data_get($reusable->meta, 'url');
        }

        $gateway = $this->gateways->for((string) $method->provider);

        // Defence in depth: never open a payment through a method this environment must not use.
        if (! $gateway->isConfigured($method, $resolution->account)) {
            throw new \RuntimeException("Payment method [{$method->code}] is not available in this environment.");
        }

        $attempt = PaymentAttempt::create([
            'order_id' => $order->id,
            'provider' => $gateway->provider(),
            'method_code' => $method->code,
            'amount' => $order->total_amount,
            'currency' => 'EGP',
            'status' => PaymentAttempt::INITIATED,
            'internal_reference' => PaymentAttempt::newInternalReference(),
            'integration_account_id' => $accountId,
            'routing_snapshot' => $resolution->snapshot() + ['method_code' => $method->code, 'amount' => (float) $order->total_amount],
        ]);

        if ($accountId && $order->integration_account_id === null) {
            $order->forceFill(['integration_account_id' => $accountId])->saveQuietly(); // pinned for good at the first attempt
        }

        try {
            $init = $gateway->initiate($order, $method, $attempt);
        } catch (\Throwable $e) {
            $attempt->update(['status' => PaymentAttempt::FAILED, 'failure_reason' => mb_substr($e->getMessage(), 0, 250)]);
            throw $e;
        }

        $attempt->update(array_filter([
            'reference' => $init['reference'] ?? null,
            'transaction_id' => $init['transaction_id'] ?? null,
            'provider_order_id' => $init['provider_order_id'] ?? null,
        ], fn ($v) => $v !== null && $v !== '') + ['meta' => array_merge($attempt->fresh()->meta ?? [], ['url' => (string) $init['url']])]);

        return (string) $init['url'];
    }

    /**
     * Called when the customer comes back from the gateway or a callback arrives: asks the gateway what really
     * happened for every open attempt of the order and settles accordingly.
     */
    public function refresh(Order $order, ?string $hintTransactionId = null, ?string $provider = null): string
    {
        // Failed attempts stay eligible for a while: a customer can retry inside the same gateway session and
        // the successful transaction then arrives under an attempt we had already marked failed.
        $attempts = $order->paymentAttempts()
            ->where(fn ($q) => $q->whereIn('status', [PaymentAttempt::INITIATED, PaymentAttempt::FAILED])->orWhere(fn ($w) => $w->where('status', PaymentAttempt::EXPIRED)->where('meta->rerouted', true)))
            ->where('created_at', '>=', now()->subHours((int) config('payments.reconcile.max_age_hours', 48)))
            ->when($provider, fn ($q) => $q->where('provider', $provider))
            ->orderByDesc('id')
            ->get();

        if ($attempts->isEmpty()) {
            return $order->fresh()->status === 'paid' ? self::OUTCOME_ALREADY_PAID : self::OUTCOME_PENDING;
        }

        $outcome = self::OUTCOME_PENDING;
        foreach ($attempts as $attempt) {
            $result = $this->gateways->for($attempt->provider)->inquire($attempt, $hintTransactionId);
            $outcome = $this->settle($attempt, $result);

            if (in_array($outcome, [self::OUTCOME_PAID, self::OUTCOME_ALREADY_PAID], true)) {
                return $outcome;
            }
        }

        return $outcome;
    }

    /** Settles exactly one attempt (used by callbacks that identified it): other attempts and other merchant accounts are not touched. */
    public function refreshAttempt(PaymentAttempt $attempt, ?string $hintTransactionId = null): string
    {
        $result = $this->gateways->for($attempt->provider)->inquire($attempt, $hintTransactionId);

        return $this->settle($attempt, $result);
    }

    /** Re-checks every still-open attempt (callback lost, customer closed the tab, ...). */
    public function reconcile(): Collection
    {
        $minAge = (int) config('payments.reconcile.older_than_minutes', 2);
        $maxAge = (int) config('payments.reconcile.max_age_hours', 48);

        $settled = collect();

        PaymentAttempt::query()
            ->where(fn ($q) => $q->where('status', PaymentAttempt::INITIATED)->orWhere(fn ($w) => $w->where('status', PaymentAttempt::EXPIRED)->where('meta->rerouted', true)))
            ->where('created_at', '<=', now()->subMinutes($minAge))
            ->where('created_at', '>=', now()->subHours($maxAge))
            ->whereHas('order')
            ->orderBy('id')
            ->each(function (PaymentAttempt $attempt) use ($settled) {
                try {
                    $result = $this->gateways->for($attempt->provider)->inquire($attempt);
                    $outcome = $this->settle($attempt, $result);
                    if ($outcome !== self::OUTCOME_PENDING) {
                        $settled->push([$attempt->id, $outcome]);
                    }
                } catch (\Throwable $e) {
                    Log::warning('Payment reconcile failed for attempt.', ['attempt_id' => $attempt->id, 'error' => $e->getMessage()]);
                }
            });

        return $settled;
    }

    public function settle(PaymentAttempt $attempt, GatewayResult $result): string
    {
        // Every gateway answer (callback, redirect, reconcile, manual check) counts as a check of this attempt.
        PaymentAttempt::query()->whereKey($attempt->id)->update(['last_checked_at' => now()]);

        if (in_array($result->status, [GatewayResult::PENDING, GatewayResult::UNKNOWN], true)) {
            return self::OUTCOME_PENDING;
        }

        $issueFor = null;

        try {
            $outcome = $this->settleInTransaction($attempt, $result, $issueFor);
        } catch (\App\Services\Payments\TransactionReused) {
            $attempt->refresh()->update(['status' => PaymentAttempt::FAILED, 'failure_reason' => 'transaction_reused']);
            Log::critical('Gateway transaction already bound to another payment attempt.', ['attempt_id' => $attempt->id, 'transaction_id' => $result->transactionId]);

            return self::OUTCOME_MISMATCH;
        }

        if ($issueFor) {
            // After commit, so a slow mail server never holds row locks. A failure here must not hide the successful
            // payment: it is logged and retried by `orders:retry-ticket-issuance`.
            $this->issueSafely($issueFor->fresh());
        }

        return $outcome;
    }

    private function settleInTransaction(PaymentAttempt $attempt, GatewayResult $result, ?Order &$issueFor): string
    {
        return DB::transaction(function () use ($attempt, $result, &$issueFor) {
            /** @var PaymentAttempt $locked */
            $locked = PaymentAttempt::query()->lockForUpdate()->findOrFail($attempt->id);
            /** @var Order $order */
            $order = Order::withTrashed()->lockForUpdate()->findOrFail($locked->order_id);

            if ($locked->status === PaymentAttempt::PAID) {
                $issueFor = $order->status === 'paid' ? $order : null; // lets a duplicate callback repair a failed issuance
                return self::OUTCOME_ALREADY_PAID;
            }

            if ($result->status === GatewayResult::FAILED) {
                if ($locked->status === PaymentAttempt::INITIATED) {
                    $locked->update([
                        'status' => PaymentAttempt::FAILED,
                        'failure_reason' => mb_substr((string) ($result->message ?: 'Payment was not completed.'), 0, 250),
                    ]);
                    $this->log($order, 'Payment failed', $locked, ['payment_status' => 'failed']);
                }

                return self::OUTCOME_FAILED;
            }

            // ---- The gateway says "paid": every fact must match what the server created. Anything unknown is rejected. ----
            if ($reason = $this->mismatch($locked, $order, $result)) {
                $locked->update(['status' => PaymentAttempt::FAILED, 'failure_reason' => $reason]);
                $this->log($order, 'Payment rejected: '.$reason, $locked, [
                    'payment_status' => 'mismatch', 'reason' => $reason,
                    'expected_amount' => (float) $locked->amount, 'received_amount' => $result->amount,
                    'expected_currency' => $locked->currency, 'received_currency' => $result->currency,
                    'received_transaction_id' => $result->transactionId,
                ]);

                return self::OUTCOME_MISMATCH;
            }

            // Bind the paying transaction to this attempt (a retry inside the same gateway session pays under a new id).
            try {
                $meta = $locked->meta ?? [];
                if ($locked->transaction_id && $locked->transaction_id !== $result->transactionId) {
                    $meta['previous_transaction_ids'] = array_values(array_unique(array_merge($meta['previous_transaction_ids'] ?? [], [$locked->transaction_id])));
                }
                $locked->update([
                    'status' => PaymentAttempt::PAID,
                    'settled_at' => now(),
                    'paid_at' => now(),
                    'transaction_id' => $result->transactionId,
                    'meta' => $meta,
                ]);
            } catch (UniqueConstraintViolationException) {
                // Lost a race: the same transaction id was just bound to another attempt.
                throw new \App\Services\Payments\TransactionReused();
            }

            if ($order->status === 'paid') {
                // A second, different payment for an order that is already paid: keep the record for a refund.
                $this->flagForReview($locked, 'duplicate_payment');
                $this->log($order, 'Duplicate payment received', $locked, ['payment_status' => 'duplicate']);

                return self::OUTCOME_ALREADY_PAID;
            }

            $expiredCancel = $this->wasExpired($order);

            if ($order->status === 'pending_payment' || $expiredCancel) {
                // Policy: an order that is still pending is honoured unless its event has already ended. A *reinstated*
                // (auto-expired) order must be fully bookable again: event on sale, not ended, every ticket still active.
                if ($blocker = $this->fulfillmentBlocker($order, strict: $expiredCancel)) {
                    $this->flagForReview($locked, $blocker);
                    $this->log($order, 'Payment received but the order can no longer be fulfilled — refund review required', $locked, [
                        'payment_status' => 'review_required', 'reason' => $blocker, 'order_status' => $order->status,
                    ]);
                    Log::critical('Paid order cannot be fulfilled: refund review required.', ['order_id' => $order->id, 'attempt_id' => $locked->id, 'reason' => $blocker]);

                    return self::OUTCOME_REVIEW_REQUIRED;
                }

                $from = $order->status;
                $order->forceFill(['status' => 'paid', 'paid_at' => now()])->saveQuietly();
                app(\App\Orders\OrderAssignmentService::class)->snapshotSale($order); // attribution is frozen at payment time
                if ($order->trashed()) {
                    $order->restore();
                }

                $this->log($order, $expiredCancel ? 'Payment received after the deadline; order reinstated' : 'Payment completed successfully', $locked, [
                    'payment_status' => 'paid', 'from_status' => $from, 'to_status' => 'paid',
                ]);

                $issueFor = $order;

                return self::OUTCOME_PAID;
            }

            // Canceled by staff / rejected / awaiting approval: the money is recorded but the order is never fulfilled automatically.
            $this->flagForReview($locked, 'order_closed');
            $this->log($order, 'Payment received on a closed order', $locked, ['payment_status' => 'needs_review', 'order_status' => $order->status]);

            return self::OUTCOME_CLOSED_ORDER;
        });
    }

    /** Returns why a "paid" result must not be accepted, or null when everything matches. */
    private function mismatch(PaymentAttempt $attempt, Order $order, GatewayResult $result): ?string
    {
        if (blank($result->transactionId)) {
            return 'missing_transaction_id';
        }

        if ($attempt->reference !== null && $attempt->reference !== '' && $result->reference !== $attempt->reference) {
            return 'reference_mismatch';
        }

        // The attempt must belong to the stored invoice/transaction when one was recorded at initiation.
        if ($attempt->provider === 'fawaterak' && (string) $attempt->transaction_id !== (string) $result->transactionId) {
            return 'transaction_mismatch';
        }

        if ($result->amount === null) {
            return 'unknown_amount';
        }

        if (abs($result->amount - (float) $attempt->amount) > 0.009 || abs($result->amount - (float) $order->total_amount) > 0.009) {
            return 'amount_mismatch';
        }

        if ($result->currency === null || $result->currency === '') {
            return 'unknown_currency';
        }

        if (strtoupper($result->currency) !== strtoupper((string) $attempt->currency)) {
            return 'currency_mismatch';
        }

        // One gateway transaction can settle one attempt, ever.
        $boundElsewhere = PaymentAttempt::query()
            ->where('provider', $attempt->provider)
            ->where('transaction_id', $result->transactionId)
            ->where('id', '!=', $attempt->id)
            ->exists();

        return $boundElsewhere ? 'transaction_reused' : null;
    }

    /**
     * Why an order that just got paid can't be (re)fulfilled, or null if it can.
     * strict = reinstating an auto-expired order: the event must be on sale and every ticket still active.
     */
    private function fulfillmentBlocker(Order $order, bool $strict): ?string
    {
        $order->loadMissing('items');
        $eventIds = $order->items->pluck('event_id')->filter()->unique();

        if ($eventIds->isEmpty()) {
            return null; // nothing to evaluate (e.g. legacy items): behave as before
        }

        $events = Event::query()->whereIn('id', $eventIds)->get()->keyBy('id');

        foreach ($eventIds as $eventId) {
            $event = $events->get($eventId);

            if (! $event) {
                return 'event_missing';
            }
            if ($event->event_date && $event->event_date->lt(now()->startOfDay())) {
                return 'event_ended';
            }
            if ($strict && $event->status !== 'active') {
                return 'event_not_on_sale';
            }
        }

        if ($strict) {
            foreach ($order->items as $item) {
                $event = $events->get($item->event_id);
                $prefix = $event ? $event->name.' - ' : '';
                $ticketName = trim(str_starts_with((string) $item->ticket_name, $prefix) ? substr((string) $item->ticket_name, strlen($prefix)) : (string) $item->ticket_name);

                $ticket = EventTicket::query()->where('event_id', $item->event_id)->where('name', $ticketName)->first();
                if (! $ticket || $ticket->status !== 'active') {
                    return 'ticket_unavailable';
                }
            }
        }

        return null;
    }

    private function flagForReview(PaymentAttempt $attempt, string $reason): void
    {
        $attempt->update(['meta' => array_merge($attempt->meta ?? [], ['needs_review' => $reason, 'flagged_at' => now()->toIso8601String()])]);
    }

    private function issueSafely(Order $order): void
    {
        try {
            $this->issuance->issueIfPaid($order);
        } catch (\Throwable $e) {
            Log::critical('Ticket issuance failed after a confirmed payment; it will be retried.', ['order_id' => $order->id, 'error' => $e->getMessage()]);

            activity('orders')->performedOn($order)->withProperties(['error' => mb_substr($e->getMessage(), 0, 250)])
                ->log('Ticket issuance failed after payment (will retry)');
        }
    }

    private function wasExpired(Order $order): bool
    {
        if ($order->status !== 'canceled') {
            return false;
        }

        return Activity::query()
            ->where('subject_type', Order::class)
            ->where('subject_id', $order->id)
            ->where('log_name', 'orders')
            ->where('properties->reason', 'payment_deadline_expired')
            ->exists();
    }

    private function log(Order $order, string $description, PaymentAttempt $attempt, array $properties = []): void
    {
        activity('orders')
            ->performedOn($order)
            ->withProperties(array_merge([
                'provider' => $attempt->provider,
                'payment_method' => $attempt->method_code,
                'transaction_id' => $attempt->transaction_id,
                'attempt_id' => $attempt->id,
            ], $properties))
            ->log($description);
    }
}
