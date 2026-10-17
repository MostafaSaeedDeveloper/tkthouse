<?php

namespace App\Observers;

use App\Models\Order;
use App\Services\TicketIssuanceService;

class OrderObserver
{
    /** The merchant account an order was pinned to only changes through PaymentAccountSwitcher (audited). */
    public function updating(Order $order): void
    {
        if ($order->isDirty('integration_account_id') && $order->getOriginal('integration_account_id') !== null && ! \App\Services\Payments\Routing\PaymentAccountSwitcher::switching()) {
            throw new \LogicException('Order payment account is immutable; use the audited switch action.');
        }
    }

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
