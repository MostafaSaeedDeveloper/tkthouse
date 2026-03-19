<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\OrderItem;
use App\Models\Ticket;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class ReportController extends Controller
{
    public function event(Request $request, Event $event)
    {
        $managedEvent = $request->user()?->managedEvent;
        if ($managedEvent) {
            abort_unless((int) $managedEvent->id === (int) $event->id, 403);
        }

        return $this->index($request, $event);
    }

    public function index(Request $request, ?Event $forcedEvent = null)
    {
        $managedEvent = $request->user()?->managedEvent;
        if ($managedEvent && $forcedEvent === null) {
            $forcedEvent = $managedEvent;
        }

        $selectedRange = (string) $request->input('range', 'last30');
        $allowedRanges = ['today', 'yesterday', 'last7', 'last30', 'this_month', 'last_month', 'all', 'custom'];
        if (! in_array($selectedRange, $allowedRanges, true)) {
            $selectedRange = 'last30';
        }

        [$startAt, $endAt, $rangeLabel] = $this->resolveRange($request, $selectedRange);

        $selectedEvent = $forcedEvent?->name ?? trim((string) $request->input('event', ''));
        $eventNames = $this->reportEventNames($forcedEvent, $selectedEvent);

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

        if ($selectedEvent !== '') {
            $itemsQuery->where(function ($query) use ($selectedEvent) {
                $query->where('ticket_name', 'like', $selectedEvent.' - %')
                    ->orWhere('ticket_name', $selectedEvent);
            });
        }

        $items = $itemsQuery
            ->get([
                'order_id',
                'ticket_name',
                'quantity',
                'line_total',
                'holder_gender',
            ]);

        $soldItems = $this->paidSoldOrderItems($startAt, $endAt, $selectedEvent);
        $paidTicketsSoldByEvent = $this->paidTicketsSoldByEvent($soldItems, $eventNames, $selectedEvent !== '' ? $selectedEvent : null);
        $normalizedItems = $this->normalizeItems($items, $eventNames, $selectedEvent !== '' ? $selectedEvent : null);
        $eventOptions = $normalizedItems
            ->pluck('event_name')
            ->filter()
            ->unique()
            ->sort()
            ->values();

        if ($forcedEvent === null && $selectedEvent !== '' && ! $eventOptions->contains($selectedEvent)) {
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
            $guestTicketsQuery->where(function ($query) use ($selectedEvent) {
                $query->where('name', 'like', $selectedEvent.' - %')
                    ->orWhere('name', $selectedEvent);
            });
        }

        $guestStatsByEvent = $guestTicketsQuery
            ->get(['name', 'status', 'holder_gender'])
            ->map(function (Ticket $ticket) use ($eventNames, $selectedEvent) {
                [$eventName] = $this->resolveEventAndTicketType((string) ($ticket->name ?? ''), $eventNames, $selectedEvent !== '' ? $selectedEvent : null);

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

        $paidCheckedInTicketsQuery = Ticket::query()
            ->where('status', 'checked_in')
            ->where(function ($query) {
                $query->whereNull('source')->orWhere('source', '!=', 'guest_list');
            });

        if ($startAt && $endAt) {
            $paidCheckedInTicketsQuery->whereBetween('checked_in_at', [$startAt, $endAt]);
        }

        if ($selectedEvent !== '') {
            $paidCheckedInTicketsQuery->where(function ($query) use ($selectedEvent) {
                $query->where('name', 'like', $selectedEvent.' - %')
                    ->orWhere('name', $selectedEvent);
            });
        }

        $paidCheckedInByEvent = $paidCheckedInTicketsQuery
            ->get(['name'])
            ->map(function (Ticket $ticket) use ($eventNames, $selectedEvent) {
                [$eventName] = $this->resolveEventAndTicketType((string) ($ticket->name ?? ''), $eventNames, $selectedEvent !== '' ? $selectedEvent : null);

                return [
                    'event_name' => $eventName,
                ];
            })
            ->filter(fn (array $item) => $item['event_name'] !== '')
            ->groupBy('event_name')
            ->map(fn (Collection $tickets) => $tickets->count());

        $eventReports = $this->buildEventReports($filteredItems, $guestStatsByEvent, $paidCheckedInByEvent, $paidTicketsSoldByEvent);

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
            'forcedEvent' => $forcedEvent,
            'reportTitle' => $forcedEvent ? $forcedEvent->name.' Report' : 'Reports',
            'reportDescription' => $forcedEvent ? 'Dedicated report for this event only.' : 'Detailed per-event performance, tickets and revenue.',
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



    private function paidSoldOrderItems(?Carbon $startAt, ?Carbon $endAt, string $selectedEvent = ''): Collection
    {
        $query = OrderItem::query()
            ->whereHas('order', function ($query) use ($startAt, $endAt) {
                $query->where('status', 'paid');

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

        if ($selectedEvent !== '') {
            $query->where(function ($inner) use ($selectedEvent) {
                $inner->where('ticket_name', 'like', $selectedEvent.' - %')
                    ->orWhere('ticket_name', $selectedEvent);
            });
        }

        return $query->get(['ticket_name', 'quantity']);
    }

    private function paidTicketsSoldByEvent(Collection $items, Collection $eventNames, ?string $selectedEvent = null): Collection
    {
        return $items
            ->map(function (OrderItem $item) use ($eventNames, $selectedEvent) {
                [$eventName] = $this->resolveEventAndTicketType((string) $item->ticket_name, $eventNames, $selectedEvent);

                return [
                    'event_name' => $eventName,
                    'quantity' => (int) $item->quantity,
                ];
            })
            ->filter(fn (array $item) => $item['event_name'] !== '')
            ->groupBy('event_name')
            ->map(fn (Collection $eventItems) => (int) $eventItems->sum('quantity'));
    }

    private function normalizeItems(Collection $items, Collection $eventNames, ?string $selectedEvent = null): Collection
    {
        $orderLineTotals = $items
            ->groupBy('order_id')
            ->map(fn (Collection $orderItems) => (float) $orderItems->sum('line_total'));

        return $items
            ->map(function (OrderItem $item) use ($orderLineTotals, $eventNames, $selectedEvent) {
                [$eventName, $ticketType] = $this->resolveEventAndTicketType((string) $item->ticket_name, $eventNames, $selectedEvent);

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


    private function buildEventReports(Collection $items, Collection $guestStatsByEvent, Collection $paidCheckedInByEvent, Collection $paidTicketsSoldByEvent): Collection
    {
        $reports = $items
            ->groupBy('event_name')
            ->map(function (Collection $eventItems, string $eventName) use ($guestStatsByEvent, $paidCheckedInByEvent, $paidTicketsSoldByEvent) {
                $ticketsSold = (int) ($paidTicketsSoldByEvent->get($eventName, 0));
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
                    'paid_checked_in' => (int) ($paidCheckedInByEvent->get($eventName, 0)),
                    'total_checked_in' => (int) ($paidCheckedInByEvent->get($eventName, 0)) + (int) $guestStats['guest_checked_in'],
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
                'paid_checked_in' => (int) ($paidCheckedInByEvent->get($eventName, 0)),
                'total_checked_in' => (int) ($paidCheckedInByEvent->get($eventName, 0)) + (int) ($guestStats['guest_checked_in'] ?? 0),
                'gross_revenue' => 0,
                'ticket_types' => collect(),
            ]);
        }

        return $reports
            ->sortByDesc(fn (array $report) => $report['tickets_sold'] + $report['guest_invitations'])
            ->values();
    }

    private function reportEventNames(?Event $forcedEvent = null, string $selectedEvent = ''): Collection
    {
        if ($forcedEvent) {
            return collect([$forcedEvent->name]);
        }

        if ($selectedEvent !== '') {
            return collect([$selectedEvent]);
        }

        return Event::query()
            ->pluck('name')
            ->filter(fn (?string $name) => filled($name))
            ->unique()
            ->sortByDesc(fn (string $name) => mb_strlen($name))
            ->values();
    }

    private function resolveEventAndTicketType(string $ticketName, Collection $eventNames, ?string $selectedEvent = null): array
    {
        if ($selectedEvent !== null && $selectedEvent !== '') {
            return [$selectedEvent, $this->ticketTypeFromEventName($ticketName, $selectedEvent)];
        }

        foreach ($eventNames as $eventName) {
            if ($ticketName === $eventName || Str::startsWith($ticketName, $eventName.' - ')) {
                return [$eventName, $this->ticketTypeFromEventName($ticketName, $eventName)];
            }
        }

        if ($eventNames->count() === 1) {
            $eventName = (string) $eventNames->first();

            return [$eventName, $ticketName !== '' ? $ticketName : 'General'];
        }

        $parts = array_map('trim', explode(' - ', $ticketName, 2));

        if (count($parts) === 1) {
            return [$parts[0], 'General'];
        }

        return [$parts[0], $parts[1] ?: 'General'];
    }

    private function ticketTypeFromEventName(string $ticketName, string $eventName): string
    {
        if ($ticketName === $eventName) {
            return 'General';
        }

        return trim((string) Str::after($ticketName, $eventName.' - ')) ?: 'General';
    }
}
