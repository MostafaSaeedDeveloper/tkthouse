@extends('admin.master')
@section('title', 'Payments')

@php $money = fn ($n) => number_format((float) $n, 2).' EGP'; @endphp

@section('content')
<x-admin.page-header title="Payments" subtitle="Every payment attempt with its gateway status. Read-only: refunds and settlements are handled by the payment provider.">
    @can('payment-methods.view')<a class="a-btn a-btn--outline" href="{{ route('admin.payment-methods.index') }}"><i class="fa fa-gear"></i> Payment methods</a>@endcan
</x-admin.page-header>

<x-admin.flash />

<div class="a-grid a-grid--4" style="margin-bottom:20px">
    <x-admin.kpi label="Collected (paid attempts)" :value="$money($stats['paid'])" icon="fa-circle-check" :hint="$stats['paid_count'].' paid attempts'" />
    <x-admin.kpi label="Processing" :value="number_format($stats['processing'])" icon="fa-hourglass-half" hint="Waiting for the gateway" />
    <x-admin.kpi label="Failed attempts" :value="number_format($stats['failed'])" icon="fa-circle-xmark" hint="Customers can retry" />
    <x-admin.kpi label="Needs review" :value="number_format($stats['review'])" icon="fa-flag" :hint="$stats['refunded'].' refunded orders'" />
</div>

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
                    <td data-label="Status"><x-admin.status :status="$a->status" kind="payment" /></td>
                    <td data-label="Date">{{ $a->created_at->format('d M Y, H:i') }}</td>
                </tr>
            @endforeach
            </tbody>
        </x-admin.table>
        <x-admin.pager :paginator="$attempts" />
    @endif
</x-admin.card>
@endsection
