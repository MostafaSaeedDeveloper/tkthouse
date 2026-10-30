@extends('admin.master')
@section('title', 'Dashboard')

@php
    $money = fn ($n) => number_format((float) $n, 2).' EGP';
    $moneyShort = fn ($n) => number_format((float) $n, 0).' EGP';
    $first = explode(' ', trim((string) auth()->user()?->name))[0] ?: 'there';
    $trend = function (?float $pct) {
        if ($pct === null) return null;
        return ($pct >= 0 ? '▲ ' : '▼ ').abs($pct).'% vs previous period';
    };
    $rangeParams = array_filter(['event_id' => $selectedEvent?->id]);
@endphp

@push('styles')
<style>
    .a-dash__top { display: flex; align-items: flex-end; justify-content: space-between; gap: 16px; flex-wrap: wrap; margin-bottom: 20px; }
    .a-dash__hello { margin: 0 0 4px; color: var(--a-muted); font-size: 14px; }
    .a-dash__title { font-size: clamp(1.4rem, 1.1rem + 1.2vw, 2rem); font-weight: 800; line-height: 1.15; }
    .a-range { display: flex; gap: 10px; flex-wrap: wrap; align-items: center; }
    .a-seg { display: inline-flex; padding: 4px; background: #fff; border: 1px solid var(--a-line); border-radius: 12px; gap: 2px; }
    .a-seg a, .a-seg button { min-height: 36px; padding: 0 14px; border: 0; border-radius: 9px; background: transparent; font: inherit; font-size: 13px; font-weight: 700; color: var(--a-muted); cursor: pointer; display: inline-flex; align-items: center; }
    .a-seg a:hover, .a-seg button:hover { color: var(--a-ink); }
    .a-seg .is-active { background: var(--a-yellow); color: #000; }
    @media (max-width: 640px) {
        .a-range { width: 100%; }
        .a-seg { display: grid; grid-template-columns: repeat(5, minmax(0, 1fr)); width: 100%; }
        .a-seg a, .a-seg button { padding: 0 2px; justify-content: center; white-space: nowrap; font-size: 12px; }
        .a-range > form { width: 100%; }
        .a-range .form-select { min-width: 0 !important; max-width: none !important; width: 100%; }
        .a-custom.is-open input[type="date"] { flex: 1 1 130px; min-width: 0; }
    }
    .a-custom { display: none; gap: 8px; align-items: center; flex-wrap: wrap; }
    .a-custom.is-open { display: flex; }
    .a-custom input { min-height: 40px; padding: 0 12px; border: 1px solid var(--a-line-strong); border-radius: 10px; font: inherit; font-size: 13px; }
    .a-dash__grid { display: grid; gap: 20px; grid-template-columns: minmax(0, 1fr); align-items: start; }
    @media (min-width: 1280px) { .a-dash__grid { grid-template-columns: minmax(0, 1fr) 380px; } }
    .a-dash__col { display: grid; gap: 20px; min-width: 0; align-content: start; }
    .a-dash__charts { display: grid; gap: 20px; grid-template-columns: minmax(0, 1fr); }
    @media (min-width: 900px) { .a-dash__charts { grid-template-columns: minmax(0, 1.6fr) minmax(0, 1fr); } }
    .a-chartbox { position: relative; height: 260px; }
    .a-chartbox--donut { height: 200px; max-width: 240px; margin: 0 auto; }
    .a-donut-center { position: absolute; inset: 0; display: flex; flex-direction: column; align-items: center; justify-content: center; pointer-events: none; text-align: center; }
    .a-donut-center b { font-size: 18px; font-weight: 800; font-variant-numeric: tabular-nums; }
    .a-donut-center span { font-size: 12px; color: var(--a-muted); }
    .a-legend { list-style: none; margin: 14px 0 0; padding: 0; display: grid; gap: 10px; font-size: 13px; }
    .a-legend li { display: grid; grid-template-columns: auto 1fr auto auto; gap: 10px; align-items: center; }
    .a-legend i { width: 10px; height: 10px; border-radius: 50%; display: inline-block; }
    .a-legend .a-num { font-variant-numeric: tabular-nums; font-weight: 700; }
    .a-split { display: grid; gap: 8px; margin-top: 14px; padding-top: 14px; border-top: 1px solid var(--a-line); font-size: 13px; }
    .a-split div { display: flex; justify-content: space-between; gap: 10px; }
    .a-split b { font-variant-numeric: tabular-nums; }
    .a-event { display: grid; grid-template-columns: 64px minmax(0, 1fr); gap: 12px; align-items: center; padding: 12px 0; border-top: 1px solid var(--a-line); }
    .a-event:first-child { border-top: 0; padding-top: 0; }
    .a-event__img { position: relative; width: 64px; height: 64px; border-radius: 12px; background: #1a1a1a center / cover no-repeat; overflow: hidden; }
    .a-event__date { position: absolute; left: 6px; top: 6px; padding: 2px 6px; border-radius: 6px; background: #fff; color: #111; font-size: 10px; font-weight: 800; line-height: 1.1; text-align: center; }
    .a-event__date b { display: block; font-size: 14px; }
    .a-event__name { font-weight: 800; font-size: 14px; display: block; overflow-wrap: anywhere; }
    .a-event__meta { font-size: 12.5px; color: var(--a-muted); display: block; overflow-wrap: anywhere; }
    .a-ring { --p: 0; width: 132px; height: 132px; border-radius: 50%; background: conic-gradient(#178a4c calc(var(--p) * 1%), #ececE6 0); display: flex; align-items: center; justify-content: center; margin: 0 auto; }
    .a-ring > div { width: 100px; height: 100px; border-radius: 50%; background: #fff; display: flex; flex-direction: column; align-items: center; justify-content: center; }
    .a-ring b { font-size: 22px; font-weight: 800; } .a-ring span { font-size: 11px; color: var(--a-muted); }
    .a-scan-stats { display: grid; gap: 10px; margin-top: 16px; font-size: 13px; }
    .a-scan-stats div { display: flex; justify-content: space-between; gap: 10px; } .a-scan-stats b { font-variant-numeric: tabular-nums; }
    .a-quick { display: grid; gap: 10px; grid-template-columns: 1fr 1fr; }
    .a-quick a { display: grid; gap: 2px; padding: 14px; border: 1px solid var(--a-line); border-radius: 12px; background: var(--a-surface-2); font-weight: 800; font-size: 14px; }
    .a-quick a small { font-weight: 500; font-size: 12px; color: var(--a-muted); }
    .a-quick a i { margin-bottom: 6px; color: #7a5b00; }
    .a-quick a.is-primary { background: var(--a-yellow); border-color: var(--a-yellow); }
    .a-quick a.is-primary i, .a-quick a.is-primary small { color: #000; }
    .a-quick a:hover { border-color: var(--a-ink); }
</style>
@endpush

@section('content')
<div class="a-dash__top">
    <div>
        <p class="a-dash__hello">Welcome back, {{ $first }}</p>
        <h1 class="a-dash__title">Here’s what’s happening with your events</h1>
    </div>
    <div class="a-range">
        <div class="a-seg" role="group" aria-label="Date range">
            @foreach(['7d' => '7 days', '30d' => '30 days', '3m' => '3 months', '1y' => '1 year'] as $key => $label)
                <a href="{{ route('admin.dashboard', array_merge($rangeParams, ['range' => $key])) }}" class="{{ $rangeKey === $key ? 'is-active' : '' }}" @if($rangeKey === $key) aria-current="true" @endif>{{ $label }}</a>
            @endforeach
            <button type="button" class="{{ $rangeKey === 'custom' ? 'is-active' : '' }}" data-a-custom-toggle aria-expanded="{{ $rangeKey === 'custom' ? 'true' : 'false' }}" aria-controls="aCustomRange">Custom</button>
        </div>
        <form class="a-custom {{ $rangeKey === 'custom' ? 'is-open' : '' }}" id="aCustomRange" method="GET" action="{{ route('admin.dashboard') }}">
            <input type="hidden" name="range" value="custom">
            @if($selectedEvent)<input type="hidden" name="event_id" value="{{ $selectedEvent->id }}">@endif
            <label class="a-sr-only" for="aFrom">From</label><input type="date" id="aFrom" name="from" value="{{ $from }}" required>
            <label class="a-sr-only" for="aTo">To</label><input type="date" id="aTo" name="to" value="{{ $to }}" required>
            <button class="a-btn a-btn--dark a-btn--sm" type="submit">Apply</button>
        </form>
        @if($eventOptions->count() > 1)
            <form method="GET" action="{{ route('admin.dashboard') }}">
                <input type="hidden" name="range" value="{{ $rangeKey }}">
                @if($rangeKey === 'custom')<input type="hidden" name="from" value="{{ $from }}"><input type="hidden" name="to" value="{{ $to }}">@endif
                <label class="a-sr-only" for="aEvent">Event</label>
                <select class="form-select" id="aEvent" name="event_id" onchange="this.form.submit()" style="min-width:200px;max-width:260px">
                    <option value="">All events</option>
                    @foreach($eventOptions as $e)<option value="{{ $e->id }}" @selected($selectedEvent?->id === $e->id)>{{ $e->filterLabel() }}</option>@endforeach
                </select>
            </form>
        @endif
    </div>
</div>

<x-admin.flash />

<div class="a-grid a-grid--4 a-grid--kpi5" style="margin-bottom:20px">
    <x-admin.kpi label="Total sales" :value="$moneyShort($kpis['sales'])" icon="fa-wallet" :hint="$trend($kpis['sales_change']) ?? 'Paid orders · '.$rangeLabel" />
    <x-admin.kpi label="Sales excl. fees" :value="$moneyShort($kpis['net_sales'])" icon="fa-coins" :hint="$trend($kpis['net_sales_change']) ?? 'Tickets after discounts · '.$rangeLabel" />
    <x-admin.kpi label="Tickets sold" :value="number_format($kpis['tickets'])" icon="fa-ticket" :hint="$trend($kpis['tickets_change']) ?? 'Paid orders · '.$rangeLabel" />
    <x-admin.kpi label="Active events" :value="number_format($kpis['active_events'])" icon="fa-calendar-days" hint="Currently on sale" />
    <x-admin.kpi label="Paid orders" :value="number_format($kpis['paid_orders'])" icon="fa-receipt" :hint="number_format($kpis['pending_orders']).' awaiting payment or approval'" />
</div>

@php
    // The KPI link carries the dashboard's own scope (period by refund completion date, event, statistics exclusion) so the list shows exactly what the card counted.
    $refundParams = ['refunded' => 1, 'stats' => 1, 'refunded_from' => $from, 'refunded_to' => $to] + ($selectedEvent ? ['refund_event_id' => $selectedEvent->id] : []);
    $refundHref = auth()->user()->can('orders.view') ? route('admin.orders.index', $refundParams) : null;
    $refundPending = $kpis['refunds_pending_count'] > 0 ? number_format($kpis['refunds_pending_count']).' pending request(s) · '.$moneyShort($kpis['refunds_pending_amount']).' (not refunded yet)' : null;
@endphp
@can('payments.refunds.view')
<div class="a-grid a-grid--2" style="margin-bottom:20px">
    <x-admin.kpi label="Total Refunded" :value="$moneyShort($kpis['refunded'])" icon="fa-rotate-left" :href="$refundHref" :hint="'Confirmed refunds · '.$rangeLabel.($refundPending ? ' · '.$refundPending : '')" />
    <x-admin.kpi label="Refunded Orders" :value="number_format($kpis['refunded_orders'])" icon="fa-file-invoice-dollar" :href="$refundHref" :hint="'Orders with a confirmed refund · '.$rangeLabel" />
</div>
@endcan

<div class="a-dash__grid">
    <div class="a-dash__col">
        <div class="a-dash__charts">
            <x-admin.card title="Sales overview" :subtitle="$rangeLabel">
                <x-slot:actions>
                    <label class="a-sr-only" for="aMetric">Chart metric</label>
                    <select class="form-select" id="aMetric" style="width:auto;min-height:38px"><option value="sales">Sales (EGP)</option><option value="orders">Bookings</option></select>
                </x-slot:actions>
                @if(array_sum($series['orders']) === 0 && array_sum($series['sales']) == 0)
                    <x-admin.empty icon="fa-chart-line" title="No sales in this period" text="Try a longer date range." />
                @else
                    <div class="a-chartbox"><canvas id="aSalesChart" role="img" aria-label="Sales over time"></canvas></div>
                @endif
            </x-admin.card>

            <x-admin.card title="Revenue summary" :subtitle="'Paid orders · '.$rangeLabel">
                @if($revenue['paid'] <= 0)
                    <x-admin.empty icon="fa-wallet" title="No paid orders" text="Paid orders by payment method appear here." />
                @else
                    <div class="a-chartbox a-chartbox--donut"><canvas id="aRevenueChart" role="img" aria-label="Paid revenue by payment method"></canvas>
                        <div class="a-donut-center"><b>{{ $moneyShort($revenue['paid']) }}</b><span>Paid</span></div></div>
                    <ul class="a-legend">
                        @foreach($revenue['by_method'] as $i => $m)
                            <li><i data-color="{{ $i }}"></i><span>{{ $m['label'] }}</span><span class="a-num">{{ $m['share'] }}%</span><span class="a-num a-muted">{{ $moneyShort($m['amount']) }}</span></li>
                        @endforeach
                    </ul>
                @endif
                <div class="a-split">
                    <div><span class="a-muted">Pending (not yet paid)</span><b>{{ $moneyShort($revenue['pending']) }}</b></div>
                    <div><span class="a-muted" title="Confirmed refunds only">Refunded</span><b>{{ $moneyShort($revenue['refunded']) }}</b></div>
                    <div><span class="a-muted" title="Paid minus confirmed refunds">Net collected</span><b>{{ $moneyShort($revenue['net_collected']) }}</b></div>
                </div>
            </x-admin.card>
        </div>

        <x-admin.card title="Recent orders" flush>
            <x-slot:actions>@can('orders.view')<a class="a-link a-text-sm" href="{{ route('admin.orders.index') }}">View all</a>@endcan</x-slot:actions>
            @if($recentOrders->isEmpty())
                <x-admin.empty icon="fa-receipt" title="No orders yet" text="New orders appear here as they come in." />
            @else
                <x-admin.table stack class="a-dash-orders" caption="Recent orders">
                    <thead><tr><th>Order</th><th>Customer</th><th>Event</th><th class="a-num">Tickets</th><th class="a-num">Amount</th><th>Status</th></tr></thead>
                    <tbody>
                    @foreach($recentOrders as $o)
                        <tr>
                            <td data-label="Order"><div class="a-cellstack">@can('orders.view')<a class="a-table__main" href="{{ route('admin.orders.show', $o) }}">#{{ $o->order_number }}</a>@else<span class="a-table__main">#{{ $o->order_number }}</span>@endcan<span class="a-table__sub">{{ $o->created_at->format('d M, H:i') }}</span></div></td>
                            <td data-label="Customer"><span class="a-table__main">{{ trim(($o->customer?->first_name ?? '').' '.($o->customer?->last_name ?? '')) ?: '—' }}</span><span class="a-table__sub a-trunc">{{ $o->customer?->email }}</span></td>
                            <td data-label="Event"><span class="a-trunc">{{ $o->items->pluck('event.name')->filter()->first() ?? '—' }}</span></td>
                            <td data-label="Tickets" class="a-num">{{ (int) $o->items->sum('quantity') }}</td>
                            <td data-label="Amount" class="a-num">{{ $moneyShort($o->total_amount) }}</td>
                            <td data-label="Status"><x-admin.status :status="$o->status" /></td>
                        </tr>
                    @endforeach
                    </tbody>
                </x-admin.table>
            @endif
        </x-admin.card>
    </div>

    <div class="a-dash__col">
        <x-admin.card title="Upcoming events">
            <x-slot:actions>@can('events.view')<a class="a-link a-text-sm" href="{{ route('admin.events.index') }}">View all</a>@endcan</x-slot:actions>
            @forelse($upcoming as $e)
                @php $img = $e->cover_image_url ?? null; @endphp
                <div class="a-event">
                    <div class="a-event__img" @if($img) style="background-image:url('{{ $img }}')" @endif><span class="a-event__date">{{ $e->event_date->format('M') }}<b>{{ $e->event_date->format('d') }}</b></span></div>
                    <div style="min-width:0">
                        @can('events.view')<a class="a-event__name" href="{{ route('admin.events.show', $e) }}">{{ $e->name }}</a>@else<span class="a-event__name">{{ $e->name }}</span>@endcan
                        <span class="a-event__meta">{{ $e->location }}</span>
                        <span class="a-event__meta">{{ number_format($e->tickets_sold) }} tickets sold · <x-admin.status :status="$e->status" kind="event" /></span>
                    </div>
                </div>
            @empty
                <x-admin.empty icon="fa-calendar-days" title="No upcoming events" text="Create an event to start selling tickets." />
            @endforelse
        </x-admin.card>

        @if($scans)
            <x-admin.card title="Ticket check-ins" subtitle="Upcoming events">
                <div class="a-ring" style="--p: {{ $scans['percent'] }}"><div><b>{{ $scans['percent'] }}%</b><span>Checked in</span></div></div>
                <div class="a-scan-stats">
                    <div><span class="a-muted">Checked in</span><b>{{ number_format($scans['checked_in']) }}</b></div>
                    <div><span class="a-muted">Remaining</span><b>{{ number_format($scans['remaining']) }}</b></div>
                    <div><span class="a-muted">Total tickets</span><b>{{ number_format($scans['total']) }}</b></div>
                </div>
            </x-admin.card>
        @endif

        <x-admin.card title="Quick actions">
            <div class="a-quick">
                @can('events.create')<a class="is-primary" href="{{ route('admin.events.create') }}"><i class="fa fa-plus"></i>Create event<small>Set up a new event</small></a>@endcan
                @can('tickets.view')<a href="{{ route('admin.tickets.index') }}"><i class="fa fa-ticket"></i>Manage tickets<small>View and edit tickets</small></a>@endcan
                @can('promo-codes.create')<a href="{{ route('admin.promo-codes.create') }}"><i class="fa fa-tags"></i>Create promo code<small>Boost your sales</small></a>@endcan
                @can('reports.view')<a href="{{ route('admin.reports.index') }}"><i class="fa fa-chart-column"></i>View reports<small>Detailed analytics</small></a>@endcan
            </div>
        </x-admin.card>
    </div>
</div>
@endsection

@push('scripts')
<script>
(function () {
    var toggle = document.querySelector('[data-a-custom-toggle]');
    if (toggle) toggle.addEventListener('click', function () {
        var f = document.getElementById('aCustomRange');
        var open = !f.classList.contains('is-open');
        f.classList.toggle('is-open', open);
        toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
        if (open) document.getElementById('aFrom').focus();
    });

    if (typeof Chart === 'undefined') return;
    var palette = ['#FFD200', '#111111', '#9a9a92', '#c9a400', '#555555', '#d9d9d3', '#7a5b00'];
    document.querySelectorAll('.a-legend i[data-color]').forEach(function (i) { i.style.background = palette[i.dataset.color % palette.length]; });
    Chart.defaults.font.family = "'Inter', system-ui, sans-serif";

    var series = @json($series);
    var salesEl = document.getElementById('aSalesChart');
    if (salesEl) {
        var metric = 'sales';
        var chart = new Chart(salesEl, {
            type: 'line',
            data: { labels: series.labels, datasets: [{ label: 'Sales (EGP)', data: series.sales, borderColor: '#E6B800', backgroundColor: 'rgba(255,210,0,.18)', fill: true, tension: .35, pointRadius: series.labels.length > 40 ? 0 : 3, pointBackgroundColor: '#FFD200', borderWidth: 2.5 }] },
            options: {
                responsive: true, maintainAspectRatio: false, interaction: { mode: 'index', intersect: false },
                plugins: { legend: { display: false }, tooltip: { backgroundColor: '#111', padding: 10, displayColors: false, callbacks: { label: function (c) { return metric === 'sales' ? c.parsed.y.toLocaleString('en-US', { minimumFractionDigits: 2 }) + ' EGP' : c.parsed.y + ' bookings'; } } } },
                scales: { x: { grid: { display: false }, ticks: { maxTicksLimit: 8, color: '#8a8a82' } }, y: { beginAtZero: true, grid: { color: '#eeeeea' }, ticks: { color: '#8a8a82', precision: 0 } } }
            }
        });
        document.getElementById('aMetric').addEventListener('change', function (e) {
            metric = e.target.value;
            chart.data.datasets[0].label = metric === 'sales' ? 'Sales (EGP)' : 'Bookings';
            chart.data.datasets[0].data = metric === 'sales' ? series.sales : series.orders;
            chart.update();
        });
    }

    var revEl = document.getElementById('aRevenueChart');
    if (revEl) {
        var rev = @json($revenue['by_method']);
        new Chart(revEl, {
            type: 'doughnut',
            data: { labels: rev.map(function (m) { return m.label; }), datasets: [{ data: rev.map(function (m) { return m.amount; }), backgroundColor: rev.map(function (m, i) { return palette[i % palette.length]; }), borderWidth: 0 }] },
            options: { cutout: '72%', responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false }, tooltip: { backgroundColor: '#111', callbacks: { label: function (c) { return c.label + ': ' + c.parsed.toLocaleString('en-US') + ' EGP'; } } } } }
        });
    }
})();
</script>
@endpush
