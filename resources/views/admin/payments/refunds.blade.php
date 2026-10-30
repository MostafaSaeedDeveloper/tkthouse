@extends('admin.master')
@section('title', 'Refunds')
@section('content')
<x-admin.page-header title="Refunds" subtitle="Refunds are financial records, separate from order status and tickets. A request is not a refund until the provider (or a person) confirms it." :crumbs="['Dashboard' => route('admin.dashboard'), 'Payments', 'Refunds']" />
<x-admin.flash />
@include('admin.payments._nav')
<x-admin.filters :action="route('admin.payments.refunds')" :reset="route('admin.payments.refunds')">
    <div class="a-field"><label class="form-label" for="rs">Status</label><select id="rs" name="status" class="form-select"><option value="">All</option>@foreach(['requested','processing','succeeded','failed','rejected','unknown'] as $s)<option value="{{ $s }}" @selected(request('status') === $s)>{{ ucfirst($s) }}</option>@endforeach</select></div>
    <div class="a-field"><label class="form-label" for="rp">Provider</label><select id="rp" name="provider" class="form-select"><option value="">All</option>@foreach(['paymob','fawaterak','fake'] as $p)<option value="{{ $p }}" @selected(request('provider') === $p)>{{ ucfirst($p) }}</option>@endforeach</select></div>
</x-admin.filters>
<x-admin.card flush>
    @if($refunds->isEmpty())
        <x-admin.empty icon="fa-rotate-left" title="No refunds" text="Refunds are started from an order's Payment Information." />
    @else
    <x-admin.table stack compact caption="Refunds">
        <thead><tr><th>Reference</th><th>Order</th><th>Payment</th>@if($seeAccounts)<th>Account</th>@endif<th>Mode</th><th class="a-num">Amount</th><th>Status</th><th>Requested</th></tr></thead>
        <tbody>
        @foreach($refunds as $r)
            <tr>
                <td data-label="Reference"><a class="a-table__main" href="{{ route('admin.payments.refunds.show', $r) }}">{{ $r->internal_reference }}</a><span class="a-table__sub">{{ $r->type }}</span></td>
                <td data-label="Order"><a href="{{ route('admin.orders.show', $r->order) }}">#{{ $r->order->order_number }}</a></td>
                <td data-label="Payment">{{ $r->attempt ? ($r->attempt->internal_reference ?: '#'.$r->payment_attempt_id) : 'Legacy payment' }}<span class="a-table__sub">{{ ucfirst($r->provider) }}</span></td>
                @if($seeAccounts)<td data-label="Account">{{ $r->account?->name ?? 'Legacy' }}</td>@endif
                <td data-label="Mode">{{ $r->mode === 'api' ? 'Provider API' : 'Manual / external' }}</td>
                <td data-label="Amount" class="a-num">{{ $r->amount() }} {{ $r->currency }}</td>
                <td data-label="Status">@include('admin.payments._refund-status', ['refund' => $r])</td>
                <td data-label="Requested">{{ $r->requested_at->format('d M Y, H:i') }}</td>
            </tr>
        @endforeach
        </tbody>
    </x-admin.table>
    <x-admin.pager :paginator="$refunds" />
    @endif
</x-admin.card>
@endsection
