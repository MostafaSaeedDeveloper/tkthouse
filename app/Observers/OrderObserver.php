<?php

namespace App\Observers;

use App\Models\Order;
use App\Services\TicketIssuanceService;

class OrderObserver
{
    public function saved(Order $order): void
    {
        if (! $this->isPaid($order)) {
            return;
        }

        app(TicketIssuanceService::class)->issueIfPaid($order);
    }

    private function isPaid(Order $order): bool
    {
        return $order->status === 'complete' && $order->payment_status === 'paid';
    }
}
