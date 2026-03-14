<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\ScanLog;
use App\Models\Ticket;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class EventInsightsController extends Controller
{
    public function dashboard(Request $request, Event $event)
    {
        $selectedRange = (string) $request->input('range', 'last30');
        $allowedRanges = ['today', 'yesterday', 'last7', 'last30', 'this_month', 'last_month', 'all', 'custom'];
        if (! in_array($selectedRange, $allowedRanges, true)) {
            $selectedRange = 'last30';
        }

        [$startAt, $endAt, $rangeLabel] = $this->resolveRange($request, $selectedRange);

        $paidItemsQuery = $this->eventOrderItemsQuery($event, $startAt, $endAt);
        $paidItems = $paidItemsQuery->get(['order_id', 'ticket_name', 'quantity', 'line_total', 'created_at']);

        $orderTotals = $paidItems
            ->groupBy('order_id')
            ->map(fn (Collection $items) => (float) $items->sum('line_total'));

        $orders = Order::query()
            ->includedInStatistics()
            ->whereIn('id', $paidItems->pluck('order_id')->unique()->filter())
            ->get(['id', 'total_amount']);

        $ordersById = $orders->keyBy('id');

        $grossRevenue = $paidItems->sum(function (OrderItem $item) use ($orderTotals, $ordersById) {
            $orderLineTotal = (float) ($orderTotals[$item->order_id] ?? 0);
            $orderAmount = (float) ($ordersById[$item->order_id]->total_amount ?? 0);

            if ($orderLineTotal <= 0) {
                return (float) $item->line_total;
            }

            return ((float) $item->line_total / $orderLineTotal) * $orderAmount;
        });

        $ticketsSold = (int) $paidItems->sum('quantity');
        $ordersCount = (int) $paidItems->pluck('order_id')->unique()->count();

        $guestTicketsQuery = Ticket::query()
            ->where('source', 'guest_list')
            ->where(function ($query) use ($event) {
                $query->where('name', 'like', $event->name.' - %')
                    ->orWhere('name', $event->name);
            });

        if ($startAt && $endAt) {
            $guestTicketsQuery->whereBetween('created_at', [$startAt, $endAt]);
        }

        $guestInvitations = (clone $guestTicketsQuery)->count();
        $guestCheckedIn = (clone $guestTicketsQuery)->where('status', 'checked_in')->count();

        $paidCheckInsQuery = Ticket::query()
            ->where('status', 'checked_in')
            ->whereBetween('checked_in_at', [$startAt ?? now()->subYears(10), $endAt ?? now()])
            ->where(function ($query) {
                $query->whereNull('source')->orWhere('source', '!=', 'guest_list');
            })
            ->where(function ($query) use ($event) {
                $query->where('name', 'like', $event->name.' - %')
                    ->orWhere('name', $event->name);
            });

        if (! $startAt || ! $endAt) {
            $paidCheckInsQuery = Ticket::query()
                ->where('status', 'checked_in')
                ->where(function ($query) {
                    $query->whereNull('source')->orWhere('source', '!=', 'guest_list');
                })
                ->where(function ($query) use ($event) {
                    $query->where('name', 'like', $event->name.' - %')
                        ->orWhere('name', $event->name);
                });
        }

        $paidCheckedIn = (clone $paidCheckInsQuery)->count();

        $scanLogsQuery = ScanLog::query()->where('event_name', $event->name);
        if ($startAt && $endAt) {
            $scanLogsQuery->whereBetween('scanned_at', [$startAt, $endAt]);
        }

        $totalScans = (clone $scanLogsQuery)->whereIn('action', ['lookup_success', 'status_update'])->count();

        [$labels, $ordersData, $revenueData] = $this->buildAnalyticsSeries($paidItems, $grossRevenue, $startAt, $endAt, $selectedRange);

        $rangeOptions = [
            'today' => 'Today',
            'yesterday' => 'Yesterday',
            'last7' => 'Last 7 Days',
            'last30' => 'Last 30 Days',
            'this_month' => 'This Month',
            'last_month' => 'Last Month',
            'all' => 'All Time',
        ];

        return view('admin.events.dashboard', compact(
            'event',
            'ticketsSold',
            'guestInvitations',
            'guestCheckedIn',
            'grossRevenue',
            'paidCheckedIn',
            'totalScans',
            'ordersCount',
            'labels',
            'ordersData',
            'revenueData',
            'selectedRange',
            'rangeLabel',
            'rangeOptions',
            'startAt',
            'endAt'
        ));
    }

    private function eventOrderItemsQuery(Event $event, ?Carbon $startAt, ?Carbon $endAt)
    {
        return OrderItem::query()
            ->where(function ($query) use ($event) {
                $query->where('ticket_name', 'like', $event->name.' - %')
                    ->orWhere('ticket_name', $event->name);
            })
            ->whereHas('order', function ($query) use ($startAt, $endAt) {
                $query->where('status', 'paid')
                    ->includedInStatistics();

                if ($startAt && $endAt) {
                    $query->where(function ($inner) use ($startAt, $endAt) {
                        $inner->whereBetween('paid_at', [$startAt, $endAt])
                            ->orWhere(function ($fallback) use ($startAt, $endAt) {
                                $fallback->whereNull('paid_at')
                                    ->whereBetween('created_at', [$startAt, $endAt]);
                            });
                    });
                }
            });
    }

    private function buildAnalyticsSeries(Collection $items, float $totalRevenue, ?Carbon $startAt, ?Carbon $endAt, string $selectedRange): array
    {
        if ($items->isEmpty()) {
            return [[], [], []];
        }

        $groupedOrders = $items
            ->groupBy(fn (OrderItem $item) => optional($item->created_at)->format('Y-m-d') ?? now()->format('Y-m-d'))
            ->map(fn (Collection $rows) => $rows->pluck('order_id')->unique()->count());

        $groupedRevenue = $items
            ->groupBy(fn (OrderItem $item) => optional($item->created_at)->format('Y-m-d') ?? now()->format('Y-m-d'))
            ->map(fn (Collection $rows) => (float) $rows->sum('line_total'));

        $period = $this->chartPeriod($startAt, $endAt, $selectedRange);

        $labels = [];
        $ordersData = [];
        $revenueData = [];

        foreach ($period as $day) {
            $key = $day->format('Y-m-d');
            $labels[] = $day->format('M d');
            $ordersData[] = (int) ($groupedOrders[$key] ?? 0);
            $revenueData[] = round((float) ($groupedRevenue[$key] ?? 0), 2);
        }

        return [$labels, $ordersData, $revenueData];
    }

    private function chartPeriod(?Carbon $startAt, ?Carbon $endAt, string $selectedRange): array
    {
        if (! $startAt || ! $endAt) {
            $end = now()->copy()->endOfDay();
            $start = $selectedRange === 'all'
                ? now()->copy()->subDays(29)->startOfDay()
                : now()->copy()->subDays(29)->startOfDay();

            return $this->dailyPeriod($start, $end);
        }

        return $this->dailyPeriod($startAt->copy(), $endAt->copy());
    }

    private function dailyPeriod(Carbon $start, Carbon $end): array
    {
        $period = [];
        $cursor = $start->copy()->startOfDay();

        while ($cursor->lte($end)) {
            $period[] = $cursor->copy();
            $cursor->addDay();
        }

        return $period;
    }

    private function resolveRange(Request $request, string $selectedRange): array
    {
        $now = now();

        return match ($selectedRange) {
            'today' => [$now->copy()->startOfDay(), $now->copy()->endOfDay(), 'Today'],
            'yesterday' => [$now->copy()->subDay()->startOfDay(), $now->copy()->subDay()->endOfDay(), 'Yesterday'],
            'last7' => [$now->copy()->subDays(6)->startOfDay(), $now->copy()->endOfDay(), 'Last 7 Days'],
            'last30' => [$now->copy()->subDays(29)->startOfDay(), $now->copy()->endOfDay(), 'Last 30 Days'],
            'this_month' => [$now->copy()->startOfMonth(), $now->copy()->endOfDay(), 'This Month'],
            'last_month' => [
                $now->copy()->subMonthNoOverflow()->startOfMonth(),
                $now->copy()->subMonthNoOverflow()->endOfMonth(),
                'Last Month',
            ],
            'custom' => $this->resolveCustomRange($request),
            default => [null, null, 'All Time'],
        };
    }

    private function resolveCustomRange(Request $request): array
    {
        $from = $request->input('from');
        $to = $request->input('to');

        if (! $from || ! $to) {
            return [now()->copy()->subDays(29)->startOfDay(), now()->copy()->endOfDay(), 'Last 30 Days'];
        }

        $start = Carbon::parse($from)->startOfDay();
        $end = Carbon::parse($to)->endOfDay();
        if ($start->gt($end)) {
            [$start, $end] = [$end->copy()->startOfDay(), $start->copy()->endOfDay()];
        }

        return [$start, $end, 'Custom Range'];
    }
}
