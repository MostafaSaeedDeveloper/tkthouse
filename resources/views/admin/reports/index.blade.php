@extends('admin.master')

@section('content')
<link rel="stylesheet" href="{{ asset('admin/assets/js/plugins/flatpickr/flatpickr.min.css') }}">

<div class="content reports-page">
    <div class="d-md-flex justify-content-md-between align-items-md-center mb-4">
        <div>
            <h1 class="h3 mb-1">Reports</h1>
            <p class="text-muted mb-0">Detailed per-event performance, tickets and revenue.</p>
        </div>
    </div>

    <div class="reports-toolbar mb-4">
        <div class="reports-range-buttons">
            @foreach($rangeOptions as $key => $label)
                <a href="{{ route('admin.reports.index', ['range' => $key, 'event' => $selectedEvent ?: null]) }}"
                   class="reports-range-btn {{ $selectedRange === $key ? 'active' : '' }}">{{ $label }}</a>
            @endforeach
        </div>
        <form method="GET" action="{{ route('admin.reports.index') }}" class="reports-filters-form">
            <input type="hidden" name="range" value="custom">
            <input type="text" name="from" class="reports-filter-input js-flatpickr" value="{{ optional($startAt)->format('Y-m-d') }}" data-date-format="Y-m-d" data-alt-input="true" data-alt-format="m/d/Y" placeholder="From date">
            <input type="text" name="to" class="reports-filter-input js-flatpickr" value="{{ optional($endAt)->format('Y-m-d') }}" data-date-format="Y-m-d" data-alt-input="true" data-alt-format="m/d/Y" placeholder="To date">
            <button type="submit" class="reports-filter-apply">Apply</button>
        </form>
    </div>

    <p class="reports-context mb-3">
        Showing <strong>{{ $selectedEvent !== '' ? $selectedEvent : 'all events' }}</strong> within <strong>{{ $rangeLabel }}</strong>.
    </p>


    <div class="report-summary mb-3">
        <span class="report-type-badge"><span>Tickets Sold</span><strong>{{ number_format($totalTickets) }}</strong></span>
        <span class="report-type-badge"><span>Guest List Invitations</span><strong>{{ number_format($totalGuestInvitations) }}</strong></span>
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
                    <span class="report-total">{{ number_format($report['tickets_sold']) }} sold / {{ number_format($guestInvitationsByEvent[$report['event_name']] ?? 0) }} invited</span>
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
.reports-page .reports-toolbar {
    display: grid;
    gap: 10px;
}
.reports-page .reports-range-buttons {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
}
.reports-page .reports-range-btn {
    text-decoration: none;
    color: var(--text) !important;
    border: 1px solid var(--border);
    border-radius: 999px;
    padding: 6px 12px;
    font-size: 12px;
    background: var(--surface2);
}
.reports-page .reports-range-btn:visited,
.reports-page .reports-range-btn:focus,
.reports-page .reports-range-btn:hover {
    color: var(--text) !important;
    text-decoration: none;
}
.reports-page .reports-range-btn.active {
    color: #000 !important;
    background: var(--gold);
    border-color: var(--gold);
    font-weight: 700;
}
.reports-page .reports-range-btn.active:visited,
.reports-page .reports-range-btn.active:hover,
.reports-page .reports-range-btn.active:focus {
    color: #000 !important;
}
.reports-page .reports-filters-form {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    align-items: center;
}
.reports-page .reports-filter-input {
    border: 1px solid var(--border);
    border-radius: 8px;
    background: var(--surface2);
    color: var(--text);
    padding: 7px 10px;
    font-size: 12px;
}
.reports-page .reports-filters-form .reports-filter-input,
.reports-page .reports-filters-form .flatpickr-input {
    width: 220px;
    min-width: 220px;
    flex: 0 0 220px;
}
.reports-page .reports-filter-input::placeholder {
    color: var(--muted);
}
.reports-page .reports-filter-apply {
    border: 0;
    border-radius: 8px;
    background: var(--gold);
    color: #111;
    padding: 7px 12px;
    font-size: 12px;
    font-weight: 700;
}
.reports-page .reports-context {
    color: var(--muted);
    font-size: 13px;
}
.report-summary { display:flex; gap:8px; flex-wrap:wrap; }
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

<script src="{{ asset('admin/assets/js/plugins/flatpickr/flatpickr.min.js') }}"></script>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        if (window.flatpickr) {
            flatpickr('.js-flatpickr', {
                dateFormat: 'Y-m-d',
                altInput: true,
                altFormat: 'm/d/Y',
                allowInput: true,
            });
        }
    });
</script>
@endsection
