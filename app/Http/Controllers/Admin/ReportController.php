<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\OrderItem;
use Illuminate\Support\Collection;

class ReportController extends Controller
{
    public function index()
    {
        $items = OrderItem::query()
            ->whereHas('order', fn ($query) => $query
                ->where('status', 'paid'))
            ->get([
                'ticket_name',
                'quantity',
                'line_total',
                'holder_gender',
            ]);

        $eventReports = $this->buildEventReports($items);

        return view('admin.reports.index', [
            'eventReports' => $eventReports,
            'totalTickets' => $eventReports->sum('tickets_sold'),
            'totalRevenue' => $eventReports->sum('gross_revenue'),
        ]);
    }

    private function buildEventReports(Collection $items): Collection
    {
        return $items
            ->map(function (OrderItem $item) {
                [$eventName, $ticketType] = $this->extractEventAndTicketType((string) $item->ticket_name);

                return [
                    'event_name' => $eventName,
                    'ticket_type' => $ticketType,
                    'quantity' => (int) $item->quantity,
                    'line_total' => (float) $item->line_total,
                    'holder_gender' => strtolower((string) ($item->holder_gender ?? '')),
                ];
            })
            ->filter(fn (array $item) => $item['event_name'] !== '')
            ->groupBy('event_name')
            ->map(function (Collection $eventItems, string $eventName) {
                $ticketsSold = $eventItems->sum('quantity');
                $maleTickets = $eventItems
                    ->filter(fn (array $item) => $item['holder_gender'] === 'male')
                    ->sum('quantity');
                $femaleTickets = $eventItems
                    ->filter(fn (array $item) => $item['holder_gender'] === 'female')
                    ->sum('quantity');

                return [
                    'event_name' => $eventName,
                    'tickets_sold' => $ticketsSold,
                    'male_tickets' => $maleTickets,
                    'female_tickets' => $femaleTickets,
                    'gross_revenue' => (float) $eventItems->sum('line_total'),
                    'ticket_types' => $eventItems
                        ->groupBy('ticket_type')
                        ->map(fn (Collection $tickets, string $type) => [
                            'name' => $type,
                            'count' => $tickets->sum('quantity'),
                        ])
                        ->sortByDesc('count')
                        ->values(),
                ];
            })
            ->sortByDesc('tickets_sold')
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
