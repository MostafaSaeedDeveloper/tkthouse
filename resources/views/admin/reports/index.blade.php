@extends('admin.master')
@section('title', $reportTitle ?? 'Reports')
@section('content')
<x-admin.page-header :title="$reportTitle ?? 'Reports'" :subtitle="$reportDescription ?? 'Detailed per-event performance, tickets and check-ins.'" :crumbs="['Dashboard' => route('admin.dashboard'), 'Reports']">
    @if($forcedEvent)
        <a href="{{ route('admin.events.dashboard', $forcedEvent) }}" class="a-btn a-btn--outline">Event Dashboard</a>
        <a href="{{ route('admin.reports.index') }}" class="a-btn a-btn--ghost">All Events Reports</a>
    @endif
</x-admin.page-header>

<x-admin.flash />

<x-admin.card title="Period" subtitle="Pick a range or enter custom dates.">
    <div class="a-m-range a-chips">
        @foreach($rangeOptions as $key => $label)
            <a href="{{ $forcedEvent ? route('admin.events.report', ['event' => $forcedEvent, 'range' => $key]) : route('admin.reports.index', ['range' => $key, 'event' => $selectedEvent ?: null]) }}"
               class="a-chip {{ $selectedRange === $key ? 'is-active' : '' }}" @if($selectedRange === $key) aria-current="true" @endif>{{ $label }}</a>
        @endforeach
    </div>
    <form method="GET" action="{{ $forcedEvent ? route('admin.events.report', $forcedEvent) : route('admin.reports.index') }}" class="a-m-range-form">
        <input type="hidden" name="range" value="custom">
        <div class="a-field"><label class="form-label" for="rep-from">From</label>
            <input type="text" id="rep-from" name="from" class="form-control js-flatpickr" value="{{ optional($startAt)->format('Y-m-d') }}" data-date-format="Y-m-d" data-alt-input="true" data-alt-format="m/d/Y" placeholder="From date"></div>
        <div class="a-field"><label class="form-label" for="rep-to">To</label>
            <input type="text" id="rep-to" name="to" class="form-control js-flatpickr" value="{{ optional($endAt)->format('Y-m-d') }}" data-date-format="Y-m-d" data-alt-input="true" data-alt-format="m/d/Y" placeholder="To date"></div>
        <button type="submit" class="a-btn a-btn--primary">Apply</button>
    </form>
</x-admin.card>

<p class="a-muted" style="margin:0 0 18px">
    Showing <strong>{{ $selectedEvent !== '' ? $selectedEvent : 'all events' }}</strong> within <strong>{{ $rangeLabel }}</strong>.
</p>

<section class="a-m-section" aria-labelledby="rep-paid">
    <h2 class="a-m-section__title" id="rep-paid">Paid sales</h2>
    <div class="a-grid a-grid--3">
        <x-admin.kpi label="Tickets sold" :value="number_format($totalTickets)" icon="fa-ticket" hint="Paid, completed orders only" />
        <x-admin.kpi label="Paid orders" :value="number_format($totalOrders)" icon="fa-receipt" />
        <x-admin.kpi label="Checked in" :value="number_format($eventReports->sum('total_checked_in'))" icon="fa-circle-check" hint="Paid and guest check-ins" />
    </div>
    <p class="a-hint">This report counts paid orders only. Pending, failed and refunded orders are not included here; review them under Orders and Payments. Refunds and settlements are handled by the payment provider.</p>
</section>

<section class="a-m-section" aria-labelledby="rep-events">
    <h2 class="a-m-section__title" id="rep-events">By event</h2>
    @forelse($eventReports as $report)
        @php
            $malePercent = $report['tickets_sold'] > 0 ? ($report['male_tickets'] / $report['tickets_sold']) * 100 : 0;
            $femalePercent = $report['tickets_sold'] > 0 ? ($report['female_tickets'] / $report['tickets_sold']) * 100 : 0;
        @endphp
        <article class="a-card" aria-label="{{ $report['event_name'] }}">
            <div class="a-card__head">
                <h3 class="a-card__title">{{ $report['event_name'] }}</h3>
                <div class="a-btn-group">
                    <x-admin.badge variant="info">{{ number_format($report['tickets_sold']) }} sold</x-admin.badge>
                </div>
            </div>
            <div class="a-card__body">
                <div class="a-m-report__grid">
                    <div class="a-m-report__block">
                        <h4>Tickets</h4>
                        <dl class="a-kv">
                            <div><dt>Male Tickets</dt><dd>{{ number_format($report['male_tickets']) }}</dd></div>
                            <div><dt>Female Tickets</dt><dd>{{ number_format($report['female_tickets']) }}</dd></div>
                            <div><dt>Guest Invitations</dt><dd>{{ number_format($report['guest_invitations']) }}</dd></div>
                        </dl>
                    </div>
                    <div class="a-m-report__block">
                        <h4>Check-ins</h4>
                        <dl class="a-kv">
                            <div><dt>Paid Checked In</dt><dd>{{ number_format($report['paid_checked_in']) }}</dd></div>
                            <div><dt>Guest Checked In</dt><dd>{{ number_format($report['guest_checked_in']) }}</dd></div>
                            <div><dt>Total Checked In</dt><dd>{{ number_format($report['total_checked_in']) }}</dd></div>
                        </dl>
                    </div>
                    <div class="a-m-report__block">
                        <h4>Gender split</h4>
                        <div class="a-m-bar"><div class="a-m-bar__label"><span>Male</span><strong>{{ round($malePercent) }}%</strong></div><div class="a-m-bar__track"><div class="a-m-bar__fill" style="width: {{ $malePercent }}%"></div></div></div>
                        <div class="a-m-bar"><div class="a-m-bar__label"><span>Female</span><strong>{{ round($femalePercent) }}%</strong></div><div class="a-m-bar__track"><div class="a-m-bar__fill a-m-bar__fill--alt" style="width: {{ $femalePercent }}%"></div></div></div>
                    </div>
                </div>
                @if(count($report['ticket_types']))
                    <div class="a-m-report__block" style="margin-top:20px">
                        <h4>Ticket types</h4>
                        <div class="a-m-chips">
                            @foreach($report['ticket_types'] as $type)
                                <x-admin.badge>{{ $type['name'] }} <strong>{{ number_format($type['count']) }}</strong></x-admin.badge>
                            @endforeach
                        </div>
                    </div>
                @endif
            </div>
        </article>
    @empty
        <x-admin.card>
            <x-admin.empty icon="fa-chart-line" title="No paid completed orders yet" text="No paid completed orders yet, so reports are still empty." />
        </x-admin.card>
    @endforelse
</section>

@push('scripts')
<script src="{{ asset('admin/assets/js/plugins/flatpickr/flatpickr.min.js') }}"></script>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        if (window.flatpickr) {
            flatpickr('.js-flatpickr', { dateFormat: 'Y-m-d', altInput: true, altFormat: 'm/d/Y', allowInput: true });
        }
    });
</script>
@endpush
@endsection
