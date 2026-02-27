@extends('admin.master')

@section('content')
@php
  $displayOrderNumber = preg_replace('/\D+/', '', (string) $order->order_number) ?: $order->order_number;
  $statusClass = str_replace('approved_pending_payment', 'approved', (string) $order->status);
  $statusLabel = ucwords(str_replace('_', ' ', (string) $order->status));
  $customerName = $order->customer?->full_name ?: 'N/A';
  $customerInitials = collect(explode(' ', $customerName))->filter()->map(fn($p)=>mb_substr($p,0,1))->take(2)->implode('');
  $paymentLink = $order->payment_link_token ? route('front.orders.payment', ['order' => $order, 'token' => $order->payment_link_token]) : null;
@endphp

<style>
.od-wrap { padding: 8px 0 60px; }
.od-head { display: flex; align-items: flex-start; justify-content: space-between; gap: 16px; flex-wrap: wrap; margin-bottom: 28px; }
.od-head-left p  { font-size: 13px; color: #5e5e72; margin: 4px 0 0; }
.od-head-left h1 { font-family: 'Syne', sans-serif; font-size: 24px; font-weight: 800; color: #fff; margin: 0; letter-spacing: -0.3px; }
.od-head-left h1 span { color: #f5b800; }
.od-grid { display: grid; grid-template-columns: 1fr 340px; gap: 20px; align-items: start; }
@media (max-width: 900px) { .od-grid { grid-template-columns: 1fr; } }
.od-card { background: #0d0d10; border: 1px solid rgba(255,255,255,0.07); border-radius: 12px; overflow: hidden; margin-bottom: 20px; }
.od-card-head { display: flex; align-items: center; justify-content: space-between; padding: 15px 20px; border-bottom: 1px solid rgba(255,255,255,0.07); gap: 10px; }
.od-card-title { font-family: 'Syne', sans-serif; font-size: 11px; font-weight: 700; letter-spacing: 2px; text-transform: uppercase; color: #f5b800; display: flex; align-items: center; gap: 8px; }
.od-card-title::before { content: ''; width: 3px; height: 13px; background: #f5b800; border-radius: 2px; flex-shrink: 0; }
.od-card-body { padding: 20px; }
.od-info-row { display: flex; align-items: center; justify-content: space-between; padding: 11px 0; border-bottom: 1px solid rgba(255,255,255,0.05); font-size: 13.5px; gap: 12px; }
.od-info-row:last-child { border-bottom: none; }
.od-info-label { color: #5e5e72; font-size: 12px; text-transform: uppercase; letter-spacing: 0.6px; }
.od-info-val   { color: #dddde8; font-weight: 500; text-align: right; }
.od-status { display: inline-flex; align-items: center; gap: 6px; font-family: 'Syne', sans-serif; font-size: 11px; font-weight: 700; letter-spacing: 0.5px; padding: 4px 11px; border-radius: 99px; }
.od-status.pending,.od-status.pending_payment,.od-status.pending_approval { color: #f5b800; background: rgba(245,184,0,0.12); border: 1px solid rgba(245,184,0,0.25); }
.od-status.approved,.od-status.paid { color: #22c55e; background: rgba(34,197,94,0.10); border: 1px solid rgba(34,197,94,0.25); }
.od-status.rejected { color: #e8445a; background: rgba(232,68,90,0.10); border: 1px solid rgba(232,68,90,0.25); }
.od-customer,.od-ticket-holder { display: flex; align-items: center; gap: 10px; }
.od-avatar,.od-ticket-holder-avatar,.od-note-avatar,.od-hist-icon { width: 34px; height: 34px; border-radius: 50%; background: rgba(245,184,0,0.12); border: 1px solid rgba(245,184,0,0.25); color: #f5b800; display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 11px; }
.od-btn-approve,.od-btn-back { display:inline-flex;align-items:center;gap:8px;border-radius:8px;padding:9px 16px;text-decoration:none;border:0; }
.od-btn-approve { background:#f5b800;color:#000; }
.od-btn-back { background:#15151b;color:#5e5e72;border:1px solid rgba(255,255,255,0.07); }
.od-ticket { background:#15151b;border:1px solid rgba(255,255,255,0.07);border-radius:10px;padding:16px;margin-bottom:12px;display:grid;grid-template-columns:1fr auto;gap:12px; }
.od-ticket-name{color:#fff;font-weight:700}.od-ticket-holder-info{color:#dddde8;font-size:12px}.od-ticket-holder-info span{display:block;color:#5e5e72}
.od-ticket-price{color:#f5b800;font-weight:700;text-align:right}.od-ticket-qty{font-size:11px;color:#5e5e72;text-align:right}
.od-total-bar { display:flex;justify-content:space-between;background:rgba(245,184,0,0.06);border:1px solid rgba(245,184,0,0.2);border-radius:10px;padding:14px 20px;margin-top:16px;color:#fff; }
.od-note-item,.od-hist-item{display:flex;gap:10px;padding:10px 0;border-bottom:1px solid rgba(255,255,255,0.05)}
.od-note-item:last-child,.od-hist-item:last-child{border-bottom:0}
.od-note-author,.od-hist-action{color:#dddde8;font-size:12px}.od-note-author span,.od-hist-meta{display:block;color:#5e5e72;font-size:11px}
.od-note-text{color:#dddde8;font-size:12px}
.od-note-form textarea{width:100%;background:#15151b;border:1px solid rgba(255,255,255,0.07);border-radius:8px;color:#dddde8;padding:10px;min-height:90px;margin-top:12px}
.od-note-submit{margin-top:10px;background:#f5b800;border:0;border-radius:8px;padding:7px 14px;font-weight:700}

.od-timeline { position: relative; margin-left: 6px; }
.od-timeline::before { content: ''; position: absolute; top: 6px; bottom: 6px; left: 5px; width: 2px; background: rgba(245,184,0,0.18); }
.od-tl-item { position: relative; padding-left: 24px; margin-bottom: 18px; }
.od-tl-item:last-child { margin-bottom: 0; }
.od-tl-dot { position: absolute; top: 4px; left: 0; width: 12px; height: 12px; border-radius: 50%; background: rgba(245,184,0,0.2); border: 2px solid rgba(245,184,0,0.4); }
.od-tl-item.done .od-tl-dot { background: #f5b800; border-color: #f5b800; box-shadow: 0 0 0 4px rgba(245,184,0,0.12); }
.od-tl-label { color: #dddde8; font-size: 14px; font-weight: 600; }
.od-tl-time { color: #5e5e72; font-size: 12px; margin-top: 2px; }
.od-hist-status { display:inline-flex; align-items:center; gap:6px; margin-top:4px; font-size:11px; color:#9ba0bd; }
.od-hist-status strong { color:#f5b800; font-weight:700; }
</style>

<div class="content od-wrap">
  @include('admin.partials.flash')

  <div class="od-head">
    <div class="od-head-left">
      <h1>Order <span>#{{ $displayOrderNumber }}</span></h1>
      <p>{{ $order->created_at?->format('d M Y, H:i') }}</p>
    </div>
    <div class="d-flex gap-2">
      @if($order->status === 'pending_approval')
        <form method="POST" action="{{ route('admin.orders.approve', $order) }}">@csrf
          <button class="od-btn-approve" type="submit"><i class="fa fa-check"></i> Approve & Send Payment Link</button>
        </form>
        <form method="POST" action="{{ route('admin.orders.reject', $order) }}">@csrf
          <button class="od-btn-back" type="submit"><i class="fa fa-times text-danger"></i> Reject</button>
        </form>
      @endif
      <a href="{{ route('admin.orders.edit', $order) }}" class="od-btn-back"><i class="fa fa-pen"></i> Edit</a>
      <a href="{{ route('admin.orders.index') }}" class="od-btn-back"><i class="fa fa-arrow-left"></i> Back</a>
    </div>
  </div>

  <div class="od-grid">
    <div>
      <div class="od-card">
        <div class="od-card-head">
          <div class="od-card-title">Order Summary</div>
          <span class="od-status {{ $statusClass }}">{{ $statusLabel }}</span>
        </div>
        <div class="od-card-body">
          <div class="od-info-row"><span class="od-info-label">Order #</span><span class="od-info-val">{{ $displayOrderNumber }}</span></div>
          <div class="od-info-row"><span class="od-info-label">Date</span><span class="od-info-val">{{ $order->created_at?->format('d M Y, H:i') }}</span></div>
          <div class="od-info-row"><span class="od-info-label">Payment Method</span><span class="od-info-val">{{ ucwords(str_replace('_',' ',(string)$order->payment_method)) }}</span></div>
          <div class="od-info-row"><span class="od-info-label">Payment Status</span><span class="od-info-val">{{ ucwords(str_replace('_',' ',(string)$order->payment_status)) }}</span></div>
          @if($order->status === 'pending_payment' && $paymentLink)
            <div class="od-info-row">
              <span class="od-info-label">Payment Link</span>
              <span class="od-info-val" style="max-width:65%;">
                <input type="text" readonly value="{{ $paymentLink }}" class="form-control form-control-sm" onclick="this.select();">
              </span>
            </div>
          @endif
        </div>
      </div>

      <div class="od-card">
        <div class="od-card-head"><div class="od-card-title">Customer</div></div>
        <div class="od-card-body">
          <div class="od-customer">
            <div class="od-avatar">{{ $customerInitials ?: 'NA' }}</div>
            <div>
              <div style="color:#fff;font-weight:600">{{ $customerName }}</div>
              <div style="font-size:12px;color:#5e5e72">{{ $order->customer?->email ?: '-' }}</div>
              <div style="font-size:12px;color:#5e5e72">{{ $order->customer?->phone ?: '-' }}</div>
            </div>
          </div>
        </div>
      </div>

      <div class="od-card">
        <div class="od-card-head">
          <div class="od-card-title">Tickets</div>
          <span style="font-size:12px;color:#5e5e72;">{{ $order->items->count() }} ticket(s)</span>
        </div>
        <div class="od-card-body">
          @forelse($order->items as $item)
            <div class="od-ticket">
              <div>
                <div class="od-ticket-name">{{ $item->ticket_name }}</div>
                <div class="od-ticket-holder">
                  <div class="od-ticket-holder-avatar">{{ collect(explode(' ', (string)$item->holder_name))->filter()->map(fn($p)=>mb_substr($p,0,1))->take(2)->implode('') }}</div>
                  <div class="od-ticket-holder-info">{{ $item->holder_name }}<span>{{ $item->holder_email }} — {{ $item->holder_phone ?: '-' }}</span></div>
                </div>
              </div>
              <div><div class="od-ticket-price">{{ number_format((float)$item->line_total,2) }} EGP</div><div class="od-ticket-qty">Qty × {{ $item->quantity }}</div></div>
            </div>
          @empty
            <div class="od-note-text">No tickets found.</div>
          @endforelse

          <div class="od-total-bar"><span>Total Amount</span><strong>{{ number_format((float)$order->total_amount,2) }} EGP</strong></div>
        </div>
      </div>

      <div class="od-card">
        <div class="od-card-head">
          <div class="od-card-title">Internal Notes</div>
          <span style="font-size:12px;color:#5e5e72;">{{ $notes->count() }} notes</span>
        </div>
        <div class="od-card-body">
          @forelse($notes as $note)
            <div class="od-note-item">
              <div class="od-note-avatar">{{ collect(explode(' ', (string)($note->causer?->name ?? 'NA')))->filter()->map(fn($p)=>mb_substr($p,0,1))->take(2)->implode('') ?: 'NA' }}</div>
              <div>
                <div class="od-note-author">{{ $note->causer?->name ?? 'System' }}<span>{{ $note->created_at?->format('d M Y, H:i') }}</span></div>
                <div class="od-note-text">{{ data_get($note->properties, 'body', $note->description) }}</div>
              </div>
            </div>
          @empty
            <div class="od-note-text">No notes yet.</div>
          @endforelse

          <form class="od-note-form" method="POST" action="{{ route('admin.orders.notes.store', $order) }}">
            @csrf
            <textarea name="body" placeholder="Add an internal note about this order…">{{ old('body') }}</textarea>
            <button type="submit" class="od-note-submit"><i class="fa fa-paper-plane"></i> Add Note</button>
          </form>
        </div>
      </div>

      <div class="od-card">
        <div class="od-card-head">
          <div class="od-card-title">Order History</div>
          <span style="font-size:12px;color:#5e5e72;">{{ $history->count() }} events</span>
        </div>
        <div class="od-card-body">
          @forelse($history as $log)
            <div class="od-hist-item">
              <div class="od-hist-icon"><i class="fa fa-clock"></i></div>
              <div>
                <div class="od-hist-action">{{ $log->description }} @if($log->causer)<strong>by {{ $log->causer->name }}</strong>@endif</div>
                @if(filled(data_get($log->properties, 'from_status')) || filled(data_get($log->properties, 'to_status')))
                  <div class="od-hist-status">
                    <span>Status:</span>
                    <strong>{{ ucwords(str_replace('_', ' ', (string) data_get($log->properties, 'from_status', 'N/A'))) }}</strong>
                    <i class="fa fa-arrow-right"></i>
                    <strong>{{ ucwords(str_replace('_', ' ', (string) data_get($log->properties, 'to_status', 'N/A'))) }}</strong>
                  </div>
                @endif
                <div class="od-hist-meta">{{ $log->created_at?->format('d M Y, H:i') }}</div>
              </div>
            </div>
          @empty
            <div class="od-note-text">No history yet.</div>
          @endforelse
        </div>
      </div>
    </div>

    <div>
      <div class="od-card">
        <div class="od-card-head"><div class="od-card-title">Activity</div></div>
        <div class="od-card-body">
          <div class="od-timeline">
            @foreach($activityTimeline as $activity)
              <div class="od-tl-item {{ $activity['done'] ? 'done' : '' }}">
                <div class="od-tl-dot"></div>
                <div class="od-tl-label">{{ $activity['label'] }}</div>
                <div class="od-tl-time">{{ $activity['at'] ? $activity['at']->format('d M Y, H:i') : '—' }}</div>
              </div>
            @endforeach
          </div>
        </div>
      </div>
    </div>
  </div>
</div>
@endsection
