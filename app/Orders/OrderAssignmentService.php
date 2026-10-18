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

    /**
     * The configured default assignees (several are allowed; the old single-assignee setting still counts until it is replaced).
     * Only agents who are eligible right now; if they no longer share the same payment methods the order waits in the queue
     * instead (the same rule as manual assignment), never someone else.
     *
     * @return \Illuminate\Support\Collection<int, User>
     */
    public function defaultAssignees()
    {
        $ids = array_values(array_filter(array_map('intval', (array) (SystemSettings::get('order_default_assignee_ids') ?: [SystemSettings::get('order_default_assignee_id')]))));
        if ($ids === []) {
            return collect();
        }
        $found = User::query()->whereIn('id', $ids)->get()->keyBy('id');
        $users = collect($ids)->map(fn ($id) => $found[$id] ?? null)->filter(fn ($u) => $u && $this->isEligible($u))->values();

        try {
            $this->assertSamePaymentMethods($users);
        } catch (ValidationException) {
            return collect();
        }

        return $users;
    }

    public function isEligible(User $user): bool
    {
        return $user->is_active && $user->can_receive_orders
            && ($user->can('orders.view_assigned') || $user->can('orders.view_all') || OrderAccess::isSuper($user));
    }

    /**
     * Manual (re)assignment by staff to ONE agent. $to = null unassigns. Replaces any extra agents on the order.
     *
     * @throws ValidationException
     */
    public function assign(Order $order, ?User $to, User $by, ?string $reason = null, string $source = 'manual'): Order
    {
        return $this->assignMany($order, $to ? [$to] : [], $by, $reason, $source);
    }

    /**
     * Several agents on one order. Every agent must be eligible and all of them must share the SAME payment methods
     * (the customer is offered one list, so two agents with different lists would contradict each other). The primary
     * assignee (sales attribution, reports, payment routing) stays the current one while they remain on the order,
     * otherwise it is the first agent of the list. An empty list unassigns.
     *
     * @param  iterable<User>  $users
     * @throws ValidationException
     */
    public function assignMany(Order $order, iterable $users, User $by, ?string $reason = null, string $source = 'manual'): Order
    {
        if (! OrderAccess::enabled()) {
            throw ValidationException::withMessages(['assignee' => 'Order assignment is disabled.']);
        }

        $users = collect($users)->filter()->unique('id')->values();
        foreach ($users as $u) {
            if (! $this->isEligible($u)) {
                throw ValidationException::withMessages(['assignee' => $u->name.' cannot receive orders (inactive or not enabled for orders).']);
            }
        }
        $this->assertSamePaymentMethods($users);

        return DB::transaction(function () use ($order, $users, $by, $reason, $source) {
            $locked = Order::withTrashed()->lockForUpdate()->findOrFail($order->id);
            $current = $locked->assigneeIds();
            $newIds = $users->pluck('id')->map(fn ($id) => (int) $id)->all();

            $primaryId = in_array((int) $locked->assigned_user_id, $newIds, true) ? (int) $locked->assigned_user_id : ($newIds[0] ?? null);
            if ($primaryId === ((int) $locked->assigned_user_id ?: null) && $this->sameIds($current, $newIds)) {
                return $locked; // no change
            }
            $this->write($locked, $users->all(), $primaryId, $by, $source, $reason);

            return $locked;
        });
    }

    /** What an agent's customers are offered: their selected methods, or null = every active method. Order never matters. */
    public function paymentMethodSet(User $user): ?array
    {
        $codes = is_array($user->allowed_payment_methods) ? array_values(array_unique(array_map('strval', $user->allowed_payment_methods))) : [];
        sort($codes);

        return $codes === [] ? null : $codes;
    }

    /** @param \Illuminate\Support\Collection<int, User> $users @throws ValidationException */
    public function assertSamePaymentMethods($users): void
    {
        if ($users->count() < 2) {
            return;
        }
        $first = $users->first();
        $set = $this->paymentMethodSet($first);
        $other = $users->first(fn (User $u) => $this->paymentMethodSet($u) !== $set);
        if ($other) {
            $label = fn (User $u) => $u->name.' ('.($this->paymentMethodSet($u) === null ? 'all methods' : implode(', ', $this->paymentMethodSet($u))).')';
            throw ValidationException::withMessages(['assignee' => 'Agents on the same order must have the same payment methods: '.$label($first).' differs from '.$label($other).'.']);
        }
    }

    private function sameIds(array $a, array $b): bool
    {
        sort($a);
        sort($b);

        return $a === $b;
    }

    /** Final assignment of an order to the agent its customer was shown (see CheckoutRoutingService::finalize). */
    public function assignFromReservation(Order $order, User $to, string $source): void
    {
        $locked = Order::withTrashed()->lockForUpdate()->findOrFail($order->id);
        if ($locked->assigned_user_id) {
            return;
        }
        $users = [$to];
        if ($source === 'default_assignee') {
            // the customer was shown the first default agent; every default agent goes on the order
            $defaults = $this->defaultAssignees();
            if ($defaults->contains('id', $to->id)) {
                $users = $defaults->sortBy(fn (User $u) => $u->id === $to->id ? 0 : 1)->values()->all();
            }
        }
        $this->write($locked, $users, $to->id, null, $source, null);
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
                    $defaults = $this->defaultAssignees(); // none eligible (or mixed payment methods) => queue, never someone else
                    $target = $defaults->first();
                } elseif ($mode === 'round_robin') {
                    $target = $this->nextRoundRobin();
                }

                $locked = Order::query()->lockForUpdate()->find($order->id);
                if (! $locked || $locked->assigned_user_id) {
                    $target = null;

                    return;
                }
                if ($target) {
                    $this->write($locked, $mode === 'default_assignee' ? $this->defaultAssignees()->all() : [$target], $target->id, null, $mode, null);
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

    /**
     * @param  list<User>  $users  every agent that ends up on the order (empty = unassigned)
     * @param  int|null  $primaryId  the primary assignee (one of $users)
     */
    private function write(Order $order, array $users, ?int $primaryId, ?User $by, string $source, ?string $reason): void
    {
        $users = array_values($users);
        $fromIds = $order->assigneeIds();
        $from = $order->assigned_user_id ? User::query()->find($order->assigned_user_id) : null;
        $byId = $fromIds ? User::query()->whereIn('id', $fromIds)->pluck('name', 'id') : collect();
        $fromNames = collect($fromIds)->map(fn ($id) => $byId[$id] ?? null)->filter()->values()->all();
        $primary = $primaryId ? collect($users)->firstWhere('id', $primaryId) : null;

        $order->forceFill([
            'assigned_user_id' => $primary?->id,
            'assigned_by_user_id' => $by?->id,
            'assigned_at' => $primary ? now() : null,
        ])->saveQuietly();

        $keep = collect($users)->pluck('id')->all();
        DB::table('order_assignees')->where('order_id', $order->id)->whereNotIn('user_id', $keep ?: [0])->delete();
        $existing = DB::table('order_assignees')->where('order_id', $order->id)->pluck('user_id')->all();
        foreach ($users as $u) {
            if (in_array($u->id, $existing, true)) {
                continue; // already on the order: keep who added them and when
            }
            DB::table('order_assignees')->insert(['order_id' => $order->id, 'user_id' => $u->id, 'assigned_by_user_id' => $by?->id, 'created_at' => now(), 'updated_at' => now()]);
        }
        $order->unsetRelation('assignees');

        $toNames = collect($users)->sortBy(fn (User $u) => $u->id === $primaryId ? 0 : 1)->pluck('name')->values()->all();
        OrderAssignment::create([
            'order_id' => $order->id,
            'from_user_id' => $from?->id, 'to_user_id' => $primary?->id, 'changed_by_user_id' => $by?->id,
            'source' => $source, 'reason' => $reason ? mb_substr($reason, 0, 300) : null,
            'meta' => ['from' => $fromNames ? implode(', ', $fromNames) : null, 'to' => $toNames ? implode(', ', $toNames) : null, 'by' => $by?->name],
        ]);

        activity('orders')->performedOn($order)->causedBy($by)->withProperties(['from' => $fromNames, 'to' => $toNames, 'source' => $source, 'reason' => $reason])
            ->log($users ? ($fromIds ? 'Order reassigned' : 'Order assigned') : 'Order unassigned');

        // Agents added to the order (the primary is notified by the order observer when it changes).
        $added = collect($users)->reject(fn (User $u) => in_array($u->id, $fromIds, true) || $u->id === $primaryId);
        if ($added->isNotEmpty()) {
            app(\App\Services\Notifications\AdminNotifier::class)->orderAssignedTo($order, $added->all());
        }
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
