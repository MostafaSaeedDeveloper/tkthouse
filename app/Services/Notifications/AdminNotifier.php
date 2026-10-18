<?php

namespace App\Services\Notifications;

use App\Models\Order;
use App\Models\PaymentRefund;
use App\Models\User;
use App\Notifications\AdminAlert;
use App\Orders\OrderAccess;
use Illuminate\Support\Collection;

/**
 * Who gets a bell notification for what. Notifications are a convenience: whatever happens in here must never break the
 * action that triggered it (a checkout, a payment callback, an assignment), so every entry point swallows and reports errors.
 */
class AdminNotifier
{
    /** Staff = accounts with a role or direct permissions (customers have neither). */
    private function staff(): Collection
    {
        return User::query()->where('is_active', true)->where(fn ($q) => $q->whereHas('roles')->orWhereHas('permissions'))->get();
    }

    /** Staff who may see this order and are expected to act on it (orders.update) or own it (assigned to them). */
    private function orderAudience(Order $order, bool $needsUpdatePermission = true): Collection
    {
        return $this->staff()->filter(function (User $u) use ($order, $needsUpdatePermission) {
            if (! $u->can('orders.view') || ! $order->isVisibleTo($u)) {
                return false;
            }

            return (int) $order->assigned_user_id === $u->id || ! $needsUpdatePermission || $u->can('orders.update');
        })->values();
    }

    private function send(iterable $users, array $payload): void
    {
        foreach ($users as $user) {
            $user->notify(new AdminAlert($payload));
        }
    }

    private function safely(callable $fn): void
    {
        try {
            $fn();
        } catch (\Throwable $e) {
            report($e);
        }
    }

    private function orderUrl(Order $order): string
    {
        // Relative path: a notification created by a webhook/console process must not depend on APP_URL.
        return route('admin.orders.show', $order, false);
    }

    private function number(Order $order): string
    {
        return \App\Support\OrderPresenter::number($order);
    }

    private function who(Order $order): string
    {
        return trim((string) $order->customer?->full_name) ?: 'a customer';
    }

    private function money(Order $order): string
    {
        return number_format((float) $order->total_amount, 2).' EGP';
    }

    public function orderCreated(Order $order): void
    {
        $this->safely(function () use ($order) {
            $needsApproval = $order->status === 'pending_approval';
            $this->send($this->orderAudience($order), [
                'type' => $needsApproval ? 'order.needs_approval' : 'order.created',
                'icon' => $needsApproval ? 'fa-user-check' : 'fa-receipt',
                'title' => ($needsApproval ? 'Order waiting for approval' : 'New order').' · #'.$this->number($order),
                'body' => $this->who($order).' · '.$this->money($order),
                'url' => $this->orderUrl($order),
            ]);
        });
    }

    public function orderStatusChanged(Order $order, ?string $from): void
    {
        $this->safely(function () use ($order, $from) {
            if ($order->status === 'paid') {
                $this->send($this->orderAudience($order, false), [
                    'type' => 'order.paid', 'icon' => 'fa-circle-check',
                    'title' => 'Payment received · #'.$this->number($order),
                    'body' => $this->who($order).' · '.$this->money($order),
                    'url' => $this->orderUrl($order),
                ]);
            } elseif ($order->status === 'pending_approval' && $from !== null) {
                $this->send($this->orderAudience($order), [
                    'type' => 'order.needs_approval', 'icon' => 'fa-user-check',
                    'title' => 'Order waiting for approval · #'.$this->number($order),
                    'body' => $this->who($order).' · '.$this->money($order),
                    'url' => $this->orderUrl($order),
                ]);
            }
        });
    }

    public function orderAssigned(Order $order): void
    {
        $this->safely(function () use ($order) {
            $to = $order->assigned_user_id ? User::query()->find($order->assigned_user_id) : null;
            if (! $to || $to->id === auth()->id()) {
                return;   // unassigned, or someone assigned an order to themselves
            }
            $this->send([$to], [
                'type' => 'order.assigned', 'icon' => 'fa-user-check',
                'title' => 'Order assigned to you · #'.$this->number($order),
                'body' => $this->who($order).' · '.$this->money($order),
                'url' => $this->orderUrl($order),
            ]);
        });
    }

    /** Extra agents added to an order (the primary assignee is notified by orderAssigned()). @param list<User> $users */
    public function orderAssignedTo(Order $order, array $users): void
    {
        $this->safely(function () use ($order, $users) {
            $to = array_values(array_filter($users, fn (User $u) => $u->id !== auth()->id()));
            if ($to === []) {
                return;
            }
            $this->send($to, [
                'type' => 'order.assigned', 'icon' => 'fa-user-check',
                'title' => 'Order assigned to you · #'.$this->number($order),
                'body' => $this->who($order).' · '.$this->money($order),
                'url' => $this->orderUrl($order),
            ]);
        });
    }

    public function refundRequested(PaymentRefund $refund): void
    {
        $this->safely(function () use ($refund) {
            if (! $refund->awaiting_approval) {
                return;
            }
            $order = $refund->order_id ? Order::query()->find($refund->order_id) : null;
            $audience = $this->staff()->filter(fn (User $u) => $u->can('payments.refunds.approve') && (! $order || $order->isVisibleTo($u)));
            $this->send($audience, [
                'type' => 'refund.requested', 'icon' => 'fa-rotate-left',
                'title' => 'Refund waiting for approval'.($order ? ' · #'.$this->number($order) : ''),
                'body' => number_format((float) $refund->amount(), 2).' '.($refund->currency ?: 'EGP'),
                'url' => $order ? $this->orderUrl($order) : route('admin.payments.refunds', [], false),
            ]);
        });
    }
}
