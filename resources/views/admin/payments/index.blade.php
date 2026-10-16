@extends('admin.master')
@section('title', 'Payments')

@php $money = fn ($n) => number_format((float) $n, 2); @endphp

@section('content')
<x-admin.page-header title="Payments" subtitle="Every payment attempt with its gateway status. Read-only: refunds and settlements are handled by the payment provider." :crumbs="['Dashboard' => route('admin.dashboard'), 'Payments']">
    @can('payment-methods.view')<a class="a-btn a-btn--outline" href="{{ route('admin.payment-methods.index') }}"><i class="fa fa-gear"></i> Payment methods</a>@endcan
</x-admin.page-header>

<x-admin.flash />

<div class="a-grid a-m-kpis">
    <x-admin.kpi class="a-m-kpi--success" label="Paid" :value="$money($stats['paid']).' EGP'" icon="fa-circle-check" :hint="number_format($stats['paid_count']).' paid attempts'" />
    <x-admin.kpi class="a-m-kpi--warning" label="Pending" :value="number_format($stats['processing'])" icon="fa-hourglass-half" :hint="$stats['review'] > 0 ? number_format($stats['review']).' need review' : 'Processing at the gateway'" />
    <x-admin.kpi class="a-m-kpi--danger" label="Failed" :value="number_format($stats['failed'])" icon="fa-circle-xmark" hint="Customers can retry" />
    <x-admin.kpi class="a-m-kpi--info" label="Refunded" :value="number_format($stats['refunded'])" icon="fa-rotate-left" hint="Refunded orders" />
</div>

@if(! $liveGateways || $fakeAllowed)
    <x-admin.alert type="warning">
        <strong>Non-production environment.</strong>
        @unless($liveGateways) Live gateways are switched off here, so only test payments can be created. @endunless
        @if($fakeAllowed) The test gateway is enabled. @endif
    </x-admin.alert>
@endif

<x-admin.filters :action="route('admin.payments.index')" :reset="route('admin.payments.index')">
    <x-admin.search name="q" :value="$search" placeholder="Order, reference or transaction id" />
    <x-admin.select label="Status" name="status" :options="$filters" :value="$filter" class="no-select2" />
    <x-admin.select label="Gateway" name="provider" :options="$providers->mapWithKeys(fn ($p) => [$p => ucfirst($p)])->all()" :value="$provider" placeholder="All gateways" class="no-select2" />
</x-admin.filters>

<x-admin.card flush title="Payment attempts" :subtitle="$attempts->total().' total'">
    @if($attempts->isEmpty())
        <x-admin.empty icon="fa-credit-card" title="No payment attempts found" text="Attempts appear here when customers start paying. Try resetting the filters." />
    @else
        <x-admin.table stack caption="Payment attempts">
            <thead><tr><th>Order</th><th>Customer</th><th>Gateway &amp; reference</th><th class="a-num">Amount</th><th>Status</th></tr></thead>
            <tbody>
            @foreach($attempts as $a)
                @php
                    $review = (bool) data_get($a->meta, 'needs_review');
                    $customerName = trim(($a->order->customer?->first_name ?? '').' '.($a->order->customer?->last_name ?? '')) ?: '—';
                    $reference = $a->transaction_id ?: ($a->reference ?: '—');
                @endphp
                <tr>
                    <td data-label="Order"><a class="a-table__main a-num" href="{{ route('admin.orders.show', $a->order) }}">#{{ $a->order->order_number }}</a><span class="a-table__sub">{{ $a->created_at->format('d M Y, H:i') }}</span></td>
                    <td data-label="Customer"><span class="a-table__main a-trunc a-m-trunc-sm">{{ $customerName }}</span><span class="a-table__sub a-trunc a-m-trunc-sm" title="{{ $a->order->customer?->email }}">{{ $a->order->customer?->email }}</span></td>
                    <td data-label="Gateway &amp; reference"><span class="a-table__main">{{ ucfirst($a->provider) }} <span class="a-muted">{{ $a->method_code }}</span></span>
                        <span class="a-table__sub a-trunc a-m-trunc-sm a-m-mono" title="{{ $reference }}">{{ $reference }}</span>
                        @if($a->failure_reason)<span class="a-table__sub a-trunc a-m-trunc-sm" title="{{ $a->failure_reason }}">{{ $a->failure_reason }}</span>@endif</td>
                    <td data-label="Amount" class="a-num"><b>{{ $money($a->amount) }}</b> <span class="a-muted">EGP</span></td>
                    <td data-label="Status"><x-admin.status :status="$a->status" kind="payment" />@if($review)<span class="a-table__sub"><x-admin.badge variant="warning">Needs review</x-admin.badge></span>@endif</td>
                </tr>
            @endforeach
            </tbody>
        </x-admin.table>
        <x-admin.pager :paginator="$attempts" />
    @endif
</x-admin.card>
@endsection
