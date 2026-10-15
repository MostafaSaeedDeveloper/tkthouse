<?php

namespace App\Services\Payments;

use App\Models\Order;
use App\Models\PaymentAttempt;
use App\Models\PaymentMethod;
use App\Services\TicketIssuanceService;
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

    public function __construct(
        private readonly PaymentGatewayManager $gateways,
        private readonly TicketIssuanceService $issuance,
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
        $reusable = $order->paymentAttempts()
            ->where('status', PaymentAttempt::INITIATED)
            ->where('method_code', $method->code)
            ->where('created_at', '>=', now()->subMinutes(30))
            ->orderByDesc('id')
            ->get()
            ->first(fn (PaymentAttempt $a) => filled(data_get($a->meta, 'url')) && (float) $a->amount === (float) $order->total_amount);

        if ($reusable) {
            return (string) data_get($reusable->meta, 'url');
        }

        $gateway = $this->gateways->for((string) $method->provider);

        $attempt = PaymentAttempt::create([
            'order_id' => $order->id,
            'provider' => $gateway->provider(),
            'method_code' => $method->code,
            'amount' => $order->total_amount,
            'currency' => 'EGP',
            'status' => PaymentAttempt::INITIATED,
        ]);

        try {
            $init = $gateway->initiate($order, $method, $attempt);
        } catch (\Throwable $e) {
            $attempt->update(['status' => PaymentAttempt::FAILED, 'failure_reason' => mb_substr($e->getMessage(), 0, 250)]);
            throw $e;
        }

        $attempt->update(array_filter([
            'reference' => $init['reference'] ?? null,
            'transaction_id' => $init['transaction_id'] ?? null,
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
            ->whereIn('status', [PaymentAttempt::INITIATED, PaymentAttempt::FAILED])
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

    /** Re-checks every still-open attempt (callback lost, customer closed the tab, ...). */
    public function reconcile(): Collection
    {
        $minAge = (int) config('payments.reconcile.older_than_minutes', 2);
        $maxAge = (int) config('payments.reconcile.max_age_hours', 48);

        $settled = collect();

        PaymentAttempt::query()
            ->where('status', PaymentAttempt::INITIATED)
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
        if (in_array($result->status, [GatewayResult::PENDING, GatewayResult::UNKNOWN], true)) {
            if ($result->transactionId && ! $attempt->transaction_id) {
                $attempt->update(['transaction_id' => $result->transactionId]);
            }

            return self::OUTCOME_PENDING;
        }

        $issueFor = null;

        $outcome = DB::transaction(function () use ($attempt, $result, &$issueFor) {
            /** @var PaymentAttempt $locked */
            $locked = PaymentAttempt::query()->lockForUpdate()->findOrFail($attempt->id);
            /** @var Order $order */
            $order = Order::withTrashed()->lockForUpdate()->findOrFail($locked->order_id);

            if ($locked->status === PaymentAttempt::PAID) {
                return self::OUTCOME_ALREADY_PAID;
            }

            if ($result->status === GatewayResult::FAILED) {
                if ($locked->status === PaymentAttempt::INITIATED) {
                    $locked->update([
                        'status' => PaymentAttempt::FAILED,
                        'failure_reason' => mb_substr((string) ($result->message ?: 'Payment was not completed.'), 0, 250),
                        'transaction_id' => $locked->transaction_id ?: $result->transactionId,
                    ]);
                    $this->log($order, 'Payment failed', $locked, ['payment_status' => 'failed']);
                }

                return self::OUTCOME_FAILED;
            }

            // Verified as paid by the gateway. The amount must match what the server priced.
            if ($result->amount !== null && abs($result->amount - (float) $order->total_amount) > 0.009) {
                $locked->update(['status' => PaymentAttempt::FAILED, 'failure_reason' => 'amount_mismatch']);
                $this->log($order, 'Payment amount mismatch', $locked, [
                    'payment_status' => 'mismatch', 'expected' => (float) $order->total_amount, 'received' => $result->amount,
                ]);

                return self::OUTCOME_MISMATCH;
            }

            $locked->update([
                'status' => PaymentAttempt::PAID,
                'settled_at' => now(),
                'transaction_id' => $locked->transaction_id ?: $result->transactionId,
            ]);

            if ($order->status === 'paid') {
                // A second, different payment for an order that is already paid: keep the record for a refund.
                $this->log($order, 'Duplicate payment received', $locked, ['payment_status' => 'duplicate']);

                return self::OUTCOME_ALREADY_PAID;
            }

            if ($order->status === 'pending_payment' || $this->wasExpired($order)) {
                $wasExpired = $order->status !== 'pending_payment';
                $from = $order->status;

                $order->forceFill(['status' => 'paid', 'paid_at' => now()])->saveQuietly();
                if ($order->trashed()) {
                    $order->restore();
                }

                $this->log($order, $wasExpired ? 'Payment received after the deadline; order reinstated' : 'Payment completed successfully', $locked, [
                    'payment_status' => 'paid', 'from_status' => $from, 'to_status' => 'paid',
                ]);

                $issueFor = $order;

                return self::OUTCOME_PAID;
            }

            // Canceled by an admin / rejected / awaiting approval: money arrived for an order that must not be fulfilled automatically.
            $this->log($order, 'Payment received on a closed order', $locked, ['payment_status' => 'needs_review', 'order_status' => $order->status]);

            return self::OUTCOME_CLOSED_ORDER;
        });

        if ($issueFor) {
            // After commit, so a slow mail server never holds the row locks.
            $this->issuance->issueIfPaid($issueFor->fresh());
        }

        return $outcome;
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
