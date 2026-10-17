@extends('admin.master')
@section('title', 'Provider transaction '.$tx->provider_transaction_id)
@php
    $money = fn ($m) => $m === null ? '—' : number_format($m / 100, 2).' '.($tx->currency ?: 'EGP');
    $copy = fn ($v) => $v ? '<button type="button" class="a-btn a-btn--ghost a-btn--sm" data-copy-text="'.e($v).'" aria-label="Copy"><i class="fa fa-copy"></i></button>' : '';
@endphp
@section('content')
<x-admin.page-header :title="'Transaction '.$tx->provider_transaction_id" :subtitle="ucfirst($tx->provider).' · '.($tx->account?->name ?? '')" :crumbs="['Dashboard' => route('admin.dashboard'), 'Payments', 'Transactions', 'Imported' => route('admin.payments.imported'), $tx->provider_transaction_id]">
    @if($canInquire)<form method="POST" action="{{ route('admin.payments.imported.verify', $tx) }}" data-a-loading>@csrf<button class="a-btn a-btn--primary" type="submit"><i class="fa fa-rotate"></i> Check with provider</button></form>@endif
    <a class="a-btn a-btn--outline" href="{{ route('admin.payments.imported') }}">Back</a>
</x-admin.page-header>
<x-admin.flash />
@include('admin.payments._nav')
@if(! $tx->isLinked())
    <x-admin.alert type="info"><strong>Not linked to any TKT House order.</strong> This payment exists at the provider only (or was made outside this system). No order was created and none will be changed by importing it.</x-admin.alert>
@endif
@if(data_get($tx->snapshot, 'link_ambiguous'))<x-admin.alert type="warning">More than one local payment matches this provider record, so it was <strong>not</strong> linked. Review it manually.</x-admin.alert>@endif
@if(data_get($tx->snapshot, 'amount_mismatch'))<x-admin.alert type="warning">The provider amount ({{ $money($tx->amount_minor) }}) differs from the linked local payment ({{ number_format(data_get($tx->snapshot, 'amount_mismatch') / 100, 2) }}). Nothing was changed; check it with the provider.</x-admin.alert>@endif
@if(! $tx->verified_at)<x-admin.alert type="warning"><strong>Unverified.</strong> This row came from a CSV file. Use <em>Check with provider</em> to confirm it with the official API.</x-admin.alert>@endif
<div class="a-grid a-grid--main-side">
  <div>
    <x-admin.card title="Transaction overview">
        <dl class="a-kv">
            <div><dt>Status</dt><dd><x-admin.badge :variant="match($tx->status){'paid'=>'success','pending'=>'warning','failed'=>'danger','refunded','partially_refunded','voided'=>'info',default=>'default'}" dot>{{ ucfirst(str_replace('_', ' ', $tx->status)) }}</x-admin.badge>@if($tx->provider_status)<span class="a-table__sub">provider says "{{ $tx->provider_status }}"</span>@endif</dd></div>
            <div><dt>Amount</dt><dd>{{ $money($tx->amount_minor) }}</dd></div>
            @if($tx->refunded_amount_minor)<div><dt>Refunded at provider</dt><dd>{{ $money($tx->refunded_amount_minor) }}</dd></div>@endif
            @if($tx->fee_minor !== null)<div><dt>Provider-reported commission</dt><dd>{{ $money($tx->fee_minor) }}</dd></div>@endif
            <div><dt>Payment method</dt><dd>{{ $tx->payment_method ?: '—' }}</dd></div>
            <div><dt>Created at provider</dt><dd>{{ $tx->provider_created_at?->format('d M Y, H:i') ?? '—' }}</dd></div>
            <div><dt>Paid at</dt><dd>{{ $tx->paid_at?->format('d M Y, H:i') ?? '—' }}</dd></div>
            @if($canSeeCustomer && $tx->customer_label)<div><dt>Customer</dt><dd>{{ $tx->customer_label }}</dd></div>@endif
        </dl>
    </x-admin.card>
    <x-admin.card title="Actions" style="margin-top:12px">
        <p class="a-table__sub" style="margin:0 0 6px"><strong>Refund / void:</strong> not available here because this transaction has no TKT House order. Refunds and voids use the existing refund workflow of a local payment (same merchant account, permissions and approval). For provider-only payments use the provider's dashboard.</p>
        @if($tx->isLinked())<p style="margin:8px 0 0"><a class="a-btn a-btn--outline" href="{{ route('admin.payments.transactions.show', $tx->attempt) }}">Open the linked local payment</a>@if($tx->attempt?->order) <a class="a-btn a-btn--outline" href="{{ route('admin.orders.show', $tx->attempt->order) }}">View related order</a>@endif</p>@endif
    </x-admin.card>
  </div>
  <div>
    <x-admin.card title="Provider information">
        <dl class="a-kv">
            <div><dt>Provider</dt><dd>{{ ucfirst($tx->provider) }}</dd></div>
            <div><dt>Merchant account</dt><dd>{{ $tx->account?->name }}</dd></div>
            <div><dt>Provider transaction ID</dt><dd>{{ $tx->provider_transaction_id }} {!! $copy($tx->provider_transaction_id) !!}</dd></div>
            @if($tx->provider_order_id)<div><dt>Provider order ID</dt><dd>{{ $tx->provider_order_id }} {!! $copy($tx->provider_order_id) !!}</dd></div>@endif
            @if($tx->provider_reference)<div><dt>Provider reference</dt><dd>{{ $tx->provider_reference }} {!! $copy($tx->provider_reference) !!}</dd></div>@endif
            <div><dt>Source</dt><dd>{{ $tx->source === 'api_lookup' ? 'Provider API lookup' : 'CSV import' }}</dd></div>
            <div><dt>Verified</dt><dd>{{ $tx->verified_at ? $tx->verified_at->format('d M Y, H:i') : 'No' }}</dd></div>
            <div><dt>Last checked</dt><dd>{{ $tx->last_checked_at?->diffForHumans() ?? 'never' }}</dd></div>
        </dl>
    </x-admin.card>
  </div>
</div>
@endsection
@push('scripts')<script>document.querySelectorAll('[data-copy-text]').forEach(function (b) { b.addEventListener('click', function () { navigator.clipboard.writeText(b.dataset.copyText); b.innerHTML = '<i class="fa fa-check"></i>'; setTimeout(function () { b.innerHTML = '<i class="fa fa-copy"></i>'; }, 1200); }); });</script>@endpush
