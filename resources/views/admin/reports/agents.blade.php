@extends('admin.master')
@section('title', 'Agent report')
@push('styles')<link rel="stylesheet" href="{{ asset('admin-v2/misc.css') }}?v={{ filemtime(public_path('admin-v2/misc.css')) }}">@endpush
@section('content')
@php $money = fn ($n) => number_format((float) $n, 2); @endphp
<x-admin.page-header :title="$seesTeam ? 'Agent report' : 'Report'" subtitle="Workflow counts follow the current assignee; collected sales follow who held the order when payment was confirmed." :crumbs="['Dashboard' => route('admin.dashboard'), 'Reports', $seesTeam ? 'Agents' : 'Mine']" />
<x-admin.filters :action="route('admin.reports.agents')" :reset="route('admin.reports.agents')">
    <div class="a-field"><label class="form-label" for="ag-from">From</label><input id="ag-from" type="date" name="from" class="form-control" value="{{ $from->toDateString() }}"></div>
    <div class="a-field"><label class="form-label" for="ag-to">To</label><input id="ag-to" type="date" name="to" class="form-control" value="{{ $to->toDateString() }}"></div>
</x-admin.filters>

<x-admin.card flush :title="$seesTeam ? 'Per agent' : 'Summary'" :subtitle="$from->format('d M Y').' – '.$to->format('d M Y')">
    @if($data['rows']->isEmpty())
        <x-admin.empty title="No activity" text="No assigned orders or attributed sales in this period." icon="fa-chart-column" />
    @else
    <x-admin.table stack caption="Agent performance">
        <thead><tr>
            <th scope="col">Agent</th><th scope="col" class="a-num">Assigned</th><th scope="col" class="a-num">Pending</th><th scope="col" class="a-num">Paid</th>
            <th scope="col" class="a-num">Canceled</th><th scope="col" class="a-num">Win rate</th><th scope="col" class="a-num">Tickets sold</th><th scope="col" class="a-num">Collected (EGP)</th><th scope="col" class="a-num">Refunded</th><th scope="col" class="a-num">Net</th><th scope="col">Payment methods</th>@if($showAccounts)<th scope="col">Merchant accounts</th>@endif
        </tr></thead>
        <tbody>
        @foreach($data['rows'] as $r)
            <tr>
                <td data-label="Agent"><span class="a-table__main">{{ $r['name'] }}</span></td>
                <td data-label="Assigned" class="a-num">{{ $r['assigned'] }}</td>
                <td data-label="Pending" class="a-num">{{ $r['pending'] }}</td>
                <td data-label="Paid" class="a-num">{{ $r['paid'] }}</td>
                <td data-label="Canceled" class="a-num">{{ $r['canceled'] }}</td>
                <td data-label="Win rate" class="a-num">{{ $r['win_rate'] === null ? '—' : $r['win_rate'].'%' }}</td>
                <td data-label="Tickets sold" class="a-num">{{ $r['tickets'] }}</td>
                <td data-label="Collected" class="a-num">{{ $money($r['collected']) }}</td>
                <td data-label="Refunded" class="a-num">{{ $money($r['refunded']) }}</td>
                <td data-label="Net" class="a-num">{{ $money($r['net']) }}</td>
                @if($showAccounts)<td data-label="Merchant accounts" class="a-wrap">@forelse($r['accounts'] as $aid => $c)<span class="a-table__sub">{{ $accountNames[$aid] ?? 'Legacy configuration' }} × {{ $c }}</span>@empty — @endforelse</td>@endif
                <td data-label="Payment methods" class="a-wrap">@forelse($r['methods'] as $m => $c)<span class="a-table__sub">{{ ucwords(str_replace('_', ' ', $m ?: 'unknown')) }} × {{ $c }}</span>@empty — @endforelse</td>
            </tr>
        @endforeach
        </tbody>
    </x-admin.table>
    @endif
</x-admin.card>

@if($seesTeam)
<div class="a-grid a-grid--2" style="margin-top:20px">
    <x-admin.card title="Unassigned queue"><p><strong>{{ $data['unassigned']['orders'] }}</strong> order(s) created in this period have no assignee ({{ $data['unassigned']['pending'] }} still pending).
        <a href="{{ route('admin.orders.index', ['assignee' => 'unassigned']) }}">View them</a></p></x-admin.card>
    <x-admin.card title="Unattributed sales"><p><strong>{{ $data['unattributed']['orders'] }}</strong> order(s), {{ $money($data['unattributed']['amount']) }} EGP paid in this period without an attributed agent (paid before assignment was enabled, or never assigned). They are never guessed onto anyone.</p></x-admin.card>
</div>
@endif
<p class="a-hint" style="margin-top:12px">"Collected" is the order total of paid orders, "Refunded" only counts refunds the provider (or a person) confirmed; neither is a bank settlement, which is not tracked.</p>
@endsection
