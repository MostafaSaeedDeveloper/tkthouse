<?php

namespace App\Orders;

use App\Models\Order;
use App\Models\OrderAssignment;
use App\Models\User;
use App\Support\SystemSettings;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * Assignment writes. Every change is one transaction that (1) re-reads the order under a row lock, (2) updates the order,
 * (3) appends a history row. Payment accounts / payment attempts are never touched here.
 */
class OrderAssignmentService
{
    /** Users who may receive new orders: opted in, active, and holding an order-handling permission. */
    public function eligibleUsers()
    {
        return User::query()
            ->where('is_active', true)->where('can_receive_orders', true)
            ->get()
            ->filter(fn (User $u) => $u->can('orders.view_assigned') || $u->can('orders.view_all') || OrderAccess::isSuper($u))
            ->sortBy('id')->values();
    }

    public function isEligible(User $user): bool
    {
        return $user->is_active && $user->can_receive_orders
            && ($user->can('orders.view_assigned') || $user->can('orders.view_all') || OrderAccess::isSuper($user));
    }

    /**
     * Manual (re)assignment by staff. $to = null unassigns.
     *
     * @throws ValidationException
     */
    public function assign(Order $order, ?User $to, User $by, ?string $reason = null, string $source = 'manual'): Order
    {
        if (! OrderAccess::enabled()) {
            throw ValidationException::withMessages(['assignee' => 'Order assignment is disabled.']);
        }
        if ($to && ! $this->isEligible($to)) {
            throw ValidationException::withMessages(['assignee' => 'That user cannot receive orders (inactive or not enabled for orders).']);
        }

        return DB::transaction(function () use ($order, $to, $by, $reason, $source) {
            $locked = Order::withTrashed()->lockForUpdate()->findOrFail($order->id);
            if ((int) $locked->assigned_user_id === (int) ($to?->id ?? 0) && ($to !== null || $locked->assigned_user_id === null)) {
                return $locked; // no change
            }
            $this->write($locked, $to, $by, $source, $reason);

            return $locked;
        });
    }

    /** Final assignment of an order to the agent its customer was shown (see CheckoutRoutingService::finalize). */
    public function assignFromReservation(Order $order, User $to, string $source): void
    {
        $locked = Order::withTrashed()->lockForUpdate()->findOrFail($order->id);
        if ($locked->assigned_user_id) {
            return;
        }
        $this->write($locked, $to, null, $source, null);
        $order->setRawAttributes($locked->getAttributes(), true);
    }

    /** Called when an order is created. Never throws: a failure leaves the order unassigned and checkout unaffected. */
    public function assignOnCreate(Order $order): void
    {
        try {
            if (! OrderAccess::enabled() || $order->assigned_user_id) {
                return;
            }

            $mode = (string) SystemSettings::get('order_assignment_mode', 'manual');
            $target = null;
            $source = $mode;

            DB::transaction(function () use ($order, $mode, &$target) {
                if ($mode === 'default_assignee') {
                    $id = (int) SystemSettings::get('order_default_assignee_id', 0);
                    $user = $id ? User::query()->find($id) : null;
                    $target = $user && $this->isEligible($user) ? $user : null; // ineligible default => queue, never someone else
                } elseif ($mode === 'round_robin') {
                    $target = $this->nextRoundRobin();
                }

                $locked = Order::query()->lockForUpdate()->find($order->id);
                if (! $locked || $locked->assigned_user_id) {
                    $target = null;

                    return;
                }
                if ($target) {
                    $this->write($locked, $target, null, $mode, null);
                    $order->setRawAttributes($locked->getAttributes(), true);
                }
            });
        } catch (\Throwable $e) {
            Log::warning('Automatic order assignment failed; order left unassigned.', ['order_id' => $order->id, 'error' => $e->getMessage()]);
        }
    }

    /** Must run inside a transaction: the single state row is locked so concurrent orders get distinct turns. */
    private function nextRoundRobin(): ?User
    {
        $eligible = $this->eligibleUsers();
        if ($eligible->isEmpty()) {
            return null;
        }

        $state = DB::table('assignment_state')->lockForUpdate()->first();
        if (! $state) {
            DB::table('assignment_state')->insert(['last_assigned_user_id' => null, 'updated_at' => now()]);
            $state = DB::table('assignment_state')->lockForUpdate()->first();
        }

        $next = $eligible->first(fn (User $u) => $u->id > (int) $state->last_assigned_user_id) ?? $eligible->first();
        DB::table('assignment_state')->where('id', $state->id)->update(['last_assigned_user_id' => $next->id, 'updated_at' => now()]);

        return $next;
    }

    private function write(Order $order, ?User $to, ?User $by, string $source, ?string $reason): void
    {
        $from = $order->assigned_user_id ? User::query()->find($order->assigned_user_id) : null;

        $order->forceFill([
            'assigned_user_id' => $to?->id,
            'assigned_by_user_id' => $by?->id,
            'assigned_at' => $to ? now() : null,
        ])->saveQuietly();

        OrderAssignment::create([
            'order_id' => $order->id,
            'from_user_id' => $from?->id, 'to_user_id' => $to?->id, 'changed_by_user_id' => $by?->id,
            'source' => $source, 'reason' => $reason ? mb_substr($reason, 0, 300) : null,
            'meta' => ['from' => $from?->name, 'to' => $to?->name, 'by' => $by?->name],
        ]);

        activity('orders')->performedOn($order)->causedBy($by)->withProperties(['from' => $from?->name, 'to' => $to?->name, 'source' => $source, 'reason' => $reason])
            ->log($to ? ($from ? 'Order reassigned' : 'Order assigned') : 'Order unassigned');
    }

    /**
     * Sales attribution snapshot, written once inside the transaction that confirms payment. Never overwritten:
     * later reassignment cannot move historical sales.
     */
    public function snapshotSale(Order $order): void
    {
        if ($order->sold_at !== null || ! OrderAccess::enabled()) {
            return;
        }

        $order->forceFill(['sold_by_user_id' => $order->assigned_user_id, 'sold_at' => now()])->saveQuietly();
    }
}
