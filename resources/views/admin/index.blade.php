@extends('admin.master')

@section('content')

<link rel="stylesheet" href="{{ asset('admin/assets/js/plugins/flatpickr/flatpickr.min.css') }}">

<style>
@import url('https://fonts.googleapis.com/css2?family=Syne:wght@600;700;800&family=DM+Sans:wght@300;400;500&display=swap');

:root {
    --gold:     #f5b800;
    --gold-dim: #c99300;
    --surface:  #0e0e12;
    --surface2: #16161d;
    --border:   rgba(255,255,255,0.07);
    --text:     #e8e8ef;
    --muted:    #6b6b7e;
    --green:    #22c55e;
    --red:      #e8445a;
    --blue:     #3b82f6;
    --font-h:   'Syne', sans-serif;
    --font-b:   'DM Sans', sans-serif;
    --radius:   12px;
}

.db-wrap { background: #060608; min-height: 100vh; font-family: var(--font-b); color: var(--text); padding: 32px 0 60px; }

/* ── Page header ── */
.db-page-head { display: flex; align-items: flex-end; justify-content: space-between; margin-bottom: 28px; gap: 16px; flex-wrap: wrap; }
.db-page-eyebrow { font-family: var(--font-h); font-size: 10px; letter-spacing: 3px; text-transform: uppercase; color: var(--muted); margin-bottom: 4px; }
.db-page-title { font-family: var(--font-h); font-size: clamp(22px, 3vw, 30px); font-weight: 800; color: #fff; letter-spacing: -0.5px; margin: 0; }
.db-page-title span { color: var(--gold); }
.db-date { font-size: 12px; color: var(--muted); background: var(--surface); border: 1px solid var(--border); border-radius: 8px; padding: 7px 14px; white-space: nowrap; }

.db-filters { display:flex; flex-wrap:wrap; gap:8px; margin-bottom:20px; align-items:center; }
.db-filter-btn { font-size:12px; color:var(--muted); background:var(--surface); border:1px solid var(--border); border-radius:999px; padding:7px 12px; text-decoration:none; transition:all .2s; }
.db-filter-btn:hover { color:var(--gold); border-color:rgba(245,184,0,0.3); }
.db-filter-btn.active { color:#111; background:var(--gold); border-color:var(--gold); font-weight:700; }
.db-filter-form { display:flex; gap:8px; align-items:center; }
.db-filter-input { background:var(--surface); border:1px solid var(--border); color:var(--text); border-radius:8px; padding:6px 10px; font-size:12px; }
.db-filter-apply { background:var(--gold); color:#111; border:0; border-radius:8px; padding:7px 12px; font-size:12px; font-weight:700; }


/* ── Stat cards ── */
.db-stats { display: grid; grid-template-columns: repeat(4, 1fr); gap: 16px; margin-bottom: 24px; }
@media (max-width: 900px) { .db-stats { grid-template-columns: repeat(2, 1fr); } }
@media (max-width: 500px)  { .db-stats { grid-template-columns: 1fr; } }

.db-stat { background: var(--surface); border: 1px solid var(--border); border-radius: var(--radius); padding: 22px 22px 18px; position: relative; overflow: hidden; transition: border-color 0.2s, transform 0.2s; }
.db-stat:hover { border-color: rgba(245,184,0,0.2); transform: translateY(-2px); }
.db-stat::before { content: ''; position: absolute; top: 0; left: 0; right: 0; height: 2px; background: var(--accent, var(--gold)); opacity: 0.7; }
.db-stat.green { --accent: var(--green); }
.db-stat.blue  { --accent: var(--blue);  }
.db-stat.red   { --accent: var(--red);   }

.db-stat-top   { display: flex; align-items: center; justify-content: space-between; margin-bottom: 14px; }
.db-stat-label { font-family: var(--font-h); font-size: 10px; letter-spacing: 2px; text-transform: uppercase; color: var(--muted); }
.db-stat-icon  { width: 36px; height: 36px; border-radius: 8px; display: flex; align-items: center; justify-content: center; font-size: 15px; background: rgba(245,184,0,0.08); }
.db-stat.green .db-stat-icon { background: rgba(34,197,94,0.08); }
.db-stat.blue  .db-stat-icon { background: rgba(59,130,246,0.08); }
.db-stat.red   .db-stat-icon { background: rgba(232,68,90,0.08); }
.db-stat-val { font-family: var(--font-h); font-size: 28px; font-weight: 800; color: #fff; line-height: 1; margin-bottom: 6px; }
.db-stat-sub { font-size: 12px; color: var(--muted); }

/* ── Charts row ── */
.db-charts { display: grid; grid-template-columns: 2fr 1fr; gap: 16px; margin-bottom: 24px; }
@media (max-width: 900px) { .db-charts { grid-template-columns: 1fr; } }

/* ── Cards ── */
.db-card { background: var(--surface); border: 1px solid var(--border); border-radius: var(--radius); overflow: hidden; }
.db-card-head { display: flex; align-items: center; justify-content: space-between; padding: 18px 22px 14px; border-bottom: 1px solid var(--border); }
.db-card-title { font-family: var(--font-h); font-size: 11px; letter-spacing: 2px; text-transform: uppercase; color: var(--gold); display: flex; align-items: center; gap: 8px; }
.db-card-title::before { content: ''; width: 3px; height: 14px; background: var(--gold); border-radius: 2px; }
.db-card-action { font-size: 12px; color: var(--muted); text-decoration: none; transition: color 0.2s; }
.db-card-action:hover { color: var(--gold); }
.db-card-body { padding: 22px; }
.chart-container { position: relative; height: 220px; }

/* ── Bottom grid ── */
.db-bottom { display: grid; grid-template-columns: 3fr 2fr; gap: 16px; }
@media (max-width: 900px) { .db-bottom { grid-template-columns: 1fr; } }

/* ── Table ── */
.db-table { width: 100%; border-collapse: collapse; }
.db-table thead th { font-family: var(--font-h); font-size: 10px; letter-spacing: 1.5px; text-transform: uppercase; color: var(--muted); padding: 0 16px 12px; text-align: left; border-bottom: 1px solid var(--border); }
.db-table tbody tr { border-bottom: 1px solid var(--border); transition: background 0.15s; }
.db-table tbody tr:last-child { border-bottom: none; }
.db-table tbody tr:hover { background: rgba(255,255,255,0.02); }
.db-table td { padding: 13px 16px; font-size: 13px; color: var(--text); vertical-align: middle; }
.db-table td.muted { color: var(--muted); font-size: 12px; }

/* ── Badges ── */
.db-badge { display: inline-flex; align-items: center; gap: 5px; font-family: var(--font-h); font-size: 10px; font-weight: 700; letter-spacing: 0.5px; padding: 3px 9px; border-radius: 99px; }
.db-badge::before { content: ''; width: 5px; height: 5px; border-radius: 50%; background: currentColor; }
.db-badge.pending  { color: var(--gold);  background: rgba(245,184,0,0.1); }
.db-badge.approved { color: var(--green); background: rgba(34,197,94,0.1); }
.db-badge.rejected { color: var(--red);   background: rgba(232,68,90,0.1); }
.db-badge.paid     { color: var(--blue);  background: rgba(59,130,246,0.1); }

/* ── Top events ── */
.db-event-row { display: flex; align-items: center; gap: 12px; padding: 12px 0; border-bottom: 1px solid var(--border); }
.db-event-row:last-child { border-bottom: none; }
.db-event-rank    { font-family: var(--font-h); font-size: 11px; font-weight: 700; color: var(--muted); width: 20px; flex-shrink: 0; }
.db-event-info    { flex: 1; min-width: 0; }
.db-event-name    { font-size: 13px; color: #fff; font-weight: 500; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.db-event-meta    { font-size: 11px; color: var(--muted); margin-top: 2px; }
.db-event-revenue { font-family: var(--font-h); font-size: 13px; font-weight: 700; color: var(--gold); white-space: nowrap; }

/* ── Pending alert ── */
.db-pending-alert { background: rgba(245,184,0,0.06); border: 1px dashed rgba(245,184,0,0.3); border-radius: 8px; padding: 12px 16px; display: flex; align-items: center; gap: 10px; font-size: 13px; color: var(--gold); margin-bottom: 20px; text-decoration: none; transition: background 0.2s; }
.db-pending-alert:hover { background: rgba(245,184,0,0.1); color: var(--gold); }
.db-pending-dot { width: 8px; height: 8px; border-radius: 50%; background: var(--gold); flex-shrink: 0; animation: blink 1.8s ease-in-out infinite; }
@keyframes blink { 0%,100%{opacity:1} 50%{opacity:0.3} }

/* ── Quick links ── */
.db-quick { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; }
.db-quick-link { display: flex; align-items: center; gap: 10px; background: var(--surface2); border: 1px solid var(--border); border-radius: 8px; padding: 13px 14px; text-decoration: none; color: var(--text); font-size: 13px; font-weight: 500; transition: border-color 0.2s, background 0.2s; }
.db-quick-link:hover { border-color: rgba(245,184,0,0.3); background: rgba(245,184,0,0.04); color: #fff; }
.db-quick-icon { font-size: 16px; }

/* ── Animations ── */
.fade-up { animation: fadeUp 0.4s ease both; }
@keyframes fadeUp { from{opacity:0;transform:translateY(12px)} to{opacity:1;transform:translateY(0)} }
.delay-1{animation-delay:.05s} .delay-2{animation-delay:.10s}
.delay-3{animation-delay:.15s} .delay-4{animation-delay:.20s}
</style>

<div class="db-wrap">
<div class="content">

    {{-- ╔══════════════════════════════════════╗
         ║          PAGE HEADER                 ║
         ╚══════════════════════════════════════╝ --}}
    <div class="db-page-head fade-up">
        <div>
            <div class="db-page-eyebrow">Admin Panel</div>
            <h1 class="db-page-title">Dashboard <span>Overview</span></h1>
        </div>
        <div class="db-date"><i class="fa fa-calendar-alt me-1"></i> {{ now()->format('D, d M Y') }}</div>
    </div>

    <div class="db-filters fade-up">
        @foreach($rangeOptions as $key => $label)
            <a href="{{ route('admin.dashboard', ['range' => $key]) }}" class="db-filter-btn {{ $selectedRange === $key ? 'active' : '' }}">{{ $label }}</a>
        @endforeach

        <form method="GET" action="{{ route('admin.dashboard') }}" class="db-filter-form">
            <input type="hidden" name="range" value="custom">
            <input type="text" name="from" class="db-filter-input js-flatpickr" value="{{ optional($startAt)->format('Y-m-d') }}" data-date-format="Y-m-d" data-alt-input="true" data-alt-format="m/d/Y" placeholder="From date">
            <input type="text" name="to" class="db-filter-input js-flatpickr" value="{{ optional($endAt)->format('Y-m-d') }}" data-date-format="Y-m-d" data-alt-input="true" data-alt-format="m/d/Y" placeholder="To date">
            <button type="submit" class="db-filter-apply">Apply</button>
        </form>
    </div>

    {{-- ╔══════════════════════════════════════╗
         ║   PENDING ORDERS ALERT               ║
         ║   WIRE: show only if $pendingOrders > 0
         ║   href="{{ route('admin.orders.index', ['status'=>'pending']) }}"
         ╚══════════════════════════════════════╝ --}}
    @if($pendingOrders > 0)
    <a href="{{ route('admin.orders.index') }}" class="db-pending-alert fade-up delay-1">
        <div class="db-pending-dot"></div>
        <span><strong>{{ $pendingOrders }} order(s)</strong> pending your review — click to view</span>
        <i class="fa fa-arrow-right ms-auto"></i>
    </a>
    @endif

    {{-- ╔══════════════════════════════════════╗
         ║          STAT CARDS                  ║
         ║  WIRE each .db-stat-val with:        ║
         ║   Orders    → {{ number_format($totalOrders) }}
         ║   Revenue   → {{ number_format($totalRevenue, 0) }}
         ║   Customers → {{ number_format($totalCustomers) }}
         ║   Events    → {{ number_format($totalEvents) }}
         ╚══════════════════════════════════════╝ --}}
    <div class="db-stats">

        <div class="db-stat fade-up delay-1">
            <div class="db-stat-top">
                <div class="db-stat-label">Total Orders</div>
                <div class="db-stat-icon">🧾</div>
            </div>
            <div class="db-stat-val">{{ number_format($totalOrders) }}</div>
            <div class="db-stat-sub">Within {{ $rangeLabel }}</div>
        </div>

        <div class="db-stat green fade-up delay-2">
            <div class="db-stat-top">
                <div class="db-stat-label">Revenue</div>
                <div class="db-stat-icon">💰</div>
            </div>
            <div class="db-stat-val">{{ number_format($totalRevenue, 0) }} EGP</div>
            <div class="db-stat-sub">Within {{ $rangeLabel }}</div>
        </div>

        <div class="db-stat blue fade-up delay-3">
            <div class="db-stat-top">
                <div class="db-stat-label">Customers</div>
                <div class="db-stat-icon">👥</div>
            </div>
            <div class="db-stat-val">{{ number_format($totalCustomers) }}</div>
            <div class="db-stat-sub">New in {{ $rangeLabel }}</div>
        </div>

        <div class="db-stat red fade-up delay-4">
            <div class="db-stat-top">
                <div class="db-stat-label">Active Events</div>
                <div class="db-stat-icon">🎟️</div>
            </div>
            <div class="db-stat-val">{{ number_format($totalEvents) }}</div>
            <div class="db-stat-sub">Published events</div>
        </div>

    </div>

    {{-- ╔══════════════════════════════════════╗
         ║              CHARTS                  ║
         ╚══════════════════════════════════════╝ --}}
    <div class="db-charts fade-up delay-2">

        <div class="db-card">
            <div class="db-card-head">
                <div class="db-card-title">Revenue Trend ({{ $rangeLabel }})</div>
                <a href="{{ route('admin.orders.index') }}" class="db-card-action">View Orders →</a>
            </div>
            <div class="db-card-body">
                <div class="chart-container"><canvas id="revenueChart"></canvas></div>
            </div>
        </div>

        <div class="db-card">
            <div class="db-card-head">
                <div class="db-card-title">Orders Trend ({{ $rangeLabel }})</div>
            </div>
            <div class="db-card-body">
                <div class="chart-container"><canvas id="ordersChart"></canvas></div>
            </div>
        </div>

    </div>

    {{-- ╔══════════════════════════════════════╗
         ║    RECENT ORDERS  +  RIGHT COLUMN    ║
         ╚══════════════════════════════════════╝ --}}
    <div class="db-bottom fade-up delay-3">

        {{-- ── Recent Orders ──────────────────────────────────
             WIRE: replace <tbody> content with:
               @foreach($recentOrders as $order)
                 <tr>
                   <td class="muted">#{{ $order->id }}</td>
                   <td>{{ $order->first_name }} {{ $order->last_name }}</td>
                   <td class="muted">{{ $order->items->first()?->ticket?->event?->name ?? '—' }}</td>
                   <td style="color:var(--gold);font-family:var(--font-h);font-weight:700;">
                       {{ number_format($order->total, 2) }}
                   </td>
                   <td><span class="db-badge {{ $order->status }}">{{ ucfirst($order->status) }}</span></td>
                 </tr>
               @endforeach
        ── --}}
        <div class="db-card">
            <div class="db-card-head">
                <div class="db-card-title">Recent Orders ({{ $rangeLabel }})</div>
                <a href="{{ route('admin.orders.index') }}" class="db-card-action">All Orders →</a>
            </div>
            <div class="db-card-body" style="padding:0">
                <table class="db-table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Customer</th>
                            <th>Event</th>
                            <th>Total</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($recentOrders as $order)
                            @php
                                $eventName = $order->items->first()?->ticket_name;
                                $eventName = $eventName && str_contains($eventName, ' - ') ? strstr($eventName, ' - ', true) : ($eventName ?: '—');
                                $statusClass = in_array($order->status, ['pending', 'pending_approval', 'pending_payment']) ? 'pending' : (in_array($order->status, ['approved', 'approved_pending_payment']) ? 'approved' : (in_array($order->status, ['paid']) ? 'paid' : 'rejected'));
                            @endphp
                            <tr>
                                <td class="muted">#{{ preg_replace('/\D+/', '', (string) $order->order_number) ?: $order->id }}</td>
                                <td>{{ $order->customer?->full_name ?: 'N/A' }}</td>
                                <td class="muted">{{ $eventName }}</td>
                                <td style="color:var(--gold);font-family:var(--font-h);font-weight:700;">{{ number_format((float) $order->total_amount, 2) }} EGP</td>
                                <td><span class="db-badge {{ $statusClass }}">{{ ucwords(str_replace('_', ' ', $order->status)) }}</span></td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="muted" style="padding:16px;">No orders yet.</td></tr>
                        @endforelse
                                        </tbody>
                </table>
            </div>
        </div>

        {{-- ── Right column ── --}}
        <div style="display:flex;flex-direction:column;gap:16px;">

            {{-- Top Events ──────────────────────────────────
                 WIRE: replace rows with:
                   @foreach($topEvents as $i => $event)
                     <div class="db-event-row">
                       <div class="db-event-rank">{{ $i + 1 }}</div>
                       <div class="db-event-info">
                         <div class="db-event-name">{{ $event->name }}</div>
                         <div class="db-event-meta">{{ $event->orders_count }} orders</div>
                       </div>
                       <div class="db-event-revenue">{{ number_format($event->revenue, 0) }}</div>
                     </div>
                   @endforeach
            ── --}}
            <div class="db-card">
                <div class="db-card-head">
                    <div class="db-card-title">Top Events ({{ $rangeLabel }})</div>
                    <a href="{{ route('admin.events.index') }}" class="db-card-action">All →</a>
                </div>
                <div class="db-card-body" style="padding:4px 22px 16px;">

                    @forelse($topEvents as $i => $event)
                    <div class="db-event-row">
                        <div class="db-event-rank">{{ $i + 1 }}</div>
                        <div class="db-event-info">
                            <div class="db-event-name">{{ $event['name'] }}</div>
                            <div class="db-event-meta">{{ $event['orders_count'] }} orders</div>
                        </div>
                        <div class="db-event-revenue">{{ number_format($event['revenue'], 0) }} EGP</div>
                    </div>
                    @empty
                    <div class="db-event-row">
                        <div class="db-event-info"><div class="db-event-name">No data yet</div></div>
                    </div>
                    @endforelse

                </div>
            </div>

            {{-- Quick Access --}}
            <div class="db-card">
                <div class="db-card-head">
                    <div class="db-card-title">Quick Access</div>
                </div>
                <div class="db-card-body">
                    <div class="db-quick">
                        <a href="{{ route('admin.events.index') }}"    class="db-quick-link"><span class="db-quick-icon">🎟️</span> Events</a>
                        <a href="{{ route('admin.orders.index') }}"    class="db-quick-link"><span class="db-quick-icon">🧾</span> Orders</a>
                        <a href="{{ route('admin.customers.index') }}" class="db-quick-link"><span class="db-quick-icon">👥</span> Customers</a>
                        <a href="{{ route('admin.tickets.index') }}"   class="db-quick-link"><span class="db-quick-icon">🎫</span> Tickets</a>
                        <a href="{{ route('admin.users.index') }}"     class="db-quick-link"><span class="db-quick-icon">🔑</span> Users</a>
                        <a href="{{ route('admin.roles.index') }}"     class="db-quick-link"><span class="db-quick-icon">🛡️</span> Roles</a>
                    </div>
                </div>
            </div>

        </div>
    </div>

</div>
</div>

{{-- ╔══════════════════════════════════════╗
     ║           CHARTS JS                  ║
     ║  WIRE chart data — replace the       ║
     ║  hardcoded arrays with:              ║
     ║   labels: {!! json_encode($revenueLabels) !!}
     ║   data:   {!! json_encode($revenueData) !!}
     ║   (same for orders chart)            ║
     ╚══════════════════════════════════════╝ --}}
<script src="{{ asset('admin/assets/js/plugins/flatpickr/flatpickr.min.js') }}"></script>
<script>
window.addEventListener('load', function () {
    if (window.Dashmix && typeof Dashmix.helpersOnLoad === 'function') {
        Dashmix.helpersOnLoad(['js-flatpickr']);
    } else if (typeof flatpickr !== 'undefined') {
        flatpickr('.js-flatpickr', { dateFormat: 'Y-m-d', altInput: true, altFormat: 'm/d/Y' });
    }
});
</script>

<script>
window.addEventListener('load', function () {
    if (typeof Chart === 'undefined') {
        console.warn('Chart.js is not loaded');
        return;
    }

    const revenueCanvas = document.getElementById('revenueChart');
    const ordersCanvas = document.getElementById('ordersChart');
    if (!revenueCanvas || !ordersCanvas) {
        return;
    }


    const gold    = '#f5b800';
    const goldDim = 'rgba(245,184,0,0.15)';
    const gridCol = 'rgba(255,255,255,0.05)';
    const muted   = '#6b6b7e';

    const sharedOpts = {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: { display: false },
            tooltip: {
                backgroundColor: '#16161d',
                borderColor: 'rgba(245,184,0,0.3)',
                borderWidth: 1,
                titleColor: '#f5b800',
                bodyColor: '#e8e8ef',
                padding: 10,
            }
        },
        scales: {
            x: { grid: { color: gridCol }, ticks: { color: muted, font: { family: 'DM Sans', size: 11 } } },
            y: { grid: { color: gridCol }, ticks: { color: muted, font: { family: 'DM Sans', size: 11 } } },
        }
    };

    new Chart(revenueCanvas, {
        type: 'line',
        data: {
            labels  : @json($labels),
            datasets: [{
                data                : @json($revenueData),
                borderColor         : gold,
                backgroundColor     : goldDim,
                borderWidth         : 2,
                pointBackgroundColor: gold,
                pointRadius         : 4,
                pointHoverRadius    : 6,
                fill                : true,
                tension             : 0.4,
            }]
        },
        options: { ...sharedOpts }
    });

    new Chart(ordersCanvas, {
        type: 'bar',
        data: {
            labels  : @json($labels),
            datasets: [{
                data           : @json($ordersData),
                backgroundColor: 'rgba(245,184,0,0.22)',
                borderColor    : gold,
                borderWidth    : 1.5,
                borderRadius   : 5,
            }]
        },
        options: { ...sharedOpts }
    });
});
</script>

@endsection
