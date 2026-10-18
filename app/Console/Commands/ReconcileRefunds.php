<?php

namespace App\Console\Commands;

use App\Models\PaymentRefund;
use App\Services\Payments\Refunds\RefundService;
use Illuminate\Console\Command;

/** Re-checks API refunds that are still processing/unknown. Read-only toward the provider; never sends a refund. */
class ReconcileRefunds extends Command
{
    protected $signature = 'payments:reconcile-refunds';
    protected $description = 'Ask providers about refunds that are still processing or unknown.';

    public function handle(RefundService $refunds): int
    {
        $n = 0;
        PaymentRefund::query()->where('mode', 'api')->whereIn('status', [PaymentRefund::PROCESSING, PaymentRefund::UNKNOWN])->where('submitted_at', '<=', now()->subMinutes(2))
            ->orderBy('id')->each(function (PaymentRefund $r) use ($refunds, &$n) {
                try {
                    $refunds->inquire($r);
                    $n++;
                } catch (\Throwable $e) {
                    $this->warn("Refund {$r->internal_reference}: ".class_basename($e));
                }
            });
        $this->info("Checked {$n} refund(s).");

        return self::SUCCESS;
    }
}
