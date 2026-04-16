<?php

namespace App\Services;

use App\Mail\OrderStatusChangedMail;
use App\Models\Order;
use App\Support\SystemSettings;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class PendingPaymentExpiryService
{
    public function expireDueOrders(?int $timeoutMinutes = null): Collection
    {
        $timeout = max(1, (int) ($timeoutMinutes ?? SystemSettings::pendingPaymentTimeoutMinutes()));
        $cutoff = now()->subMinutes($timeout);

        $orders = Order::query()
            ->where('status', 'pending_payment')
            ->whereNotNull('payment_timeout_started_at')
            ->where('payment_timeout_started_at', '<=', $cutoff)
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

            $this->sendCanceledEmailSafely($order, $oldStatus);

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
        if (! $order->payment_timeout_started_at) {
            return false;
        }

        $deadline = $order->payment_timeout_started_at->copy()->addMinutes($timeout);

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

        $this->sendCanceledEmailSafely($order, $oldStatus);

        return true;
    }

    private function sendCanceledEmailSafely(Order $order, string $oldStatus): void
    {
        if (! filled($order->customer?->email)) {
            return;
        }

        try {
            Mail::to($order->customer->email)
                ->send(new OrderStatusChangedMail($order, $oldStatus, 'canceled'));
        } catch (\Throwable $exception) {
            Log::warning('Failed to send order canceled email after timeout.', [
                'order_id' => $order->id,
                'email' => $order->customer?->email,
                'error' => $exception->getMessage(),
            ]);
        }
    }
}
