<?php

namespace App\Console\Commands;

use App\Models\Order;
use App\Services\TicketIssuanceService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Safety net after a confirmed payment: finishes ticket issuance and delivery that failed or was interrupted.
 * Issuance is idempotent (unique order_item+seat, ticket numbers), delivery is recorded per channel, so running
 * this any number of times never duplicates a ticket or re-sends something that already went out.
 */
class RetryTicketIssuance extends Command
{
    protected $signature = 'orders:retry-ticket-issuance {--days=7 : Look back this many days} {--max-attempts=20 : Give up (and alert) after this many tries per order}';

    protected $description = 'Retry ticket issuance / delivery for paid orders that are not complete yet.';

    public function handle(TicketIssuanceService $issuance): int
    {
        $retried = 0;

        Order::query()
            ->where('status', 'paid')
            ->where('paid_at', '>=', now()->subDays((int) $this->option('days')))
            ->where('paid_at', '<=', now()->subMinutes(2)) // let the normal request finish first
            ->orderBy('id')
            ->each(function (Order $order) use ($issuance, &$retried) {
                if ($issuance->isComplete($order)) {
                    Cache::forget("issue-retry:{$order->id}");

                    return;
                }

                $attempts = Cache::increment("issue-retry:{$order->id}");
                Cache::put("issue-retry:{$order->id}", $attempts, now()->addDay());

                if ($attempts > (int) $this->option('max-attempts')) {
                    if ($attempts === (int) $this->option('max-attempts') + 1) {
                        Log::critical('Giving up automatic ticket issuance retries; manual action required.', ['order_id' => $order->id, 'order_number' => $order->order_number]);
                    }

                    return;
                }

                try {
                    $issuance->issueIfPaid($order);
                    $retried++;
                } catch (\Throwable $e) {
                    Log::error('Ticket issuance retry failed.', ['order_id' => $order->id, 'attempt' => $attempts, 'error' => $e->getMessage()]);
                }
            });

        $this->info("Orders retried: {$retried}");

        return self::SUCCESS;
    }
}
