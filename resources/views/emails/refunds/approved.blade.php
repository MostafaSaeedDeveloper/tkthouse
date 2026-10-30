@extends('emails.layouts.base', [
  'title' => __('refund_mail.approved.subject'),
  'heroIcon' => '✅',
  'heroTitle' => __('refund_mail.approved.hero_title'),
  'heroText' => e(__('refund_mail.approved.hero_text')),
  'footerText' => __('refund_mail.approved.footer'),
])

@section('content')
  <p class="ep">{{ __('refund_mail.labels.greeting') }} <strong>{{ $d->name ?: __('refund_mail.labels.there') }}</strong>,</p>

  <p class="ep">{!! __('refund_mail.approved.intro', ['order' => e($d->order_number)]) !!}</p>

  @include('emails.refunds._rows', ['d' => $d, 'statusText' => __('refund_mail.labels.st_processing'), 'statusColor' => '#f5b800'])

  <div class="ealert gold">{{ __('refund_mail.approved.notice') }}</div>

  <p class="ep">{{ __('refund_mail.approved.tickets') }}</p>

  <p class="ep ep-sm">{!! __('refund_mail.contact', ['order' => e($d->order_number)]) !!}</p>
@endsection
