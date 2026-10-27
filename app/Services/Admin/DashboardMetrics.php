<?php

namespace App\Services\Admin;

use App\Models\Event;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\PaymentMethod;
use App\Models\Ticket;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Numbers for the admin dashboard, all computed from real rows. Rules:
 *  - orders flagged "exclude from statistics" never count (same as every other report);
 *  - "sales" are PAID orders only, dated by paid_at (created_at as the fallback for old rows);
 *  - pending / refunded value is shown separately and never mixed into sales;
 *  - no conversion rate: the system does not record visits, so it cannot be computed honestly.
 */
class DashboardMetrics
{
    /** Order value without checkout fees: tickets minus promo discount (older rows without a subtotal fall back to the total). */
    private const NET = 'CASE WHEN subtotal_amount > 0 THEN subtotal_amount - COALESCE(discount_amount, 0) ELSE total_amount END';

    public const RANGES = ['7d' => 'Last 7 days', '30d' => 'Last 30 days', '3m' => 'Last 3 months', '1y' => 'Last year', 'custom' => 'Custom'];

    /** @param array<int> $managedEventIds events this user is limited to (empty = all) */
    public function __construct(private readonly array $managedEventIds = [], private readonly ?Event $event = null, private readonly ?\App\Models\User $user = null, private readonly array $workEventIds = [])
    {
    }

    /** @return array{0: Carbon, 1: Carbon, 2: string, 3: string} start, end, key, label */
    public static function resolveRange(?string $range, ?string $from, ?string $to): array
    {
        $now = now();
        $range = array_key_exists((string) $range, self::RANGES) ? (string) $range : '30d';

        if ($range === 'custom') {
            try {
                $start = Carbon::parse((string) $from)->startOfDay();
                $end = Carbon::parse((string) $to)->endOfDay();
                if ($start->gt($end)) {
                    [$start, $end] = [$end->copy()->startOfDay(), $start->copy()->endOfDay()];
                }
                if ($start->diffInDays($end) > 800) {
                    $start = $end->copy()->subDays(800)->startOfDay();
                }

                return [$start, $end, 'custom', $start->format('d M Y').' – '.$end->format('d M Y')];
            } catch (\Throwable) {
                $range = '30d';
            }
        }

        $start = match ($range) {
            '7d' => $now->copy()->subDays(6)->startOfDay(),
            '3m' => $now->copy()->subMonths(3)->addDay()->startOfDay(),
            '1y' => $now->copy()->subYear()->addDay()->startOfDay(),
            default => $now->copy()->subDays(29)->startOfDay(),
        };

        return [$start, $now->copy()->endOfDay(), $range, self::RANGES[$range]];
    }

    /** Orders limited to this user's scope (managed events / the selected event) and to statistics-included ones. */
    private function orders(): Builder
    {
        $q = Order::query()->includedInStatistics()->assignedScope($this->user);
        $ids = $this->event ? [$this->event->id] : $this->managedEventIds;
        if ($ids) {
            $q->whereHas('items', fn ($i) => $i->whereIn('event_id', $ids));
        }

        return $q;
    }

    private function paidInRange(Carbon $start, Carbon $end): Builder
    {
        return $this->orders()->where('status', 'paid')->where(function ($q) use ($start, $end) {
            $q->whereBetween('paid_at', [$start, $end])->orWhere(fn ($f) => $f->whereNull('paid_at')->whereBetween('created_at', [$start, $end]));
        });
    }

    /** Events for lists/counters: the selected one, else the managed ones, else (order-scoped users) the events they work in. */
    private function eventIds(): array
    {
        return $this->event ? [$this->event->id] : ($this->managedEventIds ?: $this->workEventIds);
    }

    public function kpis(Carbon $start, Carbon $end): array
    {
        $days = max(1, $start->diffInDays($end) + 1);
        $prevEnd = $start->copy()->subSecond();
        $prevStart = $start->copy()->subDays($days);

        $sales = (float) $this->paidInRange($start, $end)->sum(DB::raw(self::NET));
        $prevSales = (float) $this->paidInRange($prevStart, $prevEnd)->sum(DB::raw(self::NET));
        $tickets = $this->ticketsSold($start, $end);
        $prevTickets = $this->ticketsSold($prevStart, $prevEnd);
        $paidOrders = $this->paidInRange($start, $end)->count();

        $activeEvents = Event::query()->where('status', 'active')
            ->when($this->eventIds(), fn ($q, $ids) => $q->whereIn('id', $ids))->count();

        return [
            'sales' => $sales, 'sales_change' => self::change($sales, $prevSales),
            'tickets' => $tickets, 'tickets_change' => self::change($tickets, $prevTickets),
            'active_events' => $activeEvents,
            'paid_orders' => $paidOrders,
            'pending_orders' => $this->orders()->whereIn('status', ['pending_payment', 'pending_approval', 'pending', 'on_hold'])->whereBetween('created_at', [$start, $end])->count(),
        ];
    }

    private function ticketsSold(Carbon $start, Carbon $end): int
    {
        return (int) OrderItem::query()
            ->when($this->eventIds(), fn ($q, $ids) => $q->whereIn('event_id', $ids))
            ->whereHas('order', function ($o) use ($start, $end) {
                $o->where('status', 'paid')->includedInStatistics()->assignedScope($this->user)->where(function ($q) use ($start, $end) {
                    $q->whereBetween('paid_at', [$start, $end])->orWhere(fn ($f) => $f->whereNull('paid_at')->whereBetween('created_at', [$start, $end]));
                });
            })->sum('quantity');
    }

    /** Percentage change vs the previous period; null when there is nothing to compare with (never a made-up number). */
    private static function change(float|int $now, float|int $before): ?float
    {
        return $before > 0 ? round((($now - $before) / $before) * 100, 1) : null;
    }

    /** @return array{labels: array<string>, sales: array<float>, orders: array<int>, bucket: string} */
    public function series(Carbon $start, Carbon $end): array
    {
        $days = $start->diffInDays($end) + 1;
        $bucket = $days <= 31 ? 'day' : ($days <= 120 ? 'week' : 'month');
        $key = fn (Carbon $d) => match ($bucket) {
            'day' => $d->format('Y-m-d'),
            'week' => $d->copy()->startOfWeek()->format('Y-m-d'),
            default => $d->format('Y-m'),
        };

        $paid = $this->paidInRange($start, $end)->selectRaw('paid_at, created_at, '.self::NET.' as net_amount')->get();
        $created = $this->orders()->whereBetween('created_at', [$start, $end])->get(['created_at']);

        $labels = [];
        $slots = [];
        $cursor = match ($bucket) { 'day' => $start->copy()->startOfDay(), 'week' => $start->copy()->startOfWeek(), default => $start->copy()->startOfMonth() };
        while ($cursor->lte($end)) {
            $k = $key($cursor);
            $slots[$k] = ['sales' => 0.0, 'orders' => 0];
            $labels[] = match ($bucket) { 'day' => $cursor->format('d M'), 'week' => $cursor->format('d M'), default => $cursor->format('M Y') };
            $cursor = match ($bucket) { 'day' => $cursor->addDay(), 'week' => $cursor->addWeek(), default => $cursor->addMonthNoOverflow() };
        }
        foreach ($paid as $o) {
            $k = $key(Carbon::parse($o->paid_at ?? $o->created_at));
            if (isset($slots[$k])) {
                $slots[$k]['sales'] += (float) $o->net_amount;
            }
        }
        foreach ($created as $o) {
            $k = $key(Carbon::parse($o->created_at));
            if (isset($slots[$k])) {
                $slots[$k]['orders']++;
            }
        }

        return [
            'labels' => $labels,
            'sales' => array_map(fn ($s) => round($s['sales'], 2), array_values($slots)),
            'orders' => array_map(fn ($s) => $s['orders'], array_values($slots)),
            'bucket' => $bucket,
        ];
    }

    /** Paid / pending / refunded value, and paid value by payment method. Never merged into one "revenue" figure. */
    public function revenue(Carbon $start, Carbon $end): array
    {
        $paid = $this->paidInRange($start, $end)->selectRaw('payment_method, '.self::NET.' as net_amount')->get();
        $labels = PaymentMethod::query()->get(['code', 'name', 'checkout_label'])->mapWithKeys(fn ($m) => [(string) $m->code => trim((string) ($m->checkout_label ?: $m->name))]);
        $paidTotal = (float) $paid->sum('net_amount');

        $byMethod = $paid->groupBy(fn ($o) => (string) $o->payment_method)->map(function (Collection $rows, string $code) use ($labels, $paidTotal) {
            $amount = (float) $rows->sum('net_amount');

            return ['label' => $code === '' ? 'No method recorded' : ($labels[$code] ?? $code), 'amount' => $amount, 'orders' => $rows->count(), 'share' => $paidTotal > 0 ? round($amount / $paidTotal * 100, 1) : 0];
        })->sortByDesc('amount')->values();

        $window = fn (array $statuses) => (float) $this->orders()->whereIn('status', $statuses)->whereBetween('created_at', [$start, $end])->sum(DB::raw(self::NET));

        return [
            'paid' => $paidTotal,
            'by_method' => $byMethod,
            'pending' => $window(['pending_payment', 'pending_approval', 'pending', 'on_hold']),
            'refunded' => $window(['refunded', 'partially_refunded']),
        ];
    }

    public function upcomingEvents(int $limit = 4): Collection
    {
        $events = Event::query()
            ->whereIn('status', ['active', 'sold_out'])
            ->whereDate('event_date', '>=', now()->toDateString())
            ->when($this->eventIds(), fn ($q, $ids) => $q->whereIn('id', $ids))
            ->orderBy('event_date')->limit($limit)->get();

        $sold = OrderItem::query()->selectRaw('event_id, SUM(quantity) as qty')
            ->whereIn('event_id', $events->pluck('id'))
            ->whereHas('order', fn ($o) => $o->where('status', 'paid')->includedInStatistics()->assignedScope($this->user))
            ->groupBy('event_id')->pluck('qty', 'event_id');

        return $events->each(fn (Event $e) => $e->setAttribute('tickets_sold', (int) ($sold[$e->id] ?? 0)));
    }

    public function recentOrders(int $limit = 6): Collection
    {
        return $this->orders()->with(['customer', 'items.event'])->latest()->limit($limit)->get();
    }

    /** Real check-in numbers (tickets of the scoped upcoming/selected events), or null when there is nothing to show. */
    public function scans(): ?array
    {
        $q = Ticket::query()->where('status', '!=', 'canceled');
        if ($this->user && \App\Orders\OrderAccess::scoped($this->user)) {
            $q->whereHas('order', fn ($o) => $o->assignedScope($this->user));
        }
        if ($this->event) {
            $q->where('event_id', $this->event->id);
        } elseif ($ids = ($this->managedEventIds ?: $this->workEventIds)) {
            $q->whereIn('event_id', $ids);
        } else {
            $q->whereIn('event_id', Event::query()->whereDate('event_date', '>=', now()->toDateString())->pluck('id'));
        }

        $total = (clone $q)->count();
        if ($total === 0) {
            return null;
        }
        $checked = (clone $q)->where('status', 'checked_in')->count();

        return ['total' => $total, 'checked_in' => $checked, 'remaining' => $total - $checked, 'percent' => (int) round($checked / $total * 100)];
    }
}
