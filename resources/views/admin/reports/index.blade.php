@extends('admin.master')

@section('content')
<div class="content reports-page">
    <div class="d-md-flex justify-content-md-between align-items-md-center mb-4">
        <div>
            <h1 class="h3 mb-1">Reports</h1>
            <p class="text-muted mb-0">Detailed per-event performance, tickets and revenue.</p>
        </div>
        <div class="reports-overview mt-3 mt-md-0">
            <div class="reports-pill">
                <span>Total Tickets</span>
                <strong>{{ number_format($totalTickets) }}</strong>
            </div>
            <div class="reports-pill reports-pill-gold">
                <span>Total Revenue</span>
                <strong>{{ number_format($totalRevenue, 2) }} EGP</strong>
            </div>
        </div>
    </div>

    <div class="reports-grid">
        @forelse($eventReports as $report)
            @php
                $malePercent = $report['tickets_sold'] > 0 ? ($report['male_tickets'] / $report['tickets_sold']) * 100 : 0;
                $femalePercent = $report['tickets_sold'] > 0 ? ($report['female_tickets'] / $report['tickets_sold']) * 100 : 0;
            @endphp
            <article class="report-card">
                <div class="report-card-top">
                    <h3>{{ $report['event_name'] }}</h3>
                    <span class="report-total">{{ number_format($report['tickets_sold']) }} sold</span>
                </div>

                <div class="report-metrics">
                    <div>
                        <small>Gross Revenue</small>
                        <strong>{{ number_format($report['gross_revenue'], 2) }} EGP</strong>
                    </div>
                    <div>
                        <small>Male Tickets</small>
                        <strong>{{ number_format($report['male_tickets']) }}</strong>
                    </div>
                    <div>
                        <small>Female Tickets</small>
                        <strong>{{ number_format($report['female_tickets']) }}</strong>
                    </div>
                </div>

                <div class="report-gender-bars">
                    <div class="report-gender-track">
                        <div class="report-gender-fill male" style="width: {{ $malePercent }}%"></div>
                    </div>
                    <div class="report-gender-track">
                        <div class="report-gender-fill female" style="width: {{ $femalePercent }}%"></div>
                    </div>
                </div>

                <div class="report-ticket-types">
                    @foreach($report['ticket_types'] as $type)
                        <span class="report-type-badge">
                            <span>{{ $type['name'] }}</span>
                            <strong>{{ number_format($type['count']) }}</strong>
                        </span>
                    @endforeach
                </div>
            </article>
        @empty
            <div class="report-empty">
                <i class="fa fa-chart-line me-2"></i>
                No paid completed orders yet, so reports are still empty.
            </div>
        @endforelse
    </div>
</div>

<style>
.reports-page .reports-overview { display: flex; gap: 10px; flex-wrap: wrap; }
.reports-page .reports-pill {
    border: 1px solid var(--border);
    border-radius: 999px;
    padding: 7px 14px;
    background: var(--surface2);
    display: inline-flex;
    align-items: center;
    gap: 8px;
}
.reports-page .reports-pill span { color: var(--muted); font-size: 12px; }
.reports-page .reports-pill strong { font-family: var(--font-num); font-weight: 700; color: var(--text); }
.reports-page .reports-pill-gold { border-color: rgba(245, 184, 0, 0.4); background: rgba(245, 184, 0, 0.1); }

.reports-grid { display: grid; gap: 16px; grid-template-columns: repeat(auto-fill, minmax(320px, 1fr)); }
.report-card {
    border: 1px solid var(--border);
    border-radius: 14px;
    padding: 16px;
    background: linear-gradient(155deg, var(--surface2), rgba(9, 9, 13, 0.95));
    box-shadow: 0 14px 24px rgba(0, 0, 0, 0.25);
}
.report-card-top { display: flex; justify-content: space-between; gap: 12px; align-items: center; margin-bottom: 14px; }
.report-card-top h3 { margin: 0; font-size: 18px; color: #fff; }
.report-total {
    background: rgba(59, 130, 246, 0.18);
    border: 1px solid rgba(59, 130, 246, 0.5);
    color: #93c5fd;
    border-radius: 999px;
    padding: 3px 10px;
    font-size: 12px;
    white-space: nowrap;
}
.report-metrics { display: grid; grid-template-columns: repeat(3, 1fr); gap: 8px; margin-bottom: 12px; }
.report-metrics small { display: block; color: var(--muted); font-size: 11px; margin-bottom: 2px; }
.report-metrics strong { color: var(--text); font-family: var(--font-num); font-size: 15px; }
.report-gender-bars { display: grid; gap: 7px; margin-bottom: 12px; }
.report-gender-track { height: 8px; background: rgba(255, 255, 255, 0.08); border-radius: 999px; overflow: hidden; }
.report-gender-fill { height: 100%; border-radius: inherit; }
.report-gender-fill.male { background: linear-gradient(90deg, #3b82f6, #60a5fa); }
.report-gender-fill.female { background: linear-gradient(90deg, #ec4899, #f472b6); }
.report-ticket-types { display: flex; flex-wrap: wrap; gap: 8px; }
.report-type-badge {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    border-radius: 999px;
    padding: 4px 10px;
    border: 1px solid var(--border);
    background: rgba(255, 255, 255, 0.04);
    font-size: 12px;
}
.report-type-badge strong { color: var(--gold); font-family: var(--font-num); }
.report-empty {
    border: 1px dashed var(--border);
    border-radius: 12px;
    padding: 20px;
    color: var(--muted);
    text-align: center;
}
@media (max-width: 540px) {
    .report-metrics { grid-template-columns: 1fr; }
}
</style>
@endsection
