<?php

namespace App\Console\Commands;

use App\Services\PendingPaymentExpiryService;
use Illuminate\Console\Command;

class ExpirePendingPaymentOrders extends Command
{
    protected $signature = 'orders:expire-pending-payments';

    protected $description = 'Auto-cancel pending payment orders that exceeded payment timeout.';

    public function handle(PendingPaymentExpiryService $expiryService): int
    {
        $expired = $expiryService->expireDueOrders();

        $this->info('Expired orders count: '.$expired->count());

        return self::SUCCESS;
    }
}
