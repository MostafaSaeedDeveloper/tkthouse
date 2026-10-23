@extends('admin.master')
@section('title', $seesTeam ? 'Agent report' : 'Reports')
@push('styles')<link rel="stylesheet" href="{{ asset('admin-v2/misc.css') }}?v={{ filemtime(public_path('admin-v2/misc.css')) }}">@endpush
@section('content')
@php $money = fn ($n) => number_format((float) $n, 2); @endphp
<x-admin.page-header :title="$seesTeam ? 'Agent report' : 'Reports'" :subtitle="$seesTeam ? 'Workflow counts follow the current assignee; collected sales follow who held the order when payment was confirmed.' : 'Orders and sales per event for the selected period.'" :crumbs="$seesTeam ? ['Dashboard' => route('admin.dashboard'), 'Reports', 'Agents'] : ['Dashboard' => route('admin.dashboard'), 'Events']" />
@php $reportRoute = $seesTeam ? 'admin.reports.agents' : 'admin.reports.mine'; @endphp
<x-admin.filters :action="route($reportRoute)" :reset="route($reportRoute)">
    <div class="a-field"><label class="form-label" for="ag-from">From</label><input id="ag-from" type="date" name="from" class="form-control" value="{{ $from->toDateString() }}"></div>
    <div class="a-field"><label class="form-label" for="ag-to">To</label><input id="ag-to" type="date" name="to" class="form-control" value="{{ $to->toDateString() }}"></div>
</x-admin.filters>

@unless($seesTeam)
@php
    $tot = ['orders' => $eventRows->sum('orders'), 'paid' => $eventRows->sum('paid'), 'canceled' => $eventRows->sum('canceled'), 'tickets' => $eventRows->sum('tickets'), 'collected' => $eventRows->sum('collected')];
    $totClosed = $tot['paid'] + $tot['canceled'];
@endphp
<div class="a-grid a-grid--4" style="margin-bottom:20px">
    <x-admin.kpi label="Collected" :value="$money($tot['collected']).' EGP'" icon="fa-wallet" :hint="$from->format('d M').' – '.$to->format('d M Y')" />
    <x-admin.kpi label="Tickets sold" :value="number_format($tot['tickets'])" icon="fa-ticket" hint="Paid orders" />
    <x-admin.kpi label="Orders" :value="number_format($tot['orders'])" icon="fa-receipt" :hint="number_format($tot['paid']).' paid · '.number_format($eventRows->sum('pending')).' pending · '.number_format($tot['canceled']).' canceled'" />
    <x-admin.kpi label="Win rate" :value="$totClosed > 0 ? round($tot['paid'] / $totClosed * 100, 1).'%' : '—'" icon="fa-chart-line" hint="Paid ÷ (paid + canceled)" />
</div>

@if($eventRows->isEmpty())
    <x-admin.card title="Events"><x-admin.empty title="No activity" text="No orders in this period." icon="fa-chart-column" /></x-admin.card>
@else
<div class="a-evrep">
    @foreach($eventRows as $r)
        @php $closed = $r['paid'] + $r['canceled']; $paidPct = $r['orders'] > 0 ? round($r['paid'] / $r['orders'] * 100) : 0; $pendPct = $r['orders'] > 0 ? round($r['pending'] / $r['orders'] * 100) : 0; @endphp
        <article class="a-evrep__card">
            <header class="a-evrep__head">
                <h2 class="a-evrep__name">{{ $r['name'] }}</h2>
                <div class="a-evrep__money">{{ $money($r['collected']) }} <small>EGP</small></div>
            </header>
            <div class="a-evrep__bar" role="img" aria-label="{{ $r['paid'] }} paid, {{ $r['pending'] }} pending, {{ $r['canceled'] }} canceled">
                <span class="is-paid" style="width:{{ $paidPct }}%"></span><span class="is-pending" style="width:{{ $pendPct }}%"></span>
            </div>
            <dl class="a-evrep__stats">
                <div><dt>Orders</dt><dd>{{ $r['orders'] }}</dd></div>
                <div><dt>Paid</dt><dd>{{ $r['paid'] }}</dd></div>
                <div><dt>Pending</dt><dd>{{ $r['pending'] }}</dd></div>
                <div><dt>Canceled</dt><dd>{{ $r['canceled'] }}</dd></div>
                <div><dt>Win rate</dt><dd>{{ $r['win_rate'] === null ? '—' : $r['win_rate'].'%' }}</dd></div>
                <div><dt>Tickets sold</dt><dd>{{ $r['tickets'] }}</dd></div>
            </dl>
            @if(! empty($r['methods']))
            <div class="a-chips a-evrep__methods">
                @foreach($r['methods'] as $m => $c)<span class="a-badge">{{ ucwords(str_replace('_', ' ', $m ?: 'unknown')) }} × {{ $c }}</span>@endforeach
            </div>
            @endif
        </article>
    @endforeach
</div>
@endif
@else

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
@endunless
@endsection
