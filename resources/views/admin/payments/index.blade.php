@extends('admin.master')
@section('title', 'Payments')

@php $money = fn ($n) => number_format((float) $n, 2).' EGP'; @endphp

@section('content')
<x-admin.page-header title="Payments" subtitle="Every payment attempt with its gateway status.">
    @can('payment-methods.view')<a class="a-btn a-btn--outline" href="{{ route('admin.payment-methods.index') }}"><i class="fa fa-gear"></i> Payment methods</a>@endcan
</x-admin.page-header>

<x-admin.flash />
@include('admin.payments._nav')

<form method="GET" action="{{ route('admin.payments.index') }}" class="a-m-range-form" style="margin-bottom:14px">
    <div class="a-field"><label class="form-label" for="pf">From</label><input id="pf" type="date" name="from" class="form-control" value="{{ $from->toDateString() }}"></div>
    <div class="a-field"><label class="form-label" for="pt">To</label><input id="pt" type="date" name="to" class="form-control" value="{{ $to->toDateString() }}"></div>
    <button class="a-btn a-btn--dark" type="submit">Apply</button>
</form>
@php $m = fn ($minor) => number_format($minor / 100, 2).' EGP'; $t = $report['totals']; @endphp
<div class="a-grid a-grid--4" style="margin-bottom:12px">
    <x-admin.kpi label="Total collected" :value="$m($t['collected'])" icon="fa-circle-check" :hint="$t['successful'].' successful transactions'" />
    <x-admin.kpi label="Refunded" :value="$m($t['refunded'])" icon="fa-rotate-left" :hint="$t['refund_count'].' refunds · '.$t['partial_refunds'].' partial'" />
    <x-admin.kpi label="Net collected" :value="$m($t['net'])" icon="fa-scale-balanced" hint="Collected − refunded (not a bank settlement)" />
    <x-admin.kpi label="Pending / failed" :value="$t['pending'].' / '.$t['failed']" icon="fa-hourglass-half" :hint="$m($t['pending_amount']).' waiting for the gateway'" />
</div>
@php
    $p = $previous['totals'];
    $delta = function (int $now, int $then) { if ($then === 0) { return $now === 0 ? '—' : 'new'; } $d = round(($now - $then) / abs($then) * 100); return ($d > 0 ? '+' : '').$d.'%'; };
@endphp
<x-admin.card title="Compared with the previous period" :subtitle="$previous['from']->format('d M Y').' – '.$previous['to']->format('d M Y').' vs '.$from->format('d M Y').' – '.$to->format('d M Y')" flush style="margin-bottom:12px">
    <x-admin.table stack compact caption="Period comparison"><thead><tr><th>Figure</th><th class="a-num">Previous</th><th class="a-num">This period</th><th class="a-num">Change</th></tr></thead><tbody>
        @foreach(['collected' => ['Total collected', true], 'successful' => ['Successful transactions', false], 'failed' => ['Failed transactions', false], 'pending' => ['Pending transactions', false], 'refunded' => ['Refunded', true], 'net' => ['Net collected', true]] as $key => [$label, $isMoney])
            <tr><td data-label="Figure">{{ $label }}</td><td data-label="Previous" class="a-num">{{ $isMoney ? $m($p[$key]) : $p[$key] }}</td><td data-label="This period" class="a-num">{{ $isMoney ? $m($t[$key]) : $t[$key] }}</td><td data-label="Change" class="a-num">{{ $delta((int) $t[$key], (int) $p[$key]) }}</td></tr>
        @endforeach
    </tbody></x-admin.table>
</x-admin.card>
@if($t['refunds_in_progress'] > 0)<p class="a-hint">{{ $t['refunds_in_progress'] }} refund(s) worth {{ $m($t['refunds_in_progress_amount']) }} are requested or processing: they are <strong>not</strong> counted as refunded until confirmed.</p>@endif
<div class="a-grid a-grid--2" style="margin-bottom:20px">
    @foreach(array_filter(['Provider' => ['rows' => $report['by_provider'], 'label' => fn ($k) => ucfirst($k)], 'Payment method' => ['rows' => $report['by_method'], 'label' => fn ($k) => $methodNames[$k] ?? ($k ?: 'Unknown')], 'Merchant account' => $report['by_account'] === null ? null : ['rows' => $report['by_account'], 'label' => fn ($k) => $k === '' || $k === '0' ? 'Legacy configuration' : ($accountNames[(int) $k] ?? '#'.$k)]]) as $title => $g)
    <x-admin.card flush :title="$title.' breakdown'">
        <x-admin.table stack compact :caption="$title.' breakdown'"><thead><tr><th>{{ $title }}</th><th class="a-num">Payments</th><th class="a-num">Collected</th><th class="a-num">Refunded</th><th class="a-num">Net</th></tr></thead><tbody>
            @forelse($g['rows'] as $k => $r)<tr><td data-label="{{ $title }}">{{ ($g['label'])($k) }}</td><td data-label="Payments" class="a-num">{{ $r['count'] }}</td><td data-label="Collected" class="a-num">{{ number_format($r['collected'] / 100, 2) }}</td><td data-label="Refunded" class="a-num">{{ number_format($r['refunded'] / 100, 2) }}</td><td data-label="Net" class="a-num">{{ number_format($r['net'] / 100, 2) }}</td></tr>@empty<tr><td colspan="5" class="a-muted">No payments in this period.</td></tr>@endforelse
        </tbody></x-admin.table>
    </x-admin.card>
    @endforeach
</div>

@if($imported)
@php $io = $imported['provider_only']; @endphp
<x-admin.card title="Imported from the provider (not included in the figures above)" :subtitle="'Looked up or imported from provider exports for the same dates. Never added to the local totals, so nothing is counted twice. '.$imported['already_in_local'].' imported record(s) are linked to a local payment and already counted above.'" style="margin-bottom:20px">
    <div class="a-grid a-grid--4">
        <x-admin.kpi label="Provider-only paid" :value="$io['paid_count'].' · '.$m($io['paid_volume'])" icon="fa-file-import" hint="Paid at the provider, no local order" />
        <x-admin.kpi label="Failed / pending" :value="$io['failed'].' / '.$io['pending']" icon="fa-hourglass-half" hint="Provider-only records" />
        <x-admin.kpi label="Refunded / voided" :value="$io['refunded'].' / '.$io['voided']" icon="fa-rotate-left" hint="Provider-only records" />
        <x-admin.kpi label="Unverified (CSV)" :value="$io['unverified']" icon="fa-circle-question" hint="Check them with the provider" />
    </div>
    <p class="a-hint" style="margin:10px 0 0">Provider-only paid by provider: @forelse($imported['by_provider'] as $k => $r){{ ucfirst($k) }} {{ $r['count'] }} ({{ $m($r['volume']) }})@if(! $loop->last), @endif @empty none @endforelse · <a href="{{ route('admin.payments.imported') }}">Open imported transactions</a></p>
</x-admin.card>
@endif

@if(! $liveGateways || $fakeAllowed)
    <x-admin.alert type="warning">
        <strong>Non-production environment.</strong>
        @unless($liveGateways) Live gateways are switched off here, so only test payments can be created. @endunless
        @if($fakeAllowed) The test gateway is enabled. @endif
    </x-admin.alert>
@endif

<x-admin.filters :action="route('admin.payments.index')" :reset="route('admin.payments.index')">
    <div class="a-field"><label class="form-label" for="pq">Search</label><input class="form-control" id="pq" name="q" value="{{ $search }}" placeholder="Order, reference or transaction id"></div>
    <div class="a-field"><label class="form-label" for="pstatus">Status</label>
        <select class="form-select" id="pstatus" name="status">@foreach($filters as $k => $label)<option value="{{ $k }}" @selected($filter === $k)>{{ $label }}</option>@endforeach</select></div>
    <div class="a-field"><label class="form-label" for="pprov">Gateway</label>
        <select class="form-select" id="pprov" name="provider"><option value="">All gateways</option>@foreach($providers as $p)<option value="{{ $p }}" @selected($provider === $p)>{{ ucfirst($p) }}</option>@endforeach</select></div>
</x-admin.filters>

<x-admin.card flush>
    @if($attempts->isEmpty())
        <x-admin.empty icon="fa-credit-card" title="No payment attempts found" text="Attempts appear here when customers start paying." />
    @else
        <x-admin.table stack caption="Payment attempts">
            <thead><tr><th>Order</th><th>Customer</th><th>Gateway</th><th>Reference</th><th class="a-num">Amount</th><th>Status</th><th>Date</th></tr></thead>
            <tbody>
            @foreach($attempts as $a)
                @php $review = (bool) data_get($a->meta, 'needs_review'); @endphp
                <tr>
                    <td data-label="Order"><a class="a-table__main" href="{{ route('admin.orders.show', $a->order) }}">#{{ $a->order->order_number }}</a>@if($review)<x-admin.badge variant="warning">Needs review</x-admin.badge>@endif</td>
                    <td data-label="Customer"><span class="a-table__main">{{ trim(($a->order->customer?->first_name ?? '').' '.($a->order->customer?->last_name ?? '')) ?: '—' }}</span><span class="a-table__sub">{{ $a->order->customer?->email }}</span></td>
                    <td data-label="Gateway">{{ ucfirst($a->provider) }}<span class="a-table__sub">{{ $a->method_code }}</span></td>
                    <td data-label="Reference"><span class="a-table__sub" style="color:var(--a-ink)">{{ $a->transaction_id ?: ($a->reference ?: '—') }}</span>@if($a->failure_reason)<span class="a-table__sub">{{ $a->failure_reason }}</span>@endif</td>
                    <td data-label="Amount" class="a-num">{{ $money($a->amount) }}</td>
                    <td data-label="Status"><x-admin.status :status="$a->displayStatus()" kind="payment" /></td>
                    <td data-label="Date">{{ $a->created_at->format('d M Y, H:i') }}</td>
                </tr>
            @endforeach
            </tbody>
        </x-admin.table>
        <x-admin.pager :paginator="$attempts" />
    @endif
</x-admin.card>
@endsection
