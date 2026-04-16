<?php

namespace App\Services;

use App\Mail\OrderStatusChangedMail;
use App\Models\Order;
use App\Support\SystemSettings;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Mail;

class PendingPaymentExpiryService
{
    public function expireDueOrders(?int $timeoutMinutes = null): Collection
    {
        $timeout = max(1, (int) ($timeoutMinutes ?? SystemSettings::pendingPaymentTimeoutMinutes()));
        $cutoff = now()->subMinutes($timeout);

        $orders = Order::query()
            ->where('status', 'pending_payment')
            ->where('created_at', '<=', $cutoff)
            ->with(['customer'])
            ->get();

        $expired = collect();

        foreach ($orders as $order) {
            $oldStatus = (string) $order->status;

            $order->update([
                'status' => 'canceled',
            ]);

            activity('orders')
                ->performedOn($order)
                ->withProperties([
                    'from_status' => $oldStatus,
                    'to_status' => 'canceled',
                    'reason' => 'payment_deadline_expired',
                ])
                ->log('Order auto-canceled after payment deadline expired');

            if (filled($order->customer?->email)) {
                Mail::to($order->customer->email)
                    ->send(new OrderStatusChangedMail($order, $oldStatus, 'canceled'));
            }

            $expired->push($order);
        }

        return $expired;
    }

    public function expireOrderIfNeeded(Order $order, ?int $timeoutMinutes = null): bool
    {
        if ($order->status !== 'pending_payment') {
            return false;
        }

        $timeout = max(1, (int) ($timeoutMinutes ?? SystemSettings::pendingPaymentTimeoutMinutes()));
        $deadline = $order->created_at?->copy()->addMinutes($timeout);

        if (! $deadline || now()->lt($deadline)) {
            return false;
        }

        $oldStatus = (string) $order->status;

        $order->update([
            'status' => 'canceled',
        ]);

        activity('orders')
            ->performedOn($order)
            ->withProperties([
                'from_status' => $oldStatus,
                'to_status' => 'canceled',
                'reason' => 'payment_deadline_expired',
            ])
            ->log('Order auto-canceled after payment deadline expired');

        if (filled($order->customer?->email)) {
            Mail::to($order->customer->email)
                ->send(new OrderStatusChangedMail($order, $oldStatus, 'canceled'));
        }

        return true;
    }
}
