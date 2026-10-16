<?php

namespace App\Console\Commands;

use App\Models\PaymentAttempt;
use Illuminate\Console\Command;

/** Ops report: payments that were taken but could not be fulfilled automatically (refund / manual review). */
class PaymentReviewQueue extends Command
{
    protected $signature = 'payments:review-queue';

    protected $description = 'List confirmed payments that need a manual decision (refund, duplicate, closed order, ...).';

    public function handle(): int
    {
        $rows = PaymentAttempt::query()
            ->where('status', PaymentAttempt::PAID)
            ->with('order:id,order_number,status,total_amount')
            ->orderBy('id')
            ->get()
            ->filter(fn (PaymentAttempt $a) => filled(data_get($a->meta, 'needs_review')) || ($a->order && $a->order->status !== 'paid'))
            ->map(fn (PaymentAttempt $a) => [
                $a->id, $a->order?->order_number, $a->provider, $a->transaction_id, number_format((float) $a->amount, 2).' '.$a->currency,
                $a->order?->status, data_get($a->meta, 'needs_review', 'order_not_paid'), optional($a->settled_at)->toDateTimeString(),
            ])->values();

        if ($rows->isEmpty()) {
            $this->info('Nothing needs review.');

            return self::SUCCESS;
        }

        $this->table(['Attempt', 'Order', 'Provider', 'Transaction', 'Amount', 'Order status', 'Reason', 'Paid at'], $rows->all());

        return self::SUCCESS;
    }
}
