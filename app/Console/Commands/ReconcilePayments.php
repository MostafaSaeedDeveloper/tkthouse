<?php

namespace App\Console\Commands;

use App\Services\Payments\PaymentService;
use Illuminate\Console\Command;

class ReconcilePayments extends Command
{
    protected $signature = 'orders:reconcile-payments';

    protected $description = 'Ask the payment gateways about still-open payment attempts and settle the ones that were paid or failed.';

    public function handle(PaymentService $payments): int
    {
        $settled = $payments->reconcile();

        $this->info('Settled attempts: '.$settled->count());

        return self::SUCCESS;
    }
}
