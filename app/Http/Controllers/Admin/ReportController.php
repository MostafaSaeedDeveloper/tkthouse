<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\OrderItem;
use App\Models\Ticket;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class ReportController extends Controller
{
    public function index(Request $request)
    {
        $selectedRange = (string) $request->input('range', 'last30');
        $allowedRanges = ['today', 'yesterday', 'last7', 'last30', 'this_month', 'last_month', 'all', 'custom'];
        if (! in_array($selectedRange, $allowedRanges, true)) {
            $selectedRange = 'last30';
        }

        [$startAt, $endAt, $rangeLabel] = $this->resolveRange($request, $selectedRange);

        $itemsQuery = OrderItem::query()
            ->with(['order:id,created_at,status,total_amount,paid_at,exclude_from_statistics'])
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

        $items = $itemsQuery
            ->get([
                'order_id',
                'ticket_name',
                'quantity',
                'line_total',
                'holder_gender',
            ]);

        $normalizedItems = $this->normalizeItems($items);
        $eventOptions = $normalizedItems
            ->pluck('event_name')
            ->filter()
            ->unique()
            ->sort()
            ->values();

        $selectedEvent = trim((string) $request->input('event', ''));

        if ($selectedEvent !== '' && ! $eventOptions->contains($selectedEvent)) {
            $selectedEvent = '';
        }


        $filteredItems = $selectedEvent === ''
            ? $normalizedItems
            : $normalizedItems->where('event_name', $selectedEvent)->values();


        $guestTicketsQuery = Ticket::query()->where('source', 'guest_list');
        if ($startAt && $endAt) {
            $guestTicketsQuery->whereBetween('created_at', [$startAt, $endAt]);
        }
        if ($selectedEvent !== '') {
            $guestTicketsQuery->where('name', 'like', $selectedEvent.' - %');
        }

        $guestStatsByEvent = $guestTicketsQuery
            ->get(['name', 'status', 'holder_gender'])
            ->map(function (Ticket $ticket) {
                [$eventName] = $this->extractEventAndTicketType((string) ($ticket->name ?? ''));

                return [
                    'event_name' => $eventName,
                    'status' => (string) ($ticket->status ?? ''),
                    'holder_gender' => strtolower((string) ($ticket->holder_gender ?? '')),
                ];
            })
            ->filter(fn (array $item) => $item['event_name'] !== '')
            ->groupBy('event_name')
            ->map(function (Collection $eventTickets) {
                return [
                    'guest_invitations' => $eventTickets->count(),
                    'guest_checked_in' => $eventTickets->where('status', 'checked_in')->count(),
                ];
            });

        $eventReports = $this->buildEventReports($filteredItems, $guestStatsByEvent);

        return view('admin.reports.index', [
            'eventReports' => $eventReports,
            'totalTickets' => $eventReports->sum('tickets_sold'),
            'totalRevenue' => $eventReports->sum('gross_revenue'),
            'totalOrders' => $filteredItems->pluck('order_id')->filter()->unique()->count(),
            'rangeOptions' => [
                'today' => 'Today',
                'yesterday' => 'Yesterday',
                'last7' => 'Last 7 Days',
                'last30' => 'Last 30 Days',
                'this_month' => 'This Month',
                'last_month' => 'Last Month',
                'all' => 'All Time',
            ],
            'selectedRange' => $selectedRange,
            'rangeLabel' => $rangeLabel,
            'selectedEvent' => $selectedEvent,
            'eventOptions' => $eventOptions,
            'startAt' => $startAt,
            'endAt' => $endAt,
        ]);
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

    private function normalizeItems(Collection $items): Collection
    {
        $orderLineTotals = $items
            ->groupBy('order_id')
            ->map(fn (Collection $orderItems) => (float) $orderItems->sum('line_total'));

        return $items
            ->map(function (OrderItem $item) use ($orderLineTotals) {
                [$eventName, $ticketType] = $this->extractEventAndTicketType((string) $item->ticket_name);

                $orderLineTotal = (float) ($orderLineTotals[$item->order_id] ?? 0);
                $orderTotalAmount = (float) ($item->order?->total_amount ?? 0);
                $grossContribution = $orderLineTotal > 0
                    ? ((float) $item->line_total / $orderLineTotal) * $orderTotalAmount
                    : (float) $item->line_total;

                return [
                    'order_id' => $item->order_id,
                    'event_name' => $eventName,
                    'ticket_type' => $ticketType,
                    'quantity' => (int) $item->quantity,
                    'line_total' => (float) $item->line_total,
                    'gross_contribution' => (float) $grossContribution,
                    'holder_gender' => strtolower((string) ($item->holder_gender ?? '')),
                ];
            })
            ->filter(fn (array $item) => $item['event_name'] !== '')
            ->values();
    }

    private function buildEventReports(Collection $items, Collection $guestStatsByEvent): Collection
    {
        $reports = $items
            ->groupBy('event_name')
            ->map(function (Collection $eventItems, string $eventName) use ($guestStatsByEvent) {
                $ticketsSold = $eventItems->sum('quantity');
                $maleTickets = $eventItems
                    ->filter(fn (array $item) => $item['holder_gender'] === 'male')
                    ->sum('quantity');
                $femaleTickets = $eventItems
                    ->filter(fn (array $item) => $item['holder_gender'] === 'female')
                    ->sum('quantity');

                $guestStats = $guestStatsByEvent->get($eventName, [
                    'guest_invitations' => 0,
                    'guest_checked_in' => 0,
                ]);

                return [
                    'event_name' => $eventName,
                    'tickets_sold' => $ticketsSold,
                    'male_tickets' => $maleTickets,
                    'female_tickets' => $femaleTickets,
                    'guest_invitations' => $guestStats['guest_invitations'],
                    'guest_checked_in' => $guestStats['guest_checked_in'],
                    'gross_revenue' => round((float) $eventItems->sum('gross_contribution'), 2),
                    'ticket_types' => $eventItems
                        ->groupBy('ticket_type')
                        ->map(fn (Collection $tickets, string $type) => [
                            'name' => $type,
                            'count' => $tickets->sum('quantity'),
                        ])
                        ->sortByDesc('count')
                        ->values(),
                ];
            });

        foreach ($guestStatsByEvent as $eventName => $guestStats) {
            if ($reports->has($eventName)) {
                continue;
            }

            $reports->put($eventName, [
                'event_name' => $eventName,
                'tickets_sold' => 0,
                'male_tickets' => 0,
                'female_tickets' => 0,
                'guest_invitations' => $guestStats['guest_invitations'] ?? 0,
                'guest_checked_in' => $guestStats['guest_checked_in'] ?? 0,
                'gross_revenue' => 0,
                'ticket_types' => collect(),
            ]);
        }

        return $reports
            ->sortByDesc(fn (array $report) => $report['tickets_sold'] + $report['guest_invitations'])
            ->values();
    }

    private function extractEventAndTicketType(string $ticketName): array
    {
        $parts = array_map('trim', explode(' - ', $ticketName, 2));

        if (count($parts) === 1) {
            return [$parts[0], 'General'];
        }

        return [$parts[0], $parts[1] ?: 'General'];
    }
}
