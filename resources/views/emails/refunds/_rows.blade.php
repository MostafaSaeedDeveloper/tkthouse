@include('emails.partials.info-table', ['rows' => [
  ['label' => __('refund_mail.labels.order'), 'value' => '#'.$d->order_number, 'tone' => 'gold'],
  $d->events ? ['label' => __('refund_mail.labels.event'), 'value' => $d->events] : null,
  ['label' => __('refund_mail.labels.type'), 'value' => $d->is_full ? __('refund_mail.labels.full') : __('refund_mail.labels.partial')],
  ['label' => __('refund_mail.labels.amount'), 'value' => $d->amount, 'tone' => 'gold'],
  $d->tickets_included ? ['label' => __('refund_mail.labels.tickets'), 'value' => $d->tickets_included] : null,
  ['label' => __('refund_mail.labels.reason'), 'value' => $d->reason],
  ['label' => __('refund_mail.labels.status'), 'value' => $statusText, 'color' => $statusColor],
  isset($withProof) ? ['label' => __('refund_mail.labels.reference'), 'value' => $d->reference] : null,
  isset($withProof) && $d->confirmed_at ? ['label' => __('refund_mail.labels.date'), 'value' => $d->confirmed_at] : null,
]])
