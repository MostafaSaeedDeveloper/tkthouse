@extends('admin.master')
@section('title', 'Transactions')
@php $money = fn ($n) => number_format((float) $n, 2).' EGP'; @endphp
@section('content')
<x-admin.page-header title="Transactions" subtitle="Every payment attempt with its provider references. An order can have several attempts; none is overwritten." :crumbs="['Dashboard' => route('admin.dashboard'), 'Payments', 'Transactions']" />
<x-admin.flash />
@include('admin.payments._nav')
@include('admin.payments._tx-source')
<x-admin.filters :action="route('admin.payments.transactions')" :reset="route('admin.payments.transactions')">
    <div class="a-field"><label class="form-label" for="tq">Search</label><input class="form-control" id="tq" name="q" value="{{ $filters['q'] }}" placeholder="Order, PAY-…, transaction, provider ref"></div>
    <div class="a-field"><label class="form-label" for="ts">Status</label><select class="form-select" id="ts" name="status"><option value="">All</option>@foreach(['initiated' => 'Processing', 'paid' => 'Paid', 'failed' => 'Failed', 'expired' => 'Expired'] as $k => $l)<option value="{{ $k }}" @selected($filters['status'] === $k)>{{ $l }}</option>@endforeach</select></div>
    <div class="a-field"><label class="form-label" for="tp">Provider</label><select class="form-select" id="tp" name="provider"><option value="">All</option>@foreach($providers as $p)<option value="{{ $p }}" @selected($filters['provider'] === $p)>{{ ucfirst($p) }}</option>@endforeach</select></div>
    @if($seeAccounts)<div class="a-field"><label class="form-label" for="ta">Merchant account</label><select class="form-select" id="ta" name="account"><option value="">All</option>@foreach($accounts as $a)<option value="{{ $a->id }}" @selected($filters['accountId'] === $a->id)>{{ $a->name }}</option>@endforeach</select></div>@endif
    <div class="a-field"><label class="form-label" for="tm">Payment method</label><select class="form-select" id="tm" name="pm"><option value="">All</option>@foreach($methods as $m)<option value="{{ $m }}" @selected($filters['method'] === $m)>{{ $m }}</option>@endforeach</select></div>
    <div class="a-field"><label class="form-label" for="tx">Transaction ID</label><input class="form-control" id="tx" name="txn" value="{{ $filters['txn'] }}" placeholder="Provider transaction / reference"></div>
    <div class="a-field"><label class="form-label" for="to2">Order reference</label><input class="form-control" id="to2" name="order" value="{{ $filters['orderRef'] }}" placeholder="Order number"></div>
    <div class="a-field"><label class="form-label" for="tf">From</label><input type="date" class="form-control" id="tf" name="from" value="{{ request('from') }}"></div>
    <div class="a-field"><label class="form-label" for="tt">To</label><input type="date" class="form-control" id="tt" name="to" value="{{ request('to') }}"></div>
</x-admin.filters>
<x-admin.card flush>
    @if($attempts->isEmpty())
        <x-admin.empty icon="fa-credit-card" title="No transactions found" text="Attempts appear here when customers start paying." />
    @else
    <x-admin.table stack compact caption="Payment transactions">
        <thead><tr><th>Payment / order</th><th>Provider transaction</th><th>Provider &amp; method</th>@if($seeAccounts)<th>Merchant account</th>@endif<th class="a-num">Amount</th><th>Status / refund</th><th>Created / paid</th></tr></thead>
        <tbody>
        @foreach($attempts as $a)
            @php
                $rs = $refunds->get($a->id, collect());
                $refunded = $rs->where('status', 'succeeded')->sum('amount_minor');
                $open = $rs->whereIn('status', ['requested', 'processing', 'unknown'])->count();
                $c = $a->order->customer;
            @endphp
            <tr>
                <td data-label="Payment / order"><a class="a-table__main" href="{{ route('admin.payments.transactions.show', $a) }}">{{ $a->internal_reference ?: '#'.$a->id }}</a>@if(! $a->internal_reference)<span class="a-table__sub">legacy</span>@endif<span class="a-table__sub"><a href="{{ route('admin.orders.show', $a->order) }}">Order #{{ $a->order->order_number }}</a> · {{ $c ? trim(($c->first_name ?? '').' '.($c->last_name ?? '')) : '—' }}</span></td>
                <td data-label="Provider transaction"><span class="a-table__sub" style="color:var(--a-ink)">{{ $a->transaction_id ?: '—' }}</span>@if($a->provider_reference)<span class="a-table__sub">Ref {{ $a->provider_reference }}</span>@endif</td>
                <td data-label="Provider &amp; method">{{ ucfirst($a->provider) }}<span class="a-table__sub">{{ $a->method_code }}</span></td>
                @if($seeAccounts)<td data-label="Merchant account">{{ $a->integrationAccount?->name ?? 'Legacy configuration' }}</td>@endif
                <td data-label="Amount" class="a-num">{{ number_format((float) $a->amount, 2) }} {{ $a->currency ?: 'EGP' }}</td>
                <td data-label="Status / refund"><x-admin.status :status="$a->displayStatus()" kind="payment" />@if($refunded > 0)<x-admin.badge variant="info">{{ number_format($refunded / 100, 2) }} refunded</x-admin.badge>@endif @if($open)<x-admin.badge variant="warning">{{ $open }} refund in progress</x-admin.badge>@endif</td>
                <td data-label="Created / paid">{{ $a->created_at->format('d M Y, H:i') }}<span class="a-table__sub">{{ ($a->paid_at ?? $a->settled_at) ? 'Paid '.($a->paid_at ?? $a->settled_at)->format('d M, H:i') : 'Not paid' }}</span></td>
            </tr>
        @endforeach
        </tbody>
    </x-admin.table>
    <x-admin.pager :paginator="$attempts" />
    @endif
</x-admin.card>
@endsection
