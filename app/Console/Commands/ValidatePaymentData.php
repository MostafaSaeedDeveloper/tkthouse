<?php

namespace App\Console\Commands;

use App\Models\Order;
use App\Models\PaymentAttempt;
use App\Models\PaymentMethod;
use App\Models\PaymentRefund;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Read-only validation of existing payment data before/after the unified payment migration, plus an OPTIONAL reference backfill.
 * Default = dry run: nothing is written. `--backfill-references` only fills the NEW internal_reference column of legacy attempts;
 * provider transaction ids, references, amounts and statuses are never touched.
 */
class ValidatePaymentData extends Command
{
    protected $signature = 'payments:validate {--backfill-references : write PAY- references for legacy attempts that have none (otherwise dry run)}';

    protected $description = 'Validate payment data consistency (read-only) and optionally backfill internal references for legacy attempts.';

    public function handle(): int
    {
        $problems = 0;
        $report = function (string $label, int $n, bool $problem = true) use (&$problems) {
            $this->line(sprintf('  %s %-58s %d', $n > 0 && $problem ? '✗' : '✓', $label, $n));
            $problems += ($n > 0 && $problem) ? 1 : 0;
        };

        $this->info('Attempts');
        $this->line('  · total attempts                                            '.PaymentAttempt::count());
        $this->line('  · legacy attempts (no merchant account, still valid)         '.PaymentAttempt::whereNull('integration_account_id')->count());
        $this->line('  · attempts without our internal reference                    '.PaymentAttempt::whereNull('internal_reference')->count());
        $report('paid attempts without a provider transaction id', PaymentAttempt::where('status', 'paid')->where(fn ($q) => $q->whereNull('transaction_id')->orWhere('transaction_id', ''))->count());
        $report('duplicate (provider, transaction id) pairs', DB::table('payment_attempts')->whereNotNull('transaction_id')->select('provider', 'transaction_id')->groupBy('provider', 'transaction_id')->havingRaw('COUNT(*) > 1')->get()->count());
        $report('attempts whose order has a DIFFERENT merchant account', PaymentAttempt::query()->join('orders', 'orders.id', '=', 'payment_attempts.order_id')->whereNotNull('payment_attempts.integration_account_id')->whereNotNull('orders.integration_account_id')->whereColumn('payment_attempts.integration_account_id', '!=', 'orders.integration_account_id')->count(), false);
        $report('attempts using a payment method code that no longer exists', PaymentAttempt::whereNotNull('method_code')->whereNotIn('method_code', PaymentMethod::pluck('code'))->count());

        $this->info('Orders');
        $report('orders referencing a payment method code that does not exist', Order::withTrashed()->whereNotNull('payment_method')->whereNotIn('payment_method', array_merge(PaymentMethod::pluck('code')->all(), ['pending_review', 'cash', 'card', '']))->count());
        $report('paid orders with no paid attempt (manual payments are fine, listed for information)', Order::where('status', 'paid')->whereDoesntHave('paymentAttempts', fn ($q) => $q->where('status', 'paid'))->count(), false);

        $this->info('Refunds');
        $over = 0;
        foreach (PaymentRefund::query()->whereIn('status', PaymentRefund::HOLDING)->select('payment_attempt_id', DB::raw('SUM(amount_minor) m'))->groupBy('payment_attempt_id')->get() as $row) {
            $captured = (int) round(((float) PaymentAttempt::whereKey($row->payment_attempt_id)->value('amount')) * 100);
            $over += (int) $row->m > $captured ? 1 : 0;
        }
        $report('attempts whose holding refunds exceed the captured amount', $over);
        $report('refunds in unknown state (need a person)', PaymentRefund::where('status', 'unknown')->count());

        $this->info('Webhooks');
        $report('events that failed processing', DB::table('webhook_events')->where('processing_status', 'failed')->count(), false);
        $report('events needing review', DB::table('webhook_events')->where('processing_status', 'needs_review')->count(), false);

        $missing = PaymentAttempt::whereNull('internal_reference')->count();
        if ($this->option('backfill-references')) {
            $n = 0;
            PaymentAttempt::whereNull('internal_reference')->orderBy('id')->each(function (PaymentAttempt $a) use (&$n) {
                PaymentAttempt::query()->whereKey($a->id)->update(['internal_reference' => PaymentAttempt::newInternalReference()]);
                $n++;
            });
            $this->info("Backfilled {$n} internal reference(s). Provider ids were not touched.");
        } else {
            $this->comment("Dry run: {$missing} legacy attempt(s) would receive an internal reference with --backfill-references (optional).");
        }

        $this->line($problems === 0 ? '<info>No blocking problems found.</info>' : "<error>{$problems} check(s) need attention.</error>");

        return $problems === 0 ? self::SUCCESS : self::FAILURE;
    }
}
