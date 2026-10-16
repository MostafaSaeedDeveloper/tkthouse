<?php

namespace App\Services\Payments;

use App\Models\PaymentAttempt;
use App\Models\PaymentRefund;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

/**
 * Payment & refund figures with explicit definitions (all in minor units internally, shown as EGP):
 *  - Collected      = Σ amount of PAID attempts whose paid time is in the range (the gateway confirmed it; not a bank settlement).
 *  - Refunded       = Σ amount of refunds that SUCCEEDED in the range. Requested / processing / unknown refunds are listed separately and never counted.
 *  - Net collected  = Collected − Refunded (same range; a refund of an earlier payment reduces this period's net).
 *  - Pending        = attempts still INITIATED (waiting for the gateway). Failed = FAILED attempts created in the range.
 * Scope: only attempts/refunds of orders the viewer may see (assignment rules apply). The merchant-account breakdown needs the account permission.
 */
class PaymentReport
{
    public function build(User $viewer, CarbonInterface $from, CarbonInterface $to, bool $withAccounts): array
    {
        $attempts = fn () => PaymentAttempt::query()->whereHas('order', fn ($o) => $o->visibleTo($viewer));
        $paid = fn () => $attempts()->where('status', 'paid')->whereBetween(DB::raw('COALESCE(payment_attempts.paid_at, payment_attempts.settled_at, payment_attempts.updated_at)'), [$from, $to]);
        $refunds = fn () => PaymentRefund::query()->whereHas('order', fn ($o) => $o->visibleTo($viewer));
        $done = fn () => $refunds()->where('payment_refunds.status', PaymentRefund::SUCCEEDED)->whereBetween(DB::raw('COALESCE(payment_refunds.completed_at, payment_refunds.updated_at)'), [$from, $to]);

        $minor = fn ($q) => (int) round(((float) $q->sum('amount')) * 100);
        $collected = $minor($paid());
        $refunded = (int) $done()->sum('amount_minor');

        $totals = [
            'collected' => $collected,
            'successful' => (int) $paid()->count(),
            'pending' => (int) $attempts()->where('status', 'initiated')->whereBetween('created_at', [$from, $to])->count(),
            'pending_amount' => $minor($attempts()->where('status', 'initiated')->whereBetween('created_at', [$from, $to])),
            'failed' => (int) $attempts()->where('status', 'failed')->whereBetween('created_at', [$from, $to])->count(),
            'refunded' => $refunded,
            'refund_count' => (int) $done()->count(),
            'partial_refunds' => (int) $done()->where('type', 'partial')->count(),
            'refunds_in_progress' => (int) $refunds()->whereIn('status', PaymentRefund::IN_FLIGHT)->count(),
            'refunds_in_progress_amount' => (int) $refunds()->whereIn('status', PaymentRefund::IN_FLIGHT)->sum('amount_minor'),
            'net' => $collected - $refunded,
        ];

        $group = function (string $col) use ($paid, $done) {
            $rows = [];
            foreach ($paid()->select($col, DB::raw('COUNT(*) c'), DB::raw('SUM(amount) a'))->groupBy($col)->get() as $r) {
                $rows[(string) $r->{$col}] = ['count' => (int) $r->c, 'collected' => (int) round(((float) $r->a) * 100), 'refunded' => 0];
            }
            // refunds attributed to the PAYMENT's provider/account/method (never to the refund's creator or the current assignee)
            $refundCol = ['provider' => 'payment_refunds.provider', 'integration_account_id' => 'payment_refunds.integration_account_id', 'method_code' => 'payment_attempts.method_code'][$col];
            $q = $done()->join('payment_attempts', 'payment_attempts.id', '=', 'payment_refunds.payment_attempt_id')->select(DB::raw($refundCol.' as k'), DB::raw('SUM(payment_refunds.amount_minor) m'))->groupBy(DB::raw($refundCol));
            foreach ($q->get() as $r) {
                $rows[(string) $r->k] ??= ['count' => 0, 'collected' => 0, 'refunded' => 0];
                $rows[(string) $r->k]['refunded'] = (int) $r->m;
            }
            foreach ($rows as $k => $v) {
                $rows[$k]['net'] = $v['collected'] - $v['refunded'];
            }

            return $rows;
        };

        return [
            'totals' => $totals,
            'by_provider' => $group('provider'),
            'by_method' => $group('method_code'),
            'by_account' => $withAccounts ? $group('integration_account_id') : null,
        ];
    }
}
