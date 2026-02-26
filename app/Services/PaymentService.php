<?php

namespace App\Services;

use App\Models\Order;
use Illuminate\Support\Facades\DB;

class PaymentService
{
    public function markAsPaid(Order $order): void
    {
        DB::transaction(function () use ($order): void {
            $order->update(['status' => 'paid', 'payment_status' => 'paid', 'paid_at' => now()]);
            app(TicketsGenerationService::class)->generate($order);
            app(TicketDeliveryService::class)->deliverOrderTickets($order->fresh('tickets.order.user'));
            app(ReferralService::class)->qualifyOnFirstPaidOrder($order);
        });
    }
}
