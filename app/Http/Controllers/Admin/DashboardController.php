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
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        if ($request->user()?->managedEvent) {
            return app(EventInsightsController::class)->dashboard($request, $request->user()->managedEvent);
        }

        $selectedRange = (string) $request->input('range', 'last30');
        $allowedRanges = ['today', 'yesterday', 'last7', 'last30', 'this_month', 'last_month', 'all', 'custom'];
        if (! in_array($selectedRange, $allowedRanges, true)) {
            $selectedRange = 'last30';
        }

        [$startAt, $endAt, $rangeLabel] = $this->resolveRange($request, $selectedRange);
        $eventOptions = Event::query()
            ->orderBy('event_date')
            ->orderBy('name')
            ->get(['id', 'name']);
        $selectedEventId = (int) $request->integer('event_id');
        $selectedEvent = $selectedEventId > 0
            ? $eventOptions->firstWhere('id', $selectedEventId)
            : null;

        $ordersQuery = Order::query()->includedInStatistics();
        $customersQuery = Customer::query();
        $this->applyEventScopeToOrdersQuery($ordersQuery, $selectedEvent);

        if ($startAt && $endAt) {
            $ordersQuery->whereBetween('created_at', [$startAt, $endAt]);
            $customersQuery->whereBetween('created_at', [$startAt, $endAt]);
        }

        $totalOrders = (clone $ordersQuery)->count();
        $paidOrdersQuery = Order::query()->includedInStatistics()->where('status', 'paid');
        $this->applyEventScopeToOrdersQuery($paidOrdersQuery, $selectedEvent);
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
        $this->applyEventScopeToOrderItemsQuery($ticketsSoldQuery, $selectedEvent);
        $ticketsSoldQuery->whereHas('order', function ($query) use ($startAt, $endAt) {
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
        $ticketsSold = (int) $ticketsSoldQuery->sum('quantity');

        $totalCustomers = (clone $customersQuery)->count();
        $totalEvents = $selectedEvent
            ? Event::query()->whereKey($selectedEvent->id)->where('status', 'active')->count()
            : Event::where('status', 'active')->count();


        $guestInvitationsQuery = Ticket::query()->where('source', 'guest_list');
        $this->applyEventScopeToTicketsQuery($guestInvitationsQuery, $selectedEvent);
        if ($startAt && $endAt) {
            $guestInvitationsQuery->whereBetween('created_at', [$startAt, $endAt]);
        }

        $guestInvitations = (clone $guestInvitationsQuery)->count();

        $scanLogsQuery = ScanLog::query();
        $this->applyEventScopeToScanLogsQuery($scanLogsQuery, $selectedEvent);
        if ($startAt && $endAt) {
            $scanLogsQuery->whereBetween('scanned_at', [$startAt, $endAt]);
        }

        $totalScans = (clone $scanLogsQuery)->where('action', 'lookup_success')->count();
        $checkInsCount = (clone $scanLogsQuery)->where('action', 'status_update')->where('new_status', 'checked_in')->count();

        $recentOrders = (clone $ordersQuery)
            ->with(['customer', 'items'])
            ->latest()
            ->take(6)
            ->get();

        $topEventsQuery = OrderItem::query()->select(['ticket_name', 'line_total']);
        $this->applyEventScopeToOrderItemsQuery($topEventsQuery, $selectedEvent);
        $topEventsQuery->whereHas('order', function ($q) use ($startAt, $endAt) {
            $q->where('status', 'paid')
                ->includedInStatistics();

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

        [$labels, $ordersData, $revenueData] = $this->buildChartSeries($startAt, $endAt, $selectedRange, $selectedEvent);

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
            'eventOptions',
            'selectedEvent',
            'selectedEventId',
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

    private function buildChartSeries(?Carbon $startAt, ?Carbon $endAt, string $selectedRange, ?Event $selectedEvent): array
    {
        if (! $startAt || ! $endAt) {
            $startAt = now()->startOfMonth()->subMonths(6);
            $endAt = now()->endOfDay();
        }

        $ordersWindowQuery = Order::query()
            ->includedInStatistics()
            ->whereBetween('created_at', [$startAt, $endAt]);
        $this->applyEventScopeToOrdersQuery($ordersWindowQuery, $selectedEvent);
        $ordersWindow = $ordersWindowQuery->get(['created_at']);

        $revenueWindowQuery = Order::query()
            ->includedInStatistics()
            ->whereBetween('created_at', [$startAt, $endAt]);
        $this->applyEventScopeToOrdersQuery($revenueWindowQuery, $selectedEvent);
        $revenueWindow = $revenueWindowQuery->get(['created_at', 'total_amount']);

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

    private function applyEventScopeToOrdersQuery(Builder $query, ?Event $event): void
    {
        if (! $event) {
            return;
        }

        $query->whereHas('items', function (Builder $itemsQuery) use ($event) {
            $itemsQuery->where('event_id', $event->id);
        });
    }

    private function applyEventScopeToOrderItemsQuery(Builder $query, ?Event $event): void
    {
        if (! $event) {
            return;
        }

        $query->where('event_id', $event->id);
    }

    private function applyEventScopeToTicketsQuery(Builder $query, ?Event $event): void
    {
        if (! $event) {
            return;
        }

        $query->where('event_id', $event->id);
    }

    private function applyEventScopeToScanLogsQuery(Builder $query, ?Event $event): void
    {
        if (! $event) {
            return;
        }

        $query->where('event_id', $event->id);
    }

}
