<?php

namespace App\Orders;

use App\Models\Order;
use App\Models\User;
use App\Support\SystemSettings;
use Illuminate\Database\Eloquent\Builder;

/**
 * Who may see / act on which order when Order Assignment is enabled. One choke point used by every admin query.
 *
 * Disabled (default): behaves exactly as before — no extra restriction is applied, and nothing here ever *widens* access.
 * Enabled: super admin or `orders.view_all` => everything; `orders.view_assigned` => own orders (+ unassigned when
 * `orders.view_unassigned`); anyone else => nothing (fail closed).
 */
final class OrderAccess
{
    public const ALL = 'all';
    public const ASSIGNED = 'assigned';
    public const NONE = 'none';

    public static function enabled(): bool
    {
        return (bool) SystemSettings::get('order_assignment_enabled', false);
    }

    public static function isSuper(?User $user): bool
    {
        if (! $user) {
            return false;
        }
        $norm = fn (string $v) => strtolower((string) preg_replace('/[^a-z0-9]/i', '', $v));

        return $norm((string) $user->username) === 'superadmin'
            || $user->roles->contains(fn ($r) => $norm($r->name) === 'superadmin');
    }

    public static function level(?User $user): string
    {
        if (! self::enabled()) {
            return self::ALL;
        }
        if (! $user) {
            return self::NONE;
        }
        if (self::isSuper($user) || $user->can('orders.view_all')) {
            return self::ALL;
        }

        // "Can receive assigned orders" ticked by an admin is enough for someone who can open Orders: they see the orders assigned to them.
        return ($user->can('orders.view_assigned') || ($user->can_receive_orders && $user->can('orders.view'))) ? self::ASSIGNED : self::NONE;
    }

    /** May this user be given orders: an order-handling permission, or the "can receive orders" flag on top of orders.view. */
    public static function canHandleOrders(User $user): bool
    {
        return self::isSuper($user) || $user->can('orders.view_all') || $user->can('orders.view_assigned')
            || ($user->can_receive_orders && $user->can('orders.view'));
    }

    /** True when the user is restricted by assignment (global dashboards/reports must not be shown to them). */
    public static function scoped(?User $user): bool
    {
        return self::level($user) !== self::ALL;
    }

    public static function apply(Builder $query, ?User $user, string $table = 'orders'): Builder
    {
        $level = self::level($user);
        if ($level === self::ALL) {
            return $query;
        }
        if ($level === self::NONE) {
            return $query->whereRaw('1 = 0');
        }

        $col = $table.'.assigned_user_id';
        $seeUnassigned = $user->can('orders.view_unassigned');

        // Mine = I am the primary assignee or one of the extra agents on the order.
        return $query->where(fn ($q) => $q->where($col, $user->id)
            ->orWhereExists(fn ($e) => $e->select(\Illuminate\Support\Facades\DB::raw(1))->from('order_assignees')->whereColumn('order_assignees.order_id', $table.'.id')->where('order_assignees.user_id', $user->id))
            ->when($seeUnassigned, fn ($w) => $w->orWhereNull($col)));
    }

    public static function canView(Order $order, ?User $user): bool
    {
        return match (self::level($user)) {
            self::ALL => true,
            self::NONE => false,
            default => $order->isAssignedTo($user) || ($order->assigned_user_id === null && $user->can('orders.view_unassigned')),
        };
    }

    /** Acting on an order (edit/status/notes/approve...). Agents need `orders.manage_assigned` and the order must be theirs. */
    public static function canManage(Order $order, ?User $user): bool
    {
        return match (self::level($user)) {
            self::ALL => true,
            self::NONE => false,
            default => $order->isAssignedTo($user) && $user->can('orders.manage_assigned'),
        };
    }

    /**
     * Who may see assignment details (who an order is assigned to, the assignment history). Agents who only see their own orders
     * must not learn which colleagues are on an order, or that it was assigned at all: only people with the full order view or
     * assignment rights see it.
     */
    public static function canSeeAssignment(?User $user): bool
    {
        return self::enabled() && $user !== null
            && (self::level($user) === self::ALL || self::canAssign($user) || self::canAssign($user, true));
    }

    public static function canAssign(?User $user, bool $reassign = false): bool
    {
        if (! self::enabled() || ! $user) {
            return false;
        }

        return self::isSuper($user) || $user->can($reassign ? 'orders.reassign' : 'orders.assign');
    }
}
