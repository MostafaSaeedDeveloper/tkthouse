@extends('admin.master')
@section('title', 'Imported transactions')
@php $money = fn ($m, $c) => $m === null ? '—' : number_format($m / 100, 2).' '.($c ?: 'EGP'); @endphp
@section('content')
<x-admin.page-header title="Transactions" subtitle="Transactions that exist at the provider but were not created by TKT House. They never create orders and never change an order." :crumbs="['Dashboard' => route('admin.dashboard'), 'Payments', 'Transactions', 'Imported']" />
<x-admin.flash />
@include('admin.payments._nav')
@include('admin.payments._tx-source')
@error('provider')<x-admin.alert type="danger">{{ $message }}</x-admin.alert>@enderror
@error('file')<x-admin.alert type="danger">{{ $message }}</x-admin.alert>@enderror
@error('transaction_id')<x-admin.alert type="danger">{{ $message }}</x-admin.alert>@enderror
@if(session('info'))<x-admin.alert type="info">{{ session('info') }}</x-admin.alert>@endif

@include('admin.payments._capabilities')

<div class="a-grid a-grid--2" style="margin-bottom:12px">
    <x-admin.card title="Provider sync" subtitle="Re-checks the records we already know with each merchant account. It cannot fetch the full list (no such API).">
        @forelse($accounts->where('provider', '!=', 'fake')->concat($accounts->where('provider', 'fake')) as $a)
            @php $s = $syncs->get($a->id); @endphp
            <div style="display:flex;gap:12px;justify-content:space-between;align-items:center;padding:8px 0;border-bottom:1px solid var(--a-line);flex-wrap:wrap">
                <div style="min-width:0"><span class="a-table__main">{{ $a->name }}</span><span class="a-table__sub">{{ ucfirst($a->provider) }} · {{ $a->environment }}</span>
                    @if($s)<span class="a-table__sub">Last sync {{ $s->finished_at?->diffForHumans() ?? 'running' }} · <x-admin.badge :variant="match($s->status){'succeeded'=>'success','partial'=>'warning','failed'=>'danger',default=>'default'}">{{ ucfirst($s->status) }}</x-admin.badge> {{ $s->examined }} checked, {{ $s->updated }} updated, {{ $s->errors }} errors{{ $s->rate_limited ? ' · rate limited' : '' }}</span>
                        @if($s->message)<span class="a-table__sub">{{ $s->message }}</span>@endif
                    @else<span class="a-table__sub">Never synced (a background sync runs every 15 minutes).</span>@endif</div>
                @if($canSync)<form method="POST" action="{{ route('admin.payments.sync', $a) }}" data-a-loading>@csrf<button class="a-btn a-btn--outline a-btn--sm" type="submit"><i class="fa fa-rotate"></i> Sync now</button></form>@endif
            </div>
        @empty
            <p class="a-table__sub">No provider accounts yet.</p>
        @endforelse
    </x-admin.card>

    @if($canImport)
    <x-admin.card title="Bring in transactions" subtitle="The supported ways: one transaction by id, or a dashboard export (CSV).">
        <form method="POST" action="{{ route('admin.payments.imported.lookup') }}" data-a-loading style="display:grid;gap:8px;margin-bottom:16px">@csrf
            <strong>Import by transaction id</strong>
            <select class="form-select" name="account_id" required aria-label="Merchant account">@foreach($accounts as $a)<option value="{{ $a->id }}" @selected(old('account_id') == $a->id)>{{ $a->name }} · {{ ucfirst($a->provider) }}</option>@endforeach</select>
            <input class="form-control" name="transaction_id" required maxlength="120" placeholder="Paymob transaction id / Fawaterak invoice id" value="{{ old('transaction_id') }}" aria-label="Provider transaction id">
            <span class="a-hint">Fetched from the provider with the account you choose. If that account does not own it, nothing is saved.</span>
            <button class="a-btn a-btn--primary" type="submit">Fetch from provider</button>
        </form>
        <form method="POST" action="{{ route('admin.payments.imported.csv') }}" enctype="multipart/form-data" style="display:grid;gap:8px">@csrf
            <strong>Import a dashboard export (CSV)</strong>
            <select class="form-select" name="account_id" required aria-label="Merchant account for the CSV">@foreach($accounts as $a)<option value="{{ $a->id }}">{{ $a->name }} · {{ ucfirst($a->provider) }}</option>@endforeach</select>
            <input class="form-control" type="file" name="file" accept=".csv,text/csv" required aria-label="CSV file">
            <span class="a-hint">Download the transactions export in the provider's dashboard, then upload it. You will map the columns and preview before anything is saved. Imported rows are marked unverified.</span>
            <button class="a-btn a-btn--outline" type="submit">Upload and preview</button>
        </form>
    </x-admin.card>
    @endif
</div>

<x-admin.filters :action="route('admin.payments.imported')" :reset="route('admin.payments.imported')">
    <div class="a-field"><label class="form-label" for="ip">Provider</label><select class="form-select" id="ip" name="provider"><option value="">All</option>@foreach(['paymob', 'fawaterak', 'fake'] as $p)<option value="{{ $p }}" @selected($filters['provider'] === $p)>{{ ucfirst($p) }}</option>@endforeach</select></div>
    <div class="a-field"><label class="form-label" for="ia">Merchant account</label><select class="form-select" id="ia" name="account"><option value="">All</option>@foreach($accounts as $a)<option value="{{ $a->id }}" @selected($filters['account'] === $a->id)>{{ $a->name }}</option>@endforeach</select></div>
    <div class="a-field"><label class="form-label" for="is">Status</label><select class="form-select" id="is" name="status"><option value="">All</option>@foreach(['paid' => 'Paid', 'pending' => 'Pending', 'failed' => 'Failed', 'refunded' => 'Refunded', 'partially_refunded' => 'Partially refunded', 'voided' => 'Voided', 'unknown' => 'Unknown'] as $k => $l)<option value="{{ $k }}" @selected($filters['status'] === $k)>{{ $l }}</option>@endforeach</select></div>
    <div class="a-field"><label class="form-label" for="im">Payment method</label><input class="form-control" id="im" name="pm" value="{{ $filters['method'] }}" placeholder="card, wallet…"></div>
    <div class="a-field"><label class="form-label" for="it">Transaction ID</label><input class="form-control" id="it" name="txn" value="{{ $filters['txn'] }}"></div>
    <div class="a-field"><label class="form-label" for="ir">Reference / order ref</label><input class="form-control" id="ir" name="ref" value="{{ $filters['ref'] }}"></div>
    <div class="a-field"><label class="form-label" for="il">Link</label><select class="form-select" id="il" name="link"><option value="">All</option><option value="linked" @selected($filters['link'] === 'linked')>Linked to a local payment</option><option value="unlinked" @selected($filters['link'] === 'unlinked')>Not linked (no local order)</option></select></div>
    <div class="a-field"><label class="form-label" for="if">From</label><input type="date" class="form-control" id="if" name="from" value="{{ request('from') }}"></div>
    <div class="a-field"><label class="form-label" for="ito">To</label><input type="date" class="form-control" id="ito" name="to" value="{{ request('to') }}"></div>
</x-admin.filters>

<x-admin.card flush>
    @if($rows->isEmpty())
        <x-admin.empty icon="fa-file-import" title="No imported transactions" text="Import one by id or upload a dashboard export. TKT House cannot list all provider transactions by itself because the providers offer no API for it." />
    @else
    <x-admin.table stack compact caption="Imported provider transactions">
        <thead><tr><th>Provider transaction</th><th>Merchant account</th><th class="a-num">Amount</th><th>Status</th><th>Local order</th><th>Source</th><th>Created at provider</th></tr></thead>
        <tbody>
        @foreach($rows as $t)
            <tr>
                <td data-label="Provider transaction"><a class="a-table__main" href="{{ route('admin.payments.imported.show', $t) }}">{{ $t->provider_transaction_id }}</a>@if($t->provider_reference)<span class="a-table__sub">Ref {{ $t->provider_reference }}</span>@endif @if($canSeeCustomer && $t->customer_label)<span class="a-table__sub">{{ $t->customer_label }}</span>@endif</td>
                <td data-label="Merchant account">{{ $t->account?->name }}<span class="a-table__sub">{{ ucfirst($t->provider) }} · {{ $t->payment_method ?: 'method n/a' }}</span></td>
                <td data-label="Amount" class="a-num">{{ $money($t->amount_minor, $t->currency) }}@if($t->fee_minor !== null)<span class="a-table__sub">fee {{ $money($t->fee_minor, $t->currency) }}</span>@endif</td>
                <td data-label="Status"><x-admin.badge :variant="match($t->status){'paid'=>'success','pending'=>'warning','failed'=>'danger','refunded','partially_refunded','voided'=>'info',default=>'default'}" dot>{{ ucfirst(str_replace('_', ' ', $t->status)) }}</x-admin.badge></td>
                <td data-label="Local order">@if($t->attempt?->order)<a href="{{ route('admin.orders.show', $t->attempt->order) }}">#{{ $t->attempt->order->order_number }}</a>@else<span class="a-table__sub">None</span>@endif</td>
                <td data-label="Source">{{ $t->source === 'api_lookup' ? 'Provider API' : 'CSV' }}<span class="a-table__sub">{{ $t->verified_at ? 'Verified '.$t->verified_at->diffForHumans() : 'Unverified' }}</span></td>
                <td data-label="Created at provider">{{ $t->provider_created_at?->format('d M Y, H:i') ?? '—' }}</td>
            </tr>
        @endforeach
        </tbody>
    </x-admin.table>
    <x-admin.pager :paginator="$rows" />
    @endif
</x-admin.card>
@endsection
