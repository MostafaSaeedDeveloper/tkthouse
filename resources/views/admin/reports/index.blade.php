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

<div class="a-chips a-m-period" role="group" aria-label="Period">
    @foreach($rangeOptions as $key => $label)
        <a href="{{ $forcedEvent ? route('admin.events.report', ['event' => $forcedEvent, 'range' => $key]) : route('admin.reports.index', ['range' => $key, 'event' => $selectedEvent ?: null]) }}"
           class="a-chip {{ $selectedRange === $key ? 'is-active' : '' }}" @if($selectedRange === $key) aria-current="true" @endif>{{ $label }}</a>
    @endforeach
</div>

<x-admin.filters :action="$forcedEvent ? route('admin.events.report', $forcedEvent) : route('admin.reports.index')" :reset="$forcedEvent ? route('admin.events.report', $forcedEvent) : route('admin.reports.index')">
    <input type="hidden" name="range" value="custom">
    <x-admin.input label="From" name="from" type="date" id="rep-from" :value="optional($startAt)->format('Y-m-d')" />
    <x-admin.input label="To" name="to" type="date" id="rep-to" :value="optional($endAt)->format('Y-m-d')" />
</x-admin.filters>

<p class="a-muted a-m-scope">Showing <strong>{{ $selectedEvent !== '' ? $selectedEvent : 'all events' }}</strong> within <strong>{{ $rangeLabel }}</strong>.</p>

<section class="a-m-section" aria-labelledby="rep-paid">
    <h2 class="a-m-section__title" id="rep-paid">Paid sales</h2>
    <div class="a-grid a-grid--3">
        <x-admin.kpi class="a-m-kpi--success" label="Tickets sold" :value="number_format($totalTickets)" icon="fa-ticket" hint="Paid, completed orders only" />
        <x-admin.kpi class="a-m-kpi--success" label="Paid orders" :value="number_format($totalOrders)" icon="fa-receipt" />
        <x-admin.kpi label="Checked in" :value="number_format($eventReports->sum('total_checked_in'))" icon="fa-circle-check" hint="Paid and guest check-ins" />
    </div>
    <div class="a-m-others">
        <span class="a-muted">Not counted here, review under Orders:</span>
        @can('orders.view')
        <a href="{{ route('admin.orders.index', ['status' => 'pending_payment']) }}"><x-admin.status status="pending_payment" /></a>
        <a href="{{ route('admin.orders.index', ['status' => 'canceled']) }}"><x-admin.status status="canceled" /></a>
        <a href="{{ route('admin.orders.index', ['status' => 'refunded']) }}"><x-admin.status status="refunded" /></a>
        @else
        <x-admin.status status="pending_payment" /> <x-admin.status status="canceled" /> <x-admin.status status="refunded" />
        @endcan
        <span class="a-muted">Refunds and settlements are handled by the payment provider.</span>
    </div>
</section>

<section class="a-m-section" aria-labelledby="rep-events">
    <h2 class="a-m-section__title" id="rep-events">By event</h2>
    @forelse($eventReports as $report)
        @php
            $malePercent = $report['tickets_sold'] > 0 ? ($report['male_tickets'] / $report['tickets_sold']) * 100 : 0;
            $femalePercent = $report['tickets_sold'] > 0 ? ($report['female_tickets'] / $report['tickets_sold']) * 100 : 0;
        @endphp
        <x-admin.card :title="$report['event_name']">
            <x-slot:actions><x-admin.badge variant="info">{{ number_format($report['tickets_sold']) }} sold</x-admin.badge></x-slot:actions>
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
                        <div class="a-m-bar"><div class="a-m-bar__label"><span>Male</span><strong>{{ round($malePercent) }}%</strong></div><div class="a-progress" role="img" aria-label="Male {{ round($malePercent) }}%"><i style="width: {{ $malePercent }}%"></i></div></div>
                        <div class="a-m-bar"><div class="a-m-bar__label"><span>Female</span><strong>{{ round($femalePercent) }}%</strong></div><div class="a-progress" role="img" aria-label="Female {{ round($femalePercent) }}%"><i style="width: {{ $femalePercent }}%"></i></div></div>
                    </div>
                </div>
                @if(count($report['ticket_types']))
                    <div class="a-m-report__block a-m-report__block--types">
                        <h4>Ticket types</h4>
                        <div class="a-chips">
                            @foreach($report['ticket_types'] as $type)
                                <x-admin.badge>{{ $type['name'] }} <strong>{{ number_format($type['count']) }}</strong></x-admin.badge>
                            @endforeach
                        </div>
                    </div>
                @endif
        </x-admin.card>
    @empty
        <x-admin.card>
            <x-admin.empty icon="fa-chart-line" title="No paid completed orders yet" text="No paid completed orders yet, so reports are still empty." />
        </x-admin.card>
    @endforelse
</section>

@endsection
