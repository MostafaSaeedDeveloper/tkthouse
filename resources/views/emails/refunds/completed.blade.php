@extends('emails.layouts.base', [
  'title' => __('refund_mail.completed.subject'),
  'heroIcon' => '✔️',
  'heroTitle' => __('refund_mail.completed.hero_title'),
  'heroText' => e(__('refund_mail.completed.hero_text')),
  'footerText' => __('refund_mail.completed.footer'),
])

@section('content')
  <p class="ep">{{ __('refund_mail.labels.greeting') }} <strong>{{ $d->name ?: __('refund_mail.labels.there') }}</strong>,</p>

  <p class="ep">{!! __('refund_mail.completed.intro', ['order' => e($d->order_number)]) !!}</p>

  @include('emails.refunds._rows', ['d' => $d, 'statusText' => __('refund_mail.labels.st_completed'), 'statusColor' => '#41d394', 'withProof' => true])

  <div class="ealert green">{{ __('refund_mail.completed.returned') }} {{ $d->timing_note }}</div>

  @if($d->tickets_outcome === 'full')
    <p class="ep">{{ __('refund_mail.completed.tickets_full') }}</p>
  @elseif($d->tickets_outcome === 'partial')
    <p class="ep">{{ __('refund_mail.completed.tickets_partial', ['count' => $d->tickets_canceled]) }}</p>
  @elseif($d->tickets_outcome === 'none')
    <p class="ep">{{ __('refund_mail.completed.tickets_none') }}</p>
  @endif

  <p class="ep ep-sm">{!! __('refund_mail.contact', ['order' => e($d->order_number)]) !!}</p>
@endsection
