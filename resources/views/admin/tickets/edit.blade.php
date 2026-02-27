@extends('admin.master')

@section('content')
@php
  $statusOptions = [
    'not_checked_in' => 'Not Checked In',
    'checked_in'     => 'Checked In',
    'canceled'       => 'Canceled',
  ];
@endphp

<style>
.te-wrap { padding: 12px 0 80px; }
.te-head { display:flex; justify-content:space-between; align-items:flex-start; gap:16px; flex-wrap:wrap; margin-bottom:28px; }
.te-head-title { font-family:'Syne',sans-serif; font-weight:800; font-size:26px; color:#fff; margin:0; letter-spacing:-0.4px; }
.te-head-title span { color:#f5b800; }
.te-head-sub { margin:5px 0 0; color:#5e5e72; font-size:13px; }
.te-btn { display:inline-flex; align-items:center; gap:7px; padding:9px 16px; border-radius:9px; font-size:13px; font-weight:600; text-decoration:none; border:1px solid rgba(255,255,255,.08); background:#15151b; color:#bbb; cursor:pointer; transition:all .18s; }
.te-btn:hover { border-color:rgba(255,255,255,.18); color:#fff; background:#1e1e27; }
.te-btn-gold { background:#f5b800; border-color:#f5b800; color:#0d0d10; font-family:'Syne',sans-serif; font-weight:800; }
.te-btn-gold:hover { background:#ffc107; border-color:#ffc107; color:#0d0d10; }
.te-grid { display:grid; grid-template-columns:1fr 280px; gap:20px; align-items:start; }
@media(max-width:900px){ .te-grid { grid-template-columns:1fr; } }
.te-card { background:#0d0d10; border:1px solid rgba(255,255,255,.07); border-radius:14px; overflow:hidden; margin-bottom:20px; }
.te-card-head { display:flex; align-items:center; justify-content:space-between; padding:14px 20px; border-bottom:1px solid rgba(255,255,255,.06); gap:10px; }
.te-card-title { font-family:'Syne',sans-serif; font-size:11px; font-weight:700; letter-spacing:2px; text-transform:uppercase; color:#f5b800; display:flex; align-items:center; gap:8px; }
.te-card-title::before { content:''; width:3px; height:13px; background:#f5b800; border-radius:2px; flex-shrink:0; }
.te-card-body { padding:20px; }
.te-field { margin-bottom:14px; }
.te-field:last-child { margin-bottom:0; }
.te-label { display:block; font-size:11px; font-weight:600; letter-spacing:0.6px; text-transform:uppercase; color:#5e5e72; margin-bottom:7px; }
.te-input, .te-select {
  width:100%; background:#15151b; border:1px solid rgba(255,255,255,.1);
  border-radius:8px; color:#dddde8; padding:9px 12px; font-size:13.5px;
  outline:none; transition:border-color .18s, box-shadow .18s;
  box-sizing:border-box; font-family:'DM Sans',sans-serif;
}
.te-select {
  appearance:none; -webkit-appearance:none;
  background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='13' height='13' viewBox='0 0 24 24' fill='none' stroke='%235e5e72' stroke-width='2.5'%3E%3Cpath d='M6 9l6 6 6-6'/%3E%3C/svg%3E");
  background-repeat:no-repeat; background-position:right 12px center; background-size:13px;
  padding-right:36px; cursor:pointer;
}
.te-input:focus,.te-select:focus { border-color:rgba(245,184,0,.5); box-shadow:0 0 0 3px rgba(245,184,0,.08); }
.te-input::placeholder { color:#3e3e52; }
.te-input[readonly] { opacity:.55; cursor:not-allowed; }
.te-row-2 { display:grid; grid-template-columns:1fr 1fr; gap:14px; }
@media(max-width:600px){ .te-row-2 { grid-template-columns:1fr; } }
.te-divider { border:none; border-top:1px solid rgba(255,255,255,.06); margin:16px 0; }
.te-info-row { display:flex; align-items:center; justify-content:space-between; padding:10px 0; border-bottom:1px solid rgba(255,255,255,.05); font-size:13px; gap:12px; }
.te-info-row:last-child { border-bottom:none; }
.te-info-label { color:#5e5e72; font-size:11px; text-transform:uppercase; letter-spacing:0.6px; white-space:nowrap; }
.te-info-val { color:#dddde8; font-weight:500; text-align:right; font-size:12px; }
.te-qr-wrap { display:flex; flex-direction:column; align-items:center; gap:12px; padding:8px 0; }
.te-qr-wrap img { border-radius:12px; border:2px solid rgba(255,255,255,.07); background:#fff; padding:10px; width:160px; height:160px; }
.te-qr-num { font-family:'Syne',sans-serif; font-size:12px; font-weight:700; color:#f5b800; letter-spacing:1px; }
.te-status { display:inline-flex; align-items:center; gap:5px; font-family:'Syne',sans-serif; font-size:10px; font-weight:700; padding:3px 10px; border-radius:99px; }
.te-status-pending  { color:#f5b800; background:rgba(245,184,0,.12); border:1px solid rgba(245,184,0,.25); }
.te-status-approved { color:#22c55e; background:rgba(34,197,94,.10); border:1px solid rgba(34,197,94,.25); }
.te-status-rejected { color:#e8445a; background:rgba(232,68,90,.10); border:1px solid rgba(232,68,90,.25); }
</style>

<div class="content te-wrap">
  @include('admin.partials.flash')

  <form method="POST" action="{{ route('admin.tickets.update', $ticket) }}">
    @csrf
    @method('PUT')

    {{-- Header --}}
    <div class="te-head">
      <div>
        <h1 class="te-head-title">Edit Ticket <span>#{{ $ticket->ticket_number ?? 'N/A' }}</span></h1>
        <p class="te-head-sub">{{ $ticket->eventLabel() ?: 'No event assigned' }} — update holder info and status.</p>
      </div>
      <div style="display:flex;gap:10px;flex-wrap:wrap;align-items:center;">
        <a href="{{ route('admin.tickets.show', $ticket) }}" class="te-btn"><i class="fa fa-eye"></i> View</a>
        <a href="{{ route('admin.tickets.download', $ticket) }}" class="te-btn"><i class="fa fa-download"></i> PDF</a>
        <button type="submit" class="te-btn te-btn-gold"><i class="fa fa-save"></i> Save Changes</button>
      </div>
    </div>

    <div class="te-grid">

      {{-- LEFT --}}
      <div>

        <div class="te-card">
          <div class="te-card-head"><div class="te-card-title">Holder Information</div></div>
          <div class="te-card-body">
            <div class="te-field">
              <label class="te-label">Holder Name</label>
              <input class="te-input" type="text" name="holder_name"
                value="{{ old('holder_name', $ticket->holder_name) }}"
                placeholder="Full name…">
            </div>
            <div class="te-row-2">
              <div class="te-field">
                <label class="te-label">Email</label>
                <input class="te-input" type="email" name="holder_email"
                  value="{{ old('holder_email', $ticket->holder_email) }}"
                  placeholder="email@example.com">
              </div>
              <div class="te-field">
                <label class="te-label">Phone</label>
                <input class="te-input" type="text" name="holder_phone"
                  value="{{ old('holder_phone', $ticket->holder_phone) }}"
                  placeholder="+20…">
              </div>
            </div>
          </div>
        </div>

        <div class="te-card">
          <div class="te-card-head"><div class="te-card-title">Ticket Settings</div></div>
          <div class="te-card-body">
            <div class="te-field">
              <label class="te-label">Status</label>
              <select class="te-select" name="status">
                @foreach($statusOptions as $val => $label)
                  <option value="{{ $val }}" {{ old('status', $ticket->status) === $val ? 'selected' : '' }}>{{ $label }}</option>
                @endforeach
              </select>
            </div>
            <hr class="te-divider">
            <div class="te-field">
              <label class="te-label">Ticket Number <span style="color:#5e5e72;font-weight:400;text-transform:none;">(read-only)</span></label>
              <input class="te-input" type="text" value="{{ $ticket->ticket_number ?? 'N/A' }}" readonly>
            </div>
          </div>
        </div>

      </div>{{-- /left --}}

      {{-- RIGHT --}}
      <div>

        <div class="te-card">
          <div class="te-card-head"><div class="te-card-title">QR Code</div></div>
          <div class="te-card-body">
            <div class="te-qr-wrap">
              <img src="https://api.qrserver.com/v1/create-qr-code/?size=200x200&data={{ urlencode($ticket->qr_payload ?: $ticket->ticket_number) }}" alt="QR Code">
              <span class="te-qr-num">{{ $ticket->ticket_number ?? 'N/A' }}</span>
              <span style="font-size:11px;color:#5e5e72;">Scan at event entrance</span>
            </div>
          </div>
        </div>

        <div class="te-card">
          <div class="te-card-head"><div class="te-card-title">Ticket Info</div></div>
          <div class="te-card-body">
            <div class="te-info-row">
              <span class="te-info-label">Event</span>
              <span class="te-info-val">{{ $ticket->eventLabel() ?: '-' }}</span>
            </div>
            <div class="te-info-row">
              <span class="te-info-label">Type</span>
              <span class="te-info-val">{{ $ticket->ticketTypeLabel() ?: '-' }}</span>
            </div>
            <div class="te-info-row">
              <span class="te-info-label">Order #</span>
              <span class="te-info-val">
                @if($ticket->order)
                  <a href="{{ route('admin.orders.show', $ticket->order) }}" style="color:#f5b800;text-decoration:none;">{{ $ticket->order->order_number }}</a>
                @else -
                @endif
              </span>
            </div>
            <div class="te-info-row">
              <span class="te-info-label">Status</span>
              <span class="te-info-val">
                @php $sc = ['not_checked_in'=>'te-status-pending','checked_in'=>'te-status-approved','canceled'=>'te-status-rejected'][$ticket->status] ?? 'te-status-pending'; @endphp
                <span class="te-status {{ $sc }}">{{ $statusOptions[$ticket->status] ?? $ticket->status }}</span>
              </span>
            </div>
            <div class="te-info-row">
              <span class="te-info-label">Issued At</span>
              <span class="te-info-val">{{ $ticket->issued_at?->format('d M Y, H:i') ?: '—' }}</span>
            </div>
            <div class="te-info-row">
              <span class="te-info-label">Checked In</span>
              <span class="te-info-val" style="{{ $ticket->checked_in_at ? 'color:#22c55e;' : '' }}">
                {{ $ticket->checked_in_at?->format('d M Y, H:i') ?: '—' }}
              </span>
            </div>
          </div>
        </div>

      </div>{{-- /right --}}

    </div>
  </form>
</div>
@endsection
