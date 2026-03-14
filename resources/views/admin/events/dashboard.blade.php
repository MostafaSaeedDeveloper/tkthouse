@extends('admin.master')

@section('content')
<link rel="stylesheet" href="{{ asset('admin/assets/js/plugins/flatpickr/flatpickr.min.css') }}">

<div class="content reports-page">
    <div class="d-md-flex justify-content-md-between align-items-md-center mb-4">
        <div>
            <h1 class="h3 mb-1">{{ $event->name }} Dashboard</h1>
            <p class="text-muted mb-0">Event-specific summary for tickets sold, invitations, guest list, revenue, check-ins and analytics.</p>
        </div>
        <div class="mt-3 mt-md-0 d-flex gap-2">
            <a href="{{ route('admin.events.report', $event) }}" class="btn btn-sm btn-alt-info">View Event Report</a>
            <a href="{{ route('admin.dashboard') }}" class="btn btn-sm btn-alt-secondary">All Events Dashboard</a>
        </div>
    </div>

    <div class="reports-toolbar mb-4">
        <div class="reports-range-buttons">
            @foreach($rangeOptions as $key => $label)
                <a href="{{ route('admin.events.dashboard', ['event' => $event, 'range' => $key]) }}"
                   class="reports-range-btn {{ $selectedRange === $key ? 'active' : '' }}">{{ $label }}</a>
            @endforeach
        </div>
        <form method="GET" action="{{ route('admin.events.dashboard', $event) }}" class="reports-filters-form">
            <input type="hidden" name="range" value="custom">
            <input type="text" name="from" class="reports-filter-input js-flatpickr" value="{{ optional($startAt)->format('Y-m-d') }}" placeholder="From date">
            <input type="text" name="to" class="reports-filter-input js-flatpickr" value="{{ optional($endAt)->format('Y-m-d') }}" placeholder="To date">
            <button type="submit" class="reports-filter-apply">Apply</button>
        </form>
    </div>

    <p class="reports-context mb-3">
        Showing <strong>{{ $event->name }}</strong> within <strong>{{ $rangeLabel }}</strong>.
    </p>

    <div class="reports-grid mb-4">
        <article class="report-card"><small>Tickets Sold</small><h3>{{ number_format($ticketsSold) }}</h3></article>
        <article class="report-card"><small>Invitations Sent</small><h3>{{ number_format($guestInvitations) }}</h3></article>
        <article class="report-card"><small>Guest List Check-ins</small><h3>{{ number_format($guestCheckedIn) }}</h3></article>
        <article class="report-card"><small>Revenue</small><h3>{{ number_format($grossRevenue, 2) }} EGP</h3></article>
        <article class="report-card"><small>Paid Check-ins</small><h3>{{ number_format($paidCheckedIn) }}</h3></article>
        <article class="report-card"><small>Scanner Activity</small><h3>{{ number_format($totalScans) }}</h3></article>
    </div>

    <div class="block block-rounded">
        <div class="block-header block-header-default">
            <h3 class="block-title">Event Analytics</h3>
            <div class="block-options text-muted fs-sm">{{ number_format($ordersCount) }} paid orders</div>
        </div>
        <div class="block-content">
            <canvas id="eventAnalyticsChart" height="120"></canvas>
        </div>
    </div>
</div>


<style>
.reports-page .reports-toolbar { display:grid; gap:10px; }
.reports-page .reports-range-buttons { display:flex; flex-wrap:wrap; gap:8px; }
.reports-page .reports-range-btn { text-decoration:none; color:var(--text) !important; border:1px solid var(--border); border-radius:999px; padding:6px 12px; font-size:12px; background:var(--surface2); }
.reports-page .reports-range-btn.active { color:#000 !important; background:var(--gold); border-color:var(--gold); font-weight:700; }
.reports-page .reports-filters-form { display:flex; flex-wrap:wrap; gap:8px; align-items:center; }
.reports-page .reports-filter-input { border:1px solid var(--border); border-radius:8px; background:var(--surface2); color:var(--text); padding:7px 10px; font-size:12px; }
.reports-page .reports-filter-apply { border:0; border-radius:8px; background:var(--gold); color:#111; padding:7px 12px; font-size:12px; font-weight:700; }
.reports-page .reports-context { color:var(--muted); font-size:13px; }
.reports-grid { display:grid; gap:16px; grid-template-columns:repeat(auto-fill, minmax(230px, 1fr)); }
.report-card { border:1px solid var(--border); border-radius:14px; padding:16px; background:linear-gradient(155deg, var(--surface2), rgba(9, 9, 13, 0.95)); }
.reports-grid .report-card small { display:block; color:var(--muted); margin-bottom:6px; }
.reports-grid .report-card h3 { margin:0; color:#fff; }
</style>


<script src="{{ asset('admin/assets/js/plugins/chart.js/chart.umd.js') }}"></script>
<script src="{{ asset('admin/assets/js/plugins/flatpickr/flatpickr.min.js') }}"></script>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        if (window.flatpickr) {
            flatpickr('.js-flatpickr', { dateFormat: 'Y-m-d', altInput: true, altFormat: 'm/d/Y', allowInput: true });
        }

        const chartCanvas = document.getElementById('eventAnalyticsChart');
        if (!chartCanvas || !window.Chart) {
            return;
        }

        new Chart(chartCanvas, {
            type: 'line',
            data: {
                labels: @json($labels),
                datasets: [
                    {
                        label: 'Orders',
                        data: @json($ordersData),
                        borderColor: '#3b82f6',
                        backgroundColor: 'rgba(59,130,246,0.2)',
                        tension: 0.3,
                        yAxisID: 'yOrders',
                    },
                    {
                        label: 'Revenue',
                        data: @json($revenueData),
                        borderColor: '#f5b800',
                        backgroundColor: 'rgba(245,184,0,0.2)',
                        tension: 0.3,
                        yAxisID: 'yRevenue',
                    }
                ]
            },
            options: {
                responsive: true,
                interaction: { mode: 'index', intersect: false },
                scales: {
                    yOrders: { type: 'linear', position: 'left', beginAtZero: true },
                    yRevenue: { type: 'linear', position: 'right', beginAtZero: true, grid: { drawOnChartArea: false } }
                }
            }
        });
    });
</script>
@endsection
