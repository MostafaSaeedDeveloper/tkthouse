@extends('emails.layouts.base', [
  'title' => __('refund_mail.received.subject'),
  'heroIcon' => '📝',
  'heroTitle' => __('refund_mail.received.hero_title'),
  'heroText' => e(__('refund_mail.received.hero_text')),
  'footerText' => __('refund_mail.received.footer'),
])

@section('content')
  <p class="ep">{{ __('refund_mail.labels.greeting') }} <strong>{{ $d->name ?: __('refund_mail.labels.there') }}</strong>,</p>

  <p class="ep">{!! __('refund_mail.received.intro', ['order' => e($d->order_number)]) !!}</p>

  @include('emails.refunds._rows', ['d' => $d, 'statusText' => __('refund_mail.labels.st_request'), 'statusColor' => '#f5b800'])

  <div class="ealert gold">{{ __('refund_mail.received.notice') }}</div>

  <p class="ep ep-sm">{!! __('refund_mail.contact', ['order' => e($d->order_number)]) !!}</p>
@endsection
