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

        if ($order->wasChanged('status')) {
            app(\App\Orders\OrderAssignmentService::class)->snapshotSale($order);
        }

        app(TicketIssuanceService::class)->issueIfPaid($order);
    }

    private function isPaid(Order $order): bool
    {
        return $order->status === 'paid';
    }
}
