@extends('admin.master')
@section('title', 'Transactions')
@php $money = fn ($n) => number_format((float) $n, 2).' EGP'; @endphp
@section('content')
<x-admin.page-header title="Transactions" subtitle="Every payment attempt with its provider references. An order can have several attempts; none is overwritten." :crumbs="['Dashboard' => route('admin.dashboard'), 'Payments', 'Transactions']" />
<x-admin.flash />
@include('admin.payments._nav')
<x-admin.filters :action="route('admin.payments.transactions')" :reset="route('admin.payments.transactions')">
    <div class="a-field"><label class="form-label" for="tq">Search</label><input class="form-control" id="tq" name="q" value="{{ $filters['q'] }}" placeholder="Order, PAY-…, transaction, provider ref"></div>
    <div class="a-field"><label class="form-label" for="ts">Status</label><select class="form-select" id="ts" name="status"><option value="">All</option>@foreach(['initiated' => 'Processing', 'paid' => 'Paid', 'failed' => 'Failed', 'expired' => 'Expired'] as $k => $l)<option value="{{ $k }}" @selected($filters['status'] === $k)>{{ $l }}</option>@endforeach</select></div>
    <div class="a-field"><label class="form-label" for="tp">Provider</label><select class="form-select" id="tp" name="provider"><option value="">All</option>@foreach($providers as $p)<option value="{{ $p }}" @selected($filters['provider'] === $p)>{{ ucfirst($p) }}</option>@endforeach</select></div>
    @if($seeAccounts)<div class="a-field"><label class="form-label" for="ta">Merchant account</label><select class="form-select" id="ta" name="account"><option value="">All</option>@foreach($accounts as $a)<option value="{{ $a->id }}" @selected($filters['accountId'] === $a->id)>{{ $a->name }}</option>@endforeach</select></div>@endif
    <div class="a-field"><label class="form-label" for="tf">From</label><input type="date" class="form-control" id="tf" name="from" value="{{ request('from') }}"></div>
    <div class="a-field"><label class="form-label" for="tt">To</label><input type="date" class="form-control" id="tt" name="to" value="{{ request('to') }}"></div>
</x-admin.filters>
<x-admin.card flush>
    @if($attempts->isEmpty())
        <x-admin.empty icon="fa-credit-card" title="No transactions found" text="Attempts appear here when customers start paying." />
    @else
    <x-admin.table stack compact caption="Payment transactions">
        <thead><tr><th>Reference</th><th>Order</th><th>Provider</th>@if($seeAccounts)<th>Merchant account</th>@endif<th>Provider transaction</th><th class="a-num">Amount</th><th>Status</th><th>Date</th></tr></thead>
        <tbody>
        @foreach($attempts as $a)
            <tr>
                <td data-label="Reference"><a class="a-table__main" href="{{ route('admin.payments.transactions.show', $a) }}">{{ $a->internal_reference ?: '#'.$a->id }}</a>@if(! $a->internal_reference)<span class="a-table__sub">legacy</span>@endif</td>
                <td data-label="Order"><a href="{{ route('admin.orders.show', $a->order) }}">#{{ $a->order->order_number }}</a></td>
                <td data-label="Provider">{{ ucfirst($a->provider) }}<span class="a-table__sub">{{ $a->method_code }}</span></td>
                @if($seeAccounts)<td data-label="Merchant account">{{ $a->integrationAccount?->name ?? 'Legacy configuration' }}</td>@endif
                <td data-label="Provider transaction"><span class="a-table__sub" style="color:var(--a-ink)">{{ $a->transaction_id ?: '—' }}</span>@if($a->provider_reference)<span class="a-table__sub">Ref {{ $a->provider_reference }}</span>@endif</td>
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
