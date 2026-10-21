<?php

namespace App\Orders;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Per-agent numbers with explicit, separate definitions (never blended):
 *  - Workflow counts (assigned / pending / paid / canceled): by the order's CURRENT assignee, orders created in range.
 *  - Collected sales + tickets sold: by the sales-attribution SNAPSHOT (orders.sold_by_user_id), orders paid in range.
 *    Reassigning an order later never moves a sale. Orders paid before attribution existed are "Unattributed".
 *  - Refunded = refunds that SUCCEEDED in the range for orders the agent sold (snapshot); requested/processing refunds are not counted.
 *  - Not computed (not modelled in the system): merchant settlement (bank payouts).
 * Everything is built on Order::visibleTo($viewer), so an agent can only ever get their own row.
 */
class AgentReport
{
    public const PENDING = ['pending_payment', 'pending_approval'];
    public const CANCELED = ['canceled', 'rejected'];

    /** @return array{rows: Collection, unassigned: array, unattributed: array, totals: array} */
    public function build(User $viewer, CarbonInterface $from, CarbonInterface $to): array
    {
        $visible = fn () => Order::query()->visibleTo($viewer);

        $workflow = $visible()->whereNotNull('assigned_user_id')->whereBetween('created_at', [$from, $to])
            ->select('assigned_user_id', 'status', DB::raw('COUNT(*) as c'))->groupBy('assigned_user_id', 'status')->get();

        $sales = $visible()->where('status', 'paid')->whereNotNull('sold_by_user_id')->whereBetween('paid_at', [$from, $to])
            ->select('sold_by_user_id', DB::raw('COUNT(*) as c'), DB::raw('SUM(total_amount) as amount'))->groupBy('sold_by_user_id')->get()->keyBy('sold_by_user_id');

        $tickets = OrderItem::query()->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->whereIn('orders.id', $visible()->where('status', 'paid')->whereNotNull('sold_by_user_id')->whereBetween('paid_at', [$from, $to])->select('orders.id'))
            ->whereNull('orders.deleted_at')
            ->select('orders.sold_by_user_id', DB::raw('SUM(order_items.quantity) as qty'))->groupBy('orders.sold_by_user_id')->pluck('qty', 'sold_by_user_id');

        $methods = $visible()->where('status', 'paid')->whereNotNull('sold_by_user_id')->whereBetween('paid_at', [$from, $to])
            ->select('sold_by_user_id', 'payment_method', DB::raw('COUNT(*) as c'))->groupBy('sold_by_user_id', 'payment_method')->get()->groupBy('sold_by_user_id');

        // Which merchant account actually collected each attributed sale (paid attempts only; NULL = legacy configuration).
        $accounts = \App\Models\PaymentAttempt::query()->join('orders', 'orders.id', '=', 'payment_attempts.order_id')
            ->where('payment_attempts.status', 'paid')
            ->whereIn('orders.id', $visible()->where('status', 'paid')->whereNotNull('sold_by_user_id')->whereBetween('paid_at', [$from, $to])->select('orders.id'))
            ->select('orders.sold_by_user_id', 'payment_attempts.integration_account_id', DB::raw('COUNT(*) as c'))->groupBy('orders.sold_by_user_id', 'payment_attempts.integration_account_id')->get()->groupBy('sold_by_user_id');

        // Refunds that SUCCEEDED in the range, attributed to the agent who held the sale (sold_by snapshot), never to the current assignee.
        $refunded = \App\Models\PaymentRefund::query()->join('orders', 'orders.id', '=', 'payment_refunds.order_id')
            ->where('payment_refunds.status', 'succeeded')->whereNotNull('orders.sold_by_user_id')
            ->whereIn('orders.id', $visible()->select('orders.id'))
            ->whereBetween(DB::raw('COALESCE(payment_refunds.completed_at, payment_refunds.updated_at)'), [$from, $to])
            ->select('orders.sold_by_user_id', DB::raw('SUM(payment_refunds.amount_minor) m'))->groupBy('orders.sold_by_user_id')->pluck('m', 'sold_by_user_id');

        $ids = $workflow->pluck('assigned_user_id')->merge($sales->keys())->merge($refunded->keys())->unique()->values();
        $users = User::query()->whereIn('id', $ids)->get(['id', 'name'])->keyBy('id');

        $rows = $ids->map(function ($id) use ($workflow, $sales, $tickets, $methods, $users, $accounts, $refunded) {
            $by = $workflow->where('assigned_user_id', $id)->pluck('c', 'status');
            $assigned = (int) $by->sum();
            $paidNow = (int) ($by['paid'] ?? 0);
            $pending = (int) collect(self::PENDING)->sum(fn ($s) => $by[$s] ?? 0);
            $canceled = (int) collect(self::CANCELED)->sum(fn ($s) => $by[$s] ?? 0);
            $closed = $paidNow + $canceled;

            return [
                'user_id' => $id, 'name' => $users[$id]->name ?? '(deleted user)',
                'assigned' => $assigned, 'pending' => $pending, 'paid' => $paidNow, 'canceled' => $canceled,
                'win_rate' => $closed > 0 ? round($paidNow / $closed * 100, 1) : null, // paid ÷ (paid + canceled): closed outcomes only
                'sold_orders' => (int) ($sales[$id]->c ?? 0),
                'collected' => (float) ($sales[$id]->amount ?? 0),
                'tickets' => (int) ($tickets[$id] ?? 0),
                'refunded' => (float) (($refunded[$id] ?? 0) / 100),
                'net' => (float) ($sales[$id]->amount ?? 0) - (float) (($refunded[$id] ?? 0) / 100),
                'accounts' => ($accounts[$id] ?? collect())->mapWithKeys(fn ($m) => [(string) ($m->integration_account_id ?? 0) => (int) $m->c])->all(),
                'methods' => ($methods[$id] ?? collect())->mapWithKeys(fn ($m) => [(string) $m->payment_method => (int) $m->c])->all(),
            ];
        })->sortByDesc('collected')->values();

        return [
            'rows' => $rows,
            'unassigned' => [
                'orders' => (int) $visible()->whereNull('assigned_user_id')->whereBetween('created_at', [$from, $to])->count(),
                'pending' => (int) $visible()->whereNull('assigned_user_id')->whereIn('status', self::PENDING)->whereBetween('created_at', [$from, $to])->count(),
            ],
            'unattributed' => [
                'orders' => (int) $visible()->where('status', 'paid')->whereNull('sold_by_user_id')->whereBetween('paid_at', [$from, $to])->count(),
                'amount' => (float) $visible()->where('status', 'paid')->whereNull('sold_by_user_id')->whereBetween('paid_at', [$from, $to])->sum('total_amount'),
            ],
            'totals' => [
                'assigned' => $rows->sum('assigned'), 'collected' => $rows->sum('collected'), 'tickets' => $rows->sum('tickets'),
            ],
        ];
    }

    /**
     * Event-level view for viewers who only see their own orders: the same orders, rolled up per event, with no mention of
     * assignment. Orders are restricted by visibleTo($viewer) exactly as in build().
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function forEvents(User $viewer, CarbonInterface $from, CarbonInterface $to): Collection
    {
        $items = fn () => OrderItem::query()->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->whereNull('orders.deleted_at')
            ->whereIn('orders.id', Order::query()->visibleTo($viewer)->select('orders.id'));

        $workflow = $items()->whereBetween('orders.created_at', [$from, $to])
            ->select('order_items.event_id', 'orders.status', DB::raw('COUNT(DISTINCT orders.id) as c'))->groupBy('order_items.event_id', 'orders.status')->get();

        $paid = $items()->where('orders.status', 'paid')->whereBetween('orders.paid_at', [$from, $to])
            ->select('order_items.event_id', DB::raw('SUM(order_items.quantity) as qty'), DB::raw('SUM(order_items.line_total) as amount'))->groupBy('order_items.event_id')->get()->keyBy('event_id');

        $methods = $items()->where('orders.status', 'paid')->whereBetween('orders.paid_at', [$from, $to])
            ->select('order_items.event_id', 'orders.payment_method', DB::raw('COUNT(DISTINCT orders.id) as c'))->groupBy('order_items.event_id', 'orders.payment_method')->get()->groupBy('event_id');

        $names = \App\Models\Event::query()->whereIn('id', $workflow->pluck('event_id')->merge($paid->keys())->unique())->pluck('name', 'id');

        return $names->keys()->map(function ($id) use ($workflow, $paid, $methods, $names) {
            $by = $workflow->where('event_id', $id)->pluck('c', 'status');
            $paidNow = (int) ($by['paid'] ?? 0);
            $canceled = (int) collect(self::CANCELED)->sum(fn ($s) => $by[$s] ?? 0);
            $closed = $paidNow + $canceled;

            return [
                'name' => $names[$id],
                'orders' => (int) $by->sum(),
                'pending' => (int) collect(self::PENDING)->sum(fn ($s) => $by[$s] ?? 0),
                'paid' => $paidNow, 'canceled' => $canceled,
                'win_rate' => $closed > 0 ? round($paidNow / $closed * 100, 1) : null,
                'tickets' => (int) ($paid[$id]->qty ?? 0),
                'collected' => (float) ($paid[$id]->amount ?? 0),
                'methods' => ($methods[$id] ?? collect())->mapWithKeys(fn ($m) => [(string) $m->payment_method => (int) $m->c])->all(),
            ];
        })->sortByDesc('collected')->values();
    }

    /** The viewer's own row (zeros when they have none yet). */
    public function mine(User $viewer, CarbonInterface $from, CarbonInterface $to): array
    {
        $data = $this->build($viewer, $from, $to);
        $row = $data['rows']->firstWhere('user_id', $viewer->id) ?? [
            'user_id' => $viewer->id, 'name' => $viewer->name, 'assigned' => 0, 'pending' => 0, 'paid' => 0, 'canceled' => 0, 'win_rate' => null,
            'sold_orders' => 0, 'collected' => 0.0, 'refunded' => 0.0, 'net' => 0.0, 'tickets' => 0, 'methods' => [], 'accounts' => [],
        ];

        return ['row' => $row] + $data;
    }
}
