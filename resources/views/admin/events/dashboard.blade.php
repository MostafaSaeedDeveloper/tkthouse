@extends('admin.master')
@section('title', $event->name.' Dashboard')

@section('content')
<x-admin.page-header :title="$event->name.' Dashboard'" subtitle="Event-specific summary for tickets sold, invitations, guest list, revenue, check-ins and analytics." :crumbs="['Dashboard' => route('admin.dashboard'), 'Events' => route('admin.events.index'), $event->name]">
    <a href="{{ route('admin.events.report', $event) }}" class="a-btn a-btn--outline">View Event Report</a>
    <a href="{{ route('admin.dashboard') }}" class="a-btn a-btn--outline">All Events Dashboard</a>
</x-admin.page-header>

<x-admin.flash />

<div class="a-ev-toolbar">
    <div class="a-ev-range" role="group" aria-label="Date range">
        @foreach($rangeOptions as $key => $label)
            <a href="{{ route('admin.events.dashboard', ['event' => $event, 'range' => $key]) }}"
               class="a-chip {{ $selectedRange === $key ? 'is-active' : '' }}" @if($selectedRange === $key) aria-current="true" @endif>{{ $label }}</a>
        @endforeach
    </div>
    <x-admin.filters :action="route('admin.events.dashboard', $event)">
        <input type="hidden" name="range" value="custom">
        <div class="a-field"><label class="form-label" for="evFrom">From date</label><input type="text" id="evFrom" name="from" class="form-control js-flatpickr" value="{{ optional($startAt)->format('Y-m-d') }}" placeholder="From date"></div>
        <div class="a-field"><label class="form-label" for="evTo">To date</label><input type="text" id="evTo" name="to" class="form-control js-flatpickr" value="{{ optional($endAt)->format('Y-m-d') }}" placeholder="To date"></div>
    </x-admin.filters>
</div>

<p class="a-ev-context">Showing <strong>{{ $event->name }}</strong> within <strong>{{ $rangeLabel }}</strong>.</p>

<div class="a-grid a-grid--4">
    <x-admin.kpi label="Tickets Sold" :value="number_format($ticketsSold)" icon="fa-ticket" />
    <x-admin.kpi label="Invitations Sent" :value="number_format($guestInvitations)" icon="fa-envelope" />
    <x-admin.kpi label="Guest List Check-ins" :value="number_format($guestCheckedIn)" icon="fa-user-check" />
    <x-admin.kpi label="Paid Check-ins" :value="number_format($paidCheckedIn)" icon="fa-circle-check" />
    <x-admin.kpi label="Scanner Activity" :value="number_format($totalScans)" icon="fa-qrcode" />
</div>
@endsection

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function () {
        if (window.flatpickr) {
            flatpickr('.js-flatpickr', { dateFormat: 'Y-m-d', altInput: true, altFormat: 'm/d/Y', allowInput: true });
        }
    });
</script>
@endpush
