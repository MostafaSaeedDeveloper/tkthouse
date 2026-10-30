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

    @if($refund->mode === 'manual' && ($refund->provider === 'fawaterak' || str_starts_with((string) $refund->order->payment_method, 'fawaterak')))
        @include('admin.payments._fawaterak-tracking', ['tracking' => $tracking])
    @endif

    <x-admin.card title="Tickets" subtitle="Tickets are canceled only when this refund is confirmed as completed.">
        @php $links = $refund->ticketLinks; @endphp
        @if($links->isEmpty())
            <p class="a-hint">{{ $refund->effects_status === 'not_applicable' ? 'Money-only refund: no tickets are affected.' : 'No tickets are linked to this refund.' }}</p>
        @else
            <ul class="a-timeline">@foreach($links as $l)<li><strong>#{{ $l->ticket?->ticket_number }}</strong> <span class="a-table__sub">{{ $l->canceled_at ? 'Canceled '.$l->canceled_at->format('d M Y, H:i').($l->was_checked_in ? ' · was checked in (check-in record kept, flagged for audit)' : '') : 'Selected · still valid until the refund is confirmed' }}</span></li>@endforeach</ul>
        @endif
        @if($refund->status === 'succeeded' && in_array($refund->effects_status, ['needs_review', null], true))
            <x-admin.alert type="warning">{{ $refund->effects_note ?: 'This refund was confirmed but its tickets were not matched automatically.' }}</x-admin.alert>
            @if($canApprove && $ticketChoices->isNotEmpty())
            <form method="POST" action="{{ route('admin.payments.refunds.tickets', $refund) }}" data-a-confirm="Cancel the selected tickets? This cannot be undone." data-a-confirm-ok="Cancel tickets" style="display:grid;gap:6px">@csrf
                @foreach($ticketChoices as $c)<label class="a-m-switch"><input class="form-check-input" type="checkbox" name="tickets[]" value="{{ $c->ticket->id }}" @disabled(! $c->selectable && $c->ticket->status !== 'canceled')><span>#{{ $c->ticket->ticket_number }} · {{ $c->ticket->holder_name ?: $c->ticket->name }}<span class="a-hint d-block">{{ $c->note ?: ($c->ticket->status === 'checked_in' ? 'Checked in' : 'Valid') }}</span></span></label>@endforeach
                <button class="a-btn a-btn--danger" type="submit">Cancel selected tickets</button>
            </form>
            @endif
        @endif
    </x-admin.card>

    @if(! $refund->isFinal() && ($canCreate || $canApprove))
    <x-admin.card title="Actions">
        <div class="a-btn-group" style="flex-wrap:wrap;gap:12px;align-items:flex-start">
            @if($refund->awaiting_approval && $canApprove && (int) $refund->requested_by !== (int) auth()->id())
                <form method="POST" action="{{ route('admin.payments.refunds.approve', $refund) }}" data-a-confirm="Approve this refund{{ $refund->mode === 'api' ? ' and send it to the provider' : '' }}?" data-a-confirm-ok="Approve" style="display:grid;gap:6px">@csrf
                    <input class="form-control" name="note" placeholder="Approval note (optional)" maxlength="300" aria-label="Approval note">
                    <button class="a-btn a-btn--primary" type="submit">Approve</button></form>
                <form method="POST" action="{{ route('admin.payments.refunds.reject', $refund) }}" class="a-btn-group">@csrf<input class="form-control" name="note" placeholder="Reason for rejecting" minlength="5" required aria-label="Rejection reason"><button class="a-btn a-btn--outline" type="submit">Reject request</button></form>
            @elseif($refund->awaiting_approval && ! $approvalRequired && $refund->mode === 'manual' && ($canApprove || ($canCreate && (int) $refund->requested_by === (int) auth()->id())))
                <x-admin.alert type="warning">This request was created while a second approval was required. That requirement is now off, but nothing was approved automatically. Release it explicitly to move it to "processing externally" (it still needs a verified confirmation to complete).</x-admin.alert>
                <form method="POST" action="{{ route('admin.payments.refunds.release', $refund) }}" data-a-confirm="Release this request without a second approval?" data-a-confirm-ok="Release" style="display:grid;gap:6px">@csrf
                    <input class="form-control" name="note" placeholder="Why is it released without a second approval?" minlength="5" maxlength="300" required aria-label="Release note">
                    <button class="a-btn a-btn--primary" type="submit">Release for external processing</button></form>
                <form method="POST" action="{{ route('admin.payments.refunds.reject', $refund) }}" class="a-btn-group">@csrf<input class="form-control" name="note" placeholder="Reason for withdrawing / rejecting" minlength="5" required aria-label="Rejection reason"><button class="a-btn a-btn--outline" type="submit">Withdraw request</button></form>
            @elseif($refund->awaiting_approval)
                <x-admin.alert type="info">Waiting for approval by a <strong>different</strong> person with the refund-approval permission. {{ (int) $refund->requested_by === (int) auth()->id() ? 'You requested this refund, so you cannot approve it.' : '' }} Nothing is sent to the provider until then.</x-admin.alert>
                @if($canApprove || $canCreate)
                <form method="POST" action="{{ route('admin.payments.refunds.reject', $refund) }}" class="a-btn-group">@csrf<input class="form-control" name="note" placeholder="Reason for withdrawing / rejecting" minlength="5" required aria-label="Rejection reason"><button class="a-btn a-btn--outline" type="submit">Withdraw request</button></form>
                @endif
            @elseif(! $refund->awaiting_approval)
                @if($refund->mode === 'manual' && ($refund->status === 'requested' || ($refund->status === 'processing' && ! $refund->submitted_by)))
                    <form method="POST" action="{{ route('admin.payments.refunds.submitted', $refund) }}">@csrf<button class="a-btn a-btn--outline" type="submit">I carried it out in the provider dashboard (record who)</button></form>
                @endif
                <form method="POST" action="{{ route('admin.payments.refunds.confirm', $refund) }}" data-a-confirm="Record this refund as COMPLETED? Its tickets will be canceled and the order updated. Only do this after verifying it with the provider." data-a-confirm-ok="Record completed" style="display:grid;gap:6px">@csrf
                    <strong>Confirm completed (verified with the provider)</strong>
                    <input class="form-control" name="provider_reference" placeholder="Provider / external refund reference" required maxlength="120" aria-label="Provider refund reference" value="{{ old('provider_reference', $refund->provider_reference) }}">
                    <label class="a-hint" for="rfdate">Actual refund date</label>
                    <input class="form-control" id="rfdate" type="date" name="refunded_at" required max="{{ now()->toDateString() }}" value="{{ old('refunded_at', $refund->refunded_at?->toDateString()) }}" aria-label="Actual refund date">
                    @if($refund->mode === 'manual')<select class="form-select" name="external_method" aria-label="Refund method">@foreach(\App\Models\PaymentRefund::EXTERNAL_METHODS as $k => $label)<option value="{{ $k }}" @selected(old('external_method', $refund->external_method) === $k)>{{ $label }}</option>@endforeach</select>@endif
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
            <div><dt>Payment</dt><dd>@if($refund->attempt)<a href="{{ route('admin.payments.transactions.show', $refund->attempt) }}">{{ $refund->attempt->internal_reference ?: '#'.$refund->payment_attempt_id }}</a>@else<span class="a-muted">Legacy payment — no gateway transaction</span>@endif</dd></div>
            <div><dt>Provider refund ID</dt><dd>{{ $refund->provider_refund_id ?: '—' }}</dd></div>
            @if($refund->provider_reference)<div><dt>Provider / external reference</dt><dd>{{ $refund->provider_reference }}</dd></div>@endif
            @if($refund->mode === 'manual')<div><dt>Refund method</dt><dd>{{ \App\Models\PaymentRefund::EXTERNAL_METHODS[$refund->external_method] ?? 'Manual / external' }}</dd></div>@endif
            <div><dt>Original paid</dt><dd>{{ \App\Models\PaymentRefund::minorToDecimal((int) ($refund->captured_minor ?? 0)) }} {{ $refund->currency }}</dd></div>
            <div><dt>Payment method</dt><dd>{{ $refund->payment_method ?: '—' }}</dd></div>
            <div><dt>Reason</dt><dd>{{ $refund->reason }}</dd></div>
            @if($refund->internal_notes)<div><dt>Internal notes</dt><dd>{{ $refund->internal_notes }}</dd></div>@endif
            @if($refund->approved_by)<div><dt>Approved by</dt><dd>{{ \App\Models\User::find($refund->approved_by)?->name ?? '—' }} · {{ $refund->approved_at?->format('d M Y, H:i') }}</dd></div>@endif
            <div><dt>Requested by</dt><dd>{{ $refund->requester?->name ?? '—' }} · {{ $refund->requested_at->format('d M Y, H:i') }}</dd></div>
            @if($refund->submitted_by)<div><dt>Submitted by</dt><dd>{{ $refund->submitter?->name ?? '—' }} · {{ $refund->submitted_at?->format('d M Y, H:i') }}</dd></div>@endif
            @if($refund->confirmed_by)<div><dt>Confirmed by</dt><dd>{{ $refund->confirmer?->name ?? '—' }} · {{ $refund->confirmed_at?->format('d M Y, H:i') }}</dd></div>@endif
            <div><dt>Actual refund date</dt><dd>{{ $refund->refunded_at?->format('d M Y') ?? '—' }}</dd></div>
            <div><dt>Completed</dt><dd>{{ $refund->completed_at?->format('d M Y, H:i') ?? '—' }}</dd></div>
            <div><dt>Last checked</dt><dd>{{ $refund->last_checked_at?->diffForHumans() ?? 'never' }}</dd></div>
            @if($refund->failure_reason)<div><dt>Note</dt><dd>{{ $refund->failure_reason }}</dd></div>@endif
        </dl>
        @php $mails = (array) data_get($refund->meta, 'customer_emails', []); @endphp
        @if($mails)
        <dl class="a-kv"><div><dt>Customer e-mails</dt><dd>@foreach(['completed' => 'Refund completed'] as $k => $label)@if(isset($mails[$k]))<div>{{ $label }}: {{ ! empty($mails[$k]['sent_at']) ? 'sent '.\Illuminate\Support\Carbon::parse($mails[$k]['sent_at'])->format('d M H:i') : (! empty($mails[$k]['skipped']) ? 'not sent (no valid e-mail)' : (! empty($mails[$k]['failed_at']) ? 'FAILED (refund unaffected)' : 'sending…')) }}
            @if(! empty($mails[$k]['failed_at']) && ($canCreate || $canApprove))<form method="POST" action="{{ route('admin.payments.refunds.resend-email', $refund) }}" style="display:inline">@csrf<input type="hidden" name="kind" value="{{ $k }}"><button class="a-btn a-btn--outline a-btn--sm" type="submit">Resend</button></form>@endif</div>@endif @endforeach</dd></div></dl>
        @endif
        <p class="a-hint">When this refund is confirmed as completed, the tickets it covers are canceled (never deleted; scan history is kept) and the order becomes refunded or partially refunded. Until then nothing changes.</p>
    </x-admin.card>
  </div>
</div>
@endsection
