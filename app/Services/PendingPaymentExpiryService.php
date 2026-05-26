<?php

namespace App\Services;

use App\Mail\OrderStatusChangedMail;
use App\Models\NotificationLog;
use App\Models\Order;
use App\Support\SystemSettings;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class PendingPaymentExpiryService
{
    public function expireDueOrders(?int $timeoutMinutes = null): Collection
    {
        $orders = Order::query()
            ->where('status', 'pending_payment')
            ->with(['customer'])
            ->get();

        $expired = collect();

        foreach ($orders as $order) {
            if ($this->expireOrderIfNeeded($order, $timeoutMinutes)) {
                $expired->push($order->fresh());
            }
        }

        return $expired;
    }

    public function expireOrderIfNeeded(Order $order, ?int $timeoutMinutes = null): bool
    {
        if ($order->status !== 'pending_payment') {
            return false;
        }

        $timeout = max(1, (int) ($timeoutMinutes ?? $order->payment_timeout_minutes ?? SystemSettings::pendingPaymentTimeoutMinutes()));
        $startAt = $order->payment_timeout_started_at ?: $order->created_at;
        if (! $startAt) {
            return false;
        }

        $deadline = $startAt->copy()->addMinutes($timeout);

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

        $subject = 'Your order has been canceled #'.$order->order_number;
        try {
            Mail::to($order->customer->email)
                ->send(new OrderStatusChangedMail($order, $oldStatus, 'canceled'));
            NotificationLog::logEmail([
                'type' => 'order_status_changed',
                'recipient' => $order->customer->email,
                'order_id' => $order->id,
                'subject' => $subject,
                'status' => 'sent',
                'metadata' => ['from' => $oldStatus, 'to' => 'canceled', 'trigger' => 'payment_timeout'],
            ]);
        } catch (\Throwable $exception) {
            Log::warning('Failed to send order canceled email after timeout.', [
                'order_id' => $order->id,
                'email' => $order->customer?->email,
                'error' => $exception->getMessage(),
            ]);
            NotificationLog::logEmail([
                'type' => 'order_status_changed',
                'recipient' => $order->customer->email,
                'order_id' => $order->id,
                'subject' => $subject,
                'status' => 'failed',
                'error' => $exception->getMessage(),
                'metadata' => ['from' => $oldStatus, 'to' => 'canceled', 'trigger' => 'payment_timeout'],
            ]);
        }
    }
}
