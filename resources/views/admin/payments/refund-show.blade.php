@extends('admin.master')
@section('title', 'Refund '.$refund->internal_reference)
@section('content')
<x-admin.page-header :title="'Refund '.$refund->internal_reference" :subtitle="'Order #'.$refund->order->order_number" :crumbs="['Dashboard' => route('admin.dashboard'), 'Payments', 'Refunds' => route('admin.payments.refunds'), $refund->internal_reference]">
    <a class="a-btn a-btn--outline" href="{{ route('admin.orders.show', $refund->order) }}">Open order</a>
    @if($refund->mode === 'api' && in_array($refund->status, ['processing', 'unknown'], true) && ($canCreate || $canApprove))
        <form method="POST" action="{{ route('admin.payments.refunds.check', $refund) }}" data-a-loading>@csrf<button class="a-btn a-btn--primary" type="submit"><i class="fa fa-rotate"></i> Check refund status</button></form>
    @endif
</x-admin.page-header>
<x-admin.flash />
@error('refund')<x-admin.alert type="danger">{{ $message }}</x-admin.alert>@enderror
@if($refund->status === 'unknown')<x-admin.alert type="warning"><strong>Result unknown.</strong> The request may or may not have reached the provider. Do not repeat it. Check status, or verify in the provider dashboard and confirm below.</x-admin.alert>@endif
@if(data_get($refund->meta, 'provider_reported_approval'))<x-admin.alert type="info">Fawaterak sent an (unauthenticated) refund notification saying "approved". That is an approval, not proof the money was returned: confirm below once you have verified it.</x-admin.alert>@endif
<div class="a-grid a-grid--main-side">
  <div>
    <x-admin.card title="Timeline" subtitle="Every state change is recorded and audited.">
        <ul class="a-timeline">
            @foreach(array_reverse($refund->timeline()) as $t)
                <li><strong>{{ str_replace('_', ' ', $t['event']) }}</strong> <span class="a-table__sub">{{ \Illuminate\Support\Carbon::parse($t['at'])->format('d M Y, H:i:s') }} · {{ $t['source'] ?? 'system' }}@isset($t['by']) · {{ $t['by'] }}@endisset</span>
                    @isset($t['note'])<br><span>{{ $t['note'] }}</span>@endisset</li>
            @endforeach
        </ul>
    </x-admin.card>

    @if(! $refund->isFinal() && ($canCreate || $canApprove))
    <x-admin.card title="Actions">
        <div class="a-btn-group" style="flex-wrap:wrap;gap:12px;align-items:flex-start">
            @if($refund->awaiting_approval && $canApprove)
                <form method="POST" action="{{ route('admin.payments.refunds.approve', $refund) }}" data-a-confirm="Approve this refund{{ $refund->mode === 'api' ? ' and send it to the provider' : '' }}?" data-a-confirm-ok="Approve">@csrf<button class="a-btn a-btn--primary" type="submit">Approve</button></form>
                <form method="POST" action="{{ route('admin.payments.refunds.reject', $refund) }}" class="a-btn-group">@csrf<input class="form-control" name="note" placeholder="Reason for rejecting" minlength="5" required aria-label="Rejection reason"><button class="a-btn a-btn--outline" type="submit">Reject request</button></form>
            @elseif(! $refund->awaiting_approval)
                @if($refund->mode === 'manual' && $refund->status === 'requested')
                    <form method="POST" action="{{ route('admin.payments.refunds.submitted', $refund) }}">@csrf<button class="a-btn a-btn--outline" type="submit">Mark as submitted to the provider</button></form>
                @endif
                <form method="POST" action="{{ route('admin.payments.refunds.confirm', $refund) }}" data-a-confirm="Record this refund as COMPLETED? Only do this after verifying it with the provider." data-a-confirm-ok="Record completed" style="display:grid;gap:6px">@csrf
                    <strong>Confirm completed (verified with the provider)</strong>
                    <input class="form-control" name="provider_reference" placeholder="Provider refund reference" required maxlength="120" aria-label="Provider refund reference">
                    <input class="form-control" name="note" placeholder="Evidence (e.g. portal screenshot id)" minlength="5" required aria-label="Evidence">
                    <button class="a-btn a-btn--success" type="submit">Record completed</button></form>
                <form method="POST" action="{{ route('admin.payments.refunds.fail', $refund) }}" data-a-confirm="Record this refund as FAILED? The amount becomes available again." data-a-confirm-ok="Record failed" style="display:grid;gap:6px">@csrf
                    <strong>Confirm it did not happen</strong>
                    <input class="form-control" name="note" placeholder="Evidence" minlength="5" required aria-label="Evidence">
                    <button class="a-btn a-btn--danger" type="submit">Record failed</button></form>
            @endif
        </div>
    </x-admin.card>
    @endif
  </div>
  <div>
    <x-admin.card title="Refund">
        <dl class="a-kv">
            <div><dt>Status</dt><dd>@include('admin.payments._refund-status', ['refund' => $refund])</dd></div>
            <div><dt>Amount</dt><dd>{{ $refund->amount() }} {{ $refund->currency }} ({{ $refund->type }})</dd></div>
            <div><dt>Mode</dt><dd>{{ $refund->mode === 'api' ? 'Provider API' : 'Manual / external' }}</dd></div>
            <div><dt>Provider</dt><dd>{{ ucfirst($refund->provider) }}</dd></div>
            @if($seeAccounts)<div><dt>Merchant account</dt><dd>{{ $refund->account?->name ?? 'Legacy configuration' }}</dd></div>@endif
            <div><dt>Payment</dt><dd><a href="{{ route('admin.payments.transactions.show', $refund->attempt) }}">{{ $refund->attempt->internal_reference ?: '#'.$refund->payment_attempt_id }}</a></dd></div>
            <div><dt>Provider refund ID</dt><dd>{{ $refund->provider_refund_id ?: '—' }}</dd></div>
            @if($refund->provider_reference)<div><dt>Provider reference</dt><dd>{{ $refund->provider_reference }}</dd></div>@endif
            <div><dt>Reason</dt><dd>{{ $refund->reason }}</dd></div>
            <div><dt>Requested by</dt><dd>{{ $refund->requester?->name ?? '—' }} · {{ $refund->requested_at->format('d M Y, H:i') }}</dd></div>
            <div><dt>Completed</dt><dd>{{ $refund->completed_at?->format('d M Y, H:i') ?? '—' }}</dd></div>
            <div><dt>Last checked</dt><dd>{{ $refund->last_checked_at?->diffForHumans() ?? 'never' }}</dd></div>
            @if($refund->failure_reason)<div><dt>Note</dt><dd>{{ $refund->failure_reason }}</dd></div>@endif
        </dl>
        <p class="a-hint">A refund never cancels the order or removes tickets. Handle those separately under your business policy.</p>
    </x-admin.card>
  </div>
</div>
@endsection
