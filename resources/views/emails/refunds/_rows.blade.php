<div class="einfo">
  <div class="einfo-row"><span class="einfo-label">{{ __('refund_mail.labels.order') }}</span><span class="einfo-val gold">#{{ $d->order_number }}</span></div>
  @if($d->events)<div class="einfo-row"><span class="einfo-label">{{ __('refund_mail.labels.event') }}</span><span class="einfo-val">{{ $d->events }}</span></div>@endif
  <div class="einfo-row"><span class="einfo-label">{{ __('refund_mail.labels.type') }}</span><span class="einfo-val">{{ $d->is_full ? __('refund_mail.labels.full') : __('refund_mail.labels.partial') }}</span></div>
  <div class="einfo-row"><span class="einfo-label">{{ __('refund_mail.labels.amount') }}</span><span class="einfo-val gold">{{ $d->amount }}</span></div>
  @if($d->tickets_included)<div class="einfo-row"><span class="einfo-label">{{ __('refund_mail.labels.tickets') }}</span><span class="einfo-val">{{ $d->tickets_included }}</span></div>@endif
  <div class="einfo-row"><span class="einfo-label">{{ __('refund_mail.labels.reason') }}</span><span class="einfo-val">{{ $d->reason }}</span></div>
  <div class="einfo-row"><span class="einfo-label">{{ __('refund_mail.labels.status') }}</span><span class="einfo-val" style="color:{{ $statusColor }};font-weight:700;">{{ $statusText }}</span></div>
  @isset($withProof)
    <div class="einfo-row"><span class="einfo-label">{{ __('refund_mail.labels.reference') }}</span><span class="einfo-val">{{ $d->reference }}</span></div>
    @if($d->confirmed_at)<div class="einfo-row"><span class="einfo-label">{{ __('refund_mail.labels.date') }}</span><span class="einfo-val">{{ $d->confirmed_at }}</span></div>@endif
  @endisset
</div>
