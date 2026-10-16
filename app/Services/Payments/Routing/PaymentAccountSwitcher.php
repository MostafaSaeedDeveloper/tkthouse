<?php

namespace App\Services\Payments\Routing;

use App\Models\IntegrationAccount;
use App\Models\Order;
use App\Models\PaymentAttempt;
use App\Models\User;
use App\Services\Payments\GatewayResult;
use App\Services\Payments\PaymentGatewayManager;
use App\Services\Payments\PaymentService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Explicit, audited change of the merchant account of an unpaid order. It never trusts local state: every attempt that could still
 * collect money is asked at its own gateway/account first. Refuses on a successful payment, an unknown state, an error, or a
 * payment that may be in progress (PENDING and younger than IN_PROGRESS_MINUTES, e.g. 3DS).
 */
class PaymentAccountSwitcher
{
    public const IN_PROGRESS_MINUTES = 60;

    private static bool $switching = false;

    public function __construct(
        private readonly PaymentGatewayManager $gateways,
        private readonly PaymentService $payments,
        private readonly OfferedMethods $offered,
    ) {
    }

    public static function switching(): bool
    {
        return self::$switching;
    }

    public function switch(Order $order, IntegrationAccount $to, User $by, string $reason): Order
    {
        if (! $by->can('orders.reroute_payment')) {
            abort(403);
        }
        $fail = fn (string $msg) => throw ValidationException::withMessages(['account' => $msg]);

        if (! PaymentAccountResolver::usable($to)) {
            $fail('The target merchant account is not enabled and verified.');
        }

        // Live checks happen OUTSIDE the order lock (network), the decision is re-validated inside it.
        $this->assertNoCollectableAttempts($order, $fail);

        return DB::transaction(function () use ($order, $to, $by, $reason, $fail) {
            $locked = Order::query()->lockForUpdate()->findOrFail($order->id);

            if ($locked->status !== 'pending_payment') {
                $fail('Only orders that are awaiting payment can be switched.');
            }
            if ($locked->integration_account_id === $to->id) {
                $fail('The order already uses this merchant account.');
            }
            if (! $this->offered->offers(new Resolution($to, 'switch'), (string) $locked->payment_method)) {
                $fail("The target account does not support the order's payment method.");
            }

            $attempts = $locked->paymentAttempts()->lockForUpdate()->get();
            // A payment opened between the live check and the lock must also block the switch.
            if ($attempts->contains(fn (PaymentAttempt $a) => $a->status === PaymentAttempt::PAID)) {
                $fail('A payment was already received for this order.');
            }

            $from = $locked->integrationAccount;
            foreach ($attempts as $a) {
                if ($a->status === PaymentAttempt::INITIATED) {
                    // Closed locally, but kept reconcile-eligible (meta.rerouted): a late payment on the old session is still matched.
                    $a->forceFill(['status' => PaymentAttempt::EXPIRED, 'failure_reason' => 'rerouted', 'meta' => array_merge($a->meta ?? [], ['rerouted' => true, 'rerouted_at' => now()->toIso8601String()])])->save();
                }
            }

            self::$switching = true;
            try {
                $locked->forceFill(['integration_account_id' => $to->id])->save();
            } finally {
                self::$switching = false;
            }

            activity('orders')->performedOn($locked)->causedBy($by)->withProperties([
                'from_account' => $from?->name, 'from_account_id' => $from?->id, 'to_account' => $to->name, 'to_account_id' => $to->id,
                'reason' => mb_substr($reason, 0, 300), 'attempts_closed' => $attempts->where('status', PaymentAttempt::EXPIRED)->count(),
            ])->log('Payment account switched');

            return $locked;
        });
    }

    private function assertNoCollectableAttempts(Order $order, \Closure $fail): void
    {
        $attempts = $order->paymentAttempts()
            ->whereIn('status', [PaymentAttempt::INITIATED, PaymentAttempt::FAILED])
            ->where('created_at', '>=', now()->subHours((int) config('payments.reconcile.max_age_hours', 48)))->get();

        foreach ($attempts as $attempt) {
            try {
                $result = $this->gateways->for($attempt->provider)->inquire($attempt);
            } catch (\Throwable) {
                $fail("Could not verify payment attempt #{$attempt->id} with its gateway; try again later.");
            }

            if ($result->status === GatewayResult::PAID) {
                $this->payments->settle($attempt, $result); // record the money that exists
                $fail("Payment attempt #{$attempt->id} was already paid at the gateway; the order has been settled instead of switched.");
            }
            if ($result->status === GatewayResult::UNKNOWN) {
                $fail("The gateway state of payment attempt #{$attempt->id} is unknown; the account cannot be switched safely.");
            }
            if ($attempt->status === PaymentAttempt::INITIATED && $result->status === GatewayResult::PENDING
                && $attempt->created_at->gt(now()->subMinutes(self::IN_PROGRESS_MINUTES))) {
                $fail("Payment attempt #{$attempt->id} may still be in progress (opened less than ".self::IN_PROGRESS_MINUTES.' minutes ago). Try again later.');
            }
        }
    }
}
