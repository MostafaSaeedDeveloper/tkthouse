<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Event;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\ScanLog;
use App\Models\Ticket;
use Carbon\Carbon;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $selectedRange = (string) $request->input('range', 'last30');
        $allowedRanges = ['today', 'yesterday', 'last7', 'last30', 'this_month', 'last_month', 'all', 'custom'];
        if (! in_array($selectedRange, $allowedRanges, true)) {
            $selectedRange = 'last30';
        }

        [$startAt, $endAt, $rangeLabel] = $this->resolveRange($request, $selectedRange);

        $ordersQuery = Order::query()->includedInStatistics();
        $customersQuery = Customer::query();

        if ($startAt && $endAt) {
            $ordersQuery->whereBetween('created_at', [$startAt, $endAt]);
            $customersQuery->whereBetween('created_at', [$startAt, $endAt]);
        }

        $totalOrders = (clone $ordersQuery)->count();
        $paidOrdersQuery = Order::query()->includedInStatistics()->where('status', 'paid');
        if ($startAt && $endAt) {
            $paidOrdersQuery->where(function ($query) use ($startAt, $endAt) {
                $query->whereBetween('paid_at', [$startAt, $endAt])
                    ->orWhere(function ($fallback) use ($startAt, $endAt) {
                        $fallback->whereNull('paid_at')
                            ->whereBetween('created_at', [$startAt, $endAt]);
                    });
            });
        }

        $totalPaidOrders = (clone $paidOrdersQuery)->count();
        $totalRevenue = (float) (clone $ordersQuery)->sum('total_amount');
        $grossRevenue = (float) (clone $paidOrdersQuery)->sum('total_amount');
        $pendingOrders = (clone $ordersQuery)->whereIn('status', ['pending', 'pending_approval', 'pending_payment'])->count();

        $ticketsSoldQuery = OrderItem::query();
        $ticketsSoldQuery->whereHas('order', function ($q) use ($startAt, $endAt) {
            $q->where('status', 'paid');
            if ($startAt && $endAt) {
                $q->where(function ($query) use ($startAt, $endAt) {
                    $query->whereBetween('paid_at', [$startAt, $endAt])
                        ->orWhere(function ($fallback) use ($startAt, $endAt) {
                            $fallback->whereNull('paid_at')
                                ->whereBetween('created_at', [$startAt, $endAt]);
                        });
                });
            }
        });
        $ticketsSold = (int) $ticketsSoldQuery->sum('quantity');

        $totalCustomers = (clone $customersQuery)->count();
        $totalEvents = Event::where('status', 'active')->count();


        $guestInvitationsQuery = Ticket::query()->where('source', 'guest_list');
        if ($startAt && $endAt) {
            $guestInvitationsQuery->whereBetween('created_at', [$startAt, $endAt]);
        }

        $guestInvitations = (clone $guestInvitationsQuery)->count();

        $scanLogsQuery = ScanLog::query();
        if ($startAt && $endAt) {
            $scanLogsQuery->whereBetween('scanned_at', [$startAt, $endAt]);
        }

        $totalScans = (clone $scanLogsQuery)->whereIn('action', ['lookup_success', 'status_update'])->count();
        $checkInsCount = (clone $scanLogsQuery)->where('action', 'status_update')->where('new_status', 'checked_in')->count();

        $recentOrders = (clone $ordersQuery)
            ->with(['customer', 'items'])
            ->latest()
            ->take(6)
            ->get();

        $topEventsQuery = OrderItem::query()->select(['ticket_name', 'line_total']);
        $topEventsQuery->whereHas('order', function ($q) use ($startAt, $endAt) {
            if ($startAt && $endAt) {
                $q->whereBetween('created_at', [$startAt, $endAt]);
            }
        });

        $topEvents = $topEventsQuery->get()
            ->groupBy(function (OrderItem $item) {
                return str_contains($item->ticket_name, ' - ')
                    ? trim((string) strstr($item->ticket_name, ' - ', true))
                    : $item->ticket_name;
            })
            ->map(function ($items, $name) {
                return [
                    'name' => $name,
                    'orders_count' => $items->count(),
                    'revenue' => (float) $items->sum('line_total'),
                ];
            })
            ->sortByDesc('revenue')
            ->take(4)
            ->values();

        [$labels, $ordersData, $revenueData] = $this->buildChartSeries($startAt, $endAt, $selectedRange);

        $rangeOptions = [
            'today' => 'Today',
            'yesterday' => 'Yesterday',
            'last7' => 'Last 7 Days',
            'last30' => 'Last 30 Days',
            'this_month' => 'This Month',
            'last_month' => 'Last Month',
            'all' => 'All Time',
        ];

        return view('admin.index', compact(
            'totalOrders',
            'totalPaidOrders',
            'totalRevenue',
            'grossRevenue',
            'pendingOrders',
            'ticketsSold',
            'guestInvitations',
            'totalScans',
            'checkInsCount',
            'totalCustomers',
            'totalEvents',
            'recentOrders',
            'topEvents',
            'labels',
            'revenueData',
            'ordersData',
            'selectedRange',
            'rangeLabel',
            'rangeOptions',
            'startAt',
            'endAt'
        ));
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

    private function buildChartSeries(?Carbon $startAt, ?Carbon $endAt, string $selectedRange): array
    {
        if (! $startAt || ! $endAt) {
            $startAt = now()->startOfMonth()->subMonths(6);
            $endAt = now()->endOfDay();
        }

        $ordersWindow = Order::query()
            ->includedInStatistics()
            ->whereBetween('created_at', [$startAt, $endAt])
            ->get(['created_at']);

        $revenueWindow = Order::query()
            ->includedInStatistics()
            ->whereBetween('created_at', [$startAt, $endAt])
            ->get(['created_at', 'total_amount']);

        $labels = [];
        $ordersData = [];
        $revenueData = [];

        $diffDays = $startAt->diffInDays($endAt);
        if (in_array($selectedRange, ['today', 'yesterday'], true)) {
            for ($h = 0; $h < 24; $h += 2) {
                $slotStart = $startAt->copy()->hour($h)->minute(0)->second(0);
                $slotEnd = $slotStart->copy()->addHours(2);
                $bucket = $ordersWindow->filter(fn ($o) => Carbon::parse($o->created_at)->betweenIncluded($slotStart, $slotEnd));
                $revenueBucket = $revenueWindow->filter(fn ($o) => Carbon::parse($o->created_at)->betweenIncluded($slotStart, $slotEnd));
                $labels[] = $slotStart->format('H:i');
                $ordersData[] = $bucket->count();
                $revenueData[] = round((float) $revenueBucket->sum('total_amount'), 2);
            }

            return [$labels, $ordersData, $revenueData];
        }

        if ($diffDays <= 31) {
            $cursor = $startAt->copy()->startOfDay();
            while ($cursor->lte($endAt)) {
                $day = $cursor->format('Y-m-d');
                $bucket = $ordersWindow->filter(fn ($o) => Carbon::parse($o->created_at)->format('Y-m-d') === $day);
                $revenueBucket = $revenueWindow->filter(fn ($o) => Carbon::parse($o->created_at)->format('Y-m-d') === $day);
                $labels[] = $cursor->format('d M');
                $ordersData[] = $bucket->count();
                $revenueData[] = round((float) $revenueBucket->sum('total_amount'), 2);
                $cursor->addDay();
            }

            return [$labels, $ordersData, $revenueData];
        }

        $cursor = $startAt->copy()->startOfMonth();
        while ($cursor->lte($endAt)) {
            $month = $cursor->format('Y-m');
            $bucket = $ordersWindow->filter(fn ($o) => Carbon::parse($o->created_at)->format('Y-m') === $month);
            $revenueBucket = $revenueWindow->filter(fn ($o) => Carbon::parse($o->created_at)->format('Y-m') === $month);
            $labels[] = $cursor->format('M Y');
            $ordersData[] = $bucket->count();
            $revenueData[] = round((float) $revenueBucket->sum('total_amount'), 2);
            $cursor->addMonthNoOverflow();
        }

        return [$labels, $ordersData, $revenueData];
    }

}
