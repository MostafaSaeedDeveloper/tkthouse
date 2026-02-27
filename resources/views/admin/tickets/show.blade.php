@extends('admin.master')

@section('content')
@php
  $statusLabels = [
    'not_checked_in' => 'Not Checked In',
    'checked_in'     => 'Checked In',
    'canceled'       => 'Canceled',
  ];
  $statusClass = [
    'not_checked_in' => 'tk-status-pending',
    'checked_in'     => 'tk-status-approved',
    'canceled'       => 'tk-status-rejected',
  ][$ticket->status] ?? 'tk-status-pending';
  $statusLabel = $statusLabels[$ticket->status] ?? str($ticket->status)->headline();
@endphp

<style>
.tk-wrap  { padding: 12px 0 80px; }

/* ── Header ──────────────────────────────────────────── */
.tk-head  { display:flex; justify-content:space-between; align-items:flex-start; gap:16px; flex-wrap:wrap; margin-bottom:28px; }
.tk-head-title { font-family:'Syne',sans-serif; font-weight:800; font-size:26px; color:#fff; margin:0; letter-spacing:-0.4px; }
.tk-head-title span { color:#f5b800; }
.tk-head-sub { margin:5px 0 0; color:#5e5e72; font-size:13px; }

/* ── Buttons ─────────────────────────────────────────── */
.tk-btn { display:inline-flex; align-items:center; gap:7px; padding:9px 16px; border-radius:9px; font-size:13px; font-weight:600; text-decoration:none; border:1px solid rgba(255,255,255,.08); background:#15151b; color:#bbb; cursor:pointer; transition:all .18s; }
.tk-btn:hover { border-color:rgba(255,255,255,.18); color:#fff; background:#1e1e27; }
.tk-btn-gold    { background:#f5b800; border-color:#f5b800; color:#0d0d10; font-family:'Syne',sans-serif; font-weight:800; }
.tk-btn-gold:hover { background:#ffc107; border-color:#ffc107; color:#0d0d10; }
.tk-btn-green   { background:rgba(34,197,94,.1); border-color:rgba(34,197,94,.25); color:#22c55e; }
.tk-btn-green:hover { background:rgba(34,197,94,.2); }
.tk-btn-red     { background:rgba(232,68,90,.08); border-color:rgba(232,68,90,.25); color:#e8445a; }
.tk-btn-red:hover { background:rgba(232,68,90,.18); }

/* ── Layout ──────────────────────────────────────────── */
.tk-grid { display:grid; grid-template-columns:1fr 280px; gap:20px; align-items:start; }
@media(max-width:900px){ .tk-grid { grid-template-columns:1fr; } }

/* ── Card ────────────────────────────────────────────── */
.tk-card { background:#0d0d10; border:1px solid rgba(255,255,255,.07); border-radius:14px; overflow:hidden; margin-bottom:20px; }
.tk-card-head { display:flex; align-items:center; justify-content:space-between; padding:14px 20px; border-bottom:1px solid rgba(255,255,255,.06); gap:10px; }
.tk-card-title { font-family:'Syne',sans-serif; font-size:11px; font-weight:700; letter-spacing:2px; text-transform:uppercase; color:#f5b800; display:flex; align-items:center; gap:8px; }
.tk-card-title::before { content:''; width:3px; height:13px; background:#f5b800; border-radius:2px; flex-shrink:0; }
.tk-card-body { padding:20px; }

/* ── Info rows ───────────────────────────────────────── */
.tk-info-row { display:flex; align-items:center; justify-content:space-between; padding:11px 0; border-bottom:1px solid rgba(255,255,255,.05); font-size:13.5px; gap:12px; }
.tk-info-row:last-child { border-bottom:none; }
.tk-info-label { color:#5e5e72; font-size:11px; text-transform:uppercase; letter-spacing:0.6px; white-space:nowrap; }
.tk-info-val   { color:#dddde8; font-weight:500; text-align:right; word-break:break-all; }
.tk-info-val.mono { font-family:'DM Sans',monospace; font-size:12px; }

/* ── Status badge ────────────────────────────────────── */
.tk-status { display:inline-flex; align-items:center; gap:6px; font-family:'Syne',sans-serif; font-size:11px; font-weight:700; letter-spacing:0.5px; padding:4px 12px; border-radius:99px; }
.tk-status-pending  { color:#f5b800; background:rgba(245,184,0,.12); border:1px solid rgba(245,184,0,.25); }
.tk-status-approved { color:#22c55e; background:rgba(34,197,94,.10); border:1px solid rgba(34,197,94,.25); }
.tk-status-rejected { color:#e8445a; background:rgba(232,68,90,.10); border:1px solid rgba(232,68,90,.25); }

/* ── QR ──────────────────────────────────────────────── */
.tk-qr-wrap { display:flex; flex-direction:column; align-items:center; gap:14px; padding:8px 0; }
.tk-qr-wrap img { border-radius:12px; border:2px solid rgba(255,255,255,.07); background:#fff; padding:10px; width:180px; height:180px; }
.tk-qr-number { font-family:'Syne',sans-serif; font-size:13px; font-weight:700; color:#f5b800; letter-spacing:1px; }

/* ── Send form ───────────────────────────────────────── */
.tk-send-input { width:100%; background:#15151b; border:1px solid rgba(255,255,255,.1); border-radius:8px; color:#dddde8; padding:9px 12px; font-size:13.5px; outline:none; transition:border-color .18s, box-shadow .18s; box-sizing:border-box; }
.tk-send-input:focus { border-color:rgba(245,184,0,.5); box-shadow:0 0 0 3px rgba(245,184,0,.08); }

/* ── Divider ─────────────────────────────────────────── */
.tk-divider { border:none; border-top:1px solid rgba(255,255,255,.06); margin:4px 0 16px; }

/* ── Holder avatar ───────────────────────────────────── */
.tk-holder { display:flex; align-items:center; gap:12px; margin-bottom:16px; }
.tk-avatar { width:40px; height:40px; border-radius:50%; background:rgba(245,184,0,.12); border:1px solid rgba(245,184,0,.25); color:#f5b800; display:flex; align-items:center; justify-content:center; font-weight:700; font-size:13px; flex-shrink:0; }
.tk-holder-name { color:#fff; font-weight:700; font-size:15px; }
.tk-holder-sub  { font-size:12px; color:#5e5e72; margin-top:2px; }
</style>

<div class="content tk-wrap">
  @include('admin.partials.flash')

  {{-- Header --}}
  <div class="tk-head">
    <div>
      <h1 class="tk-head-title">Ticket <span>#{{ $ticket->ticket_number ?? 'N/A' }}</span></h1>
      <p class="tk-head-sub">{{ $ticket->eventLabel() ?: 'No event assigned' }}</p>
    </div>
    <div style="display:flex;gap:10px;flex-wrap:wrap;align-items:center;">
      <a href="{{ route('admin.tickets.index') }}" class="tk-btn"><i class="fa fa-arrow-left"></i> Back</a>
      <a href="{{ route('admin.tickets.download', $ticket) }}" class="tk-btn"><i class="fa fa-download"></i> PDF</a>
      <a href="{{ route('admin.tickets.edit', $ticket) }}" class="tk-btn tk-btn-gold"><i class="fa fa-pen"></i> Edit</a>
      <form method="POST" action="{{ route('admin.tickets.destroy', $ticket) }}">
        @csrf @method('DELETE')
        <button class="tk-btn tk-btn-red" type="submit"><i class="fa fa-trash"></i> Delete</button>
      </form>
    </div>
  </div>

  <div class="tk-grid">

    {{-- LEFT --}}
    <div>

      {{-- Holder + Core Info --}}
      <div class="tk-card">
        <div class="tk-card-head">
          <div class="tk-card-title">Ticket Info</div>
          <span class="tk-status {{ $statusClass }}">{{ $statusLabel }}</span>
        </div>
        <div class="tk-card-body">

          @php
            $initials = collect(explode(' ', (string)($ticket->holder_name ?: 'NA')))->filter()->map(fn($p)=>mb_substr($p,0,1))->take(2)->implode('');
          @endphp
          <div class="tk-holder">
            <div class="tk-avatar">{{ $initials ?: 'NA' }}</div>
            <div>
              <div class="tk-holder-name">{{ $ticket->holder_name ?: 'Unknown Holder' }}</div>
              <div class="tk-holder-sub">{{ $ticket->holder_email ?: '-' }}</div>
            </div>
          </div>

          <hr class="tk-divider">

          <div class="tk-info-row">
            <span class="tk-info-label">Ticket #</span>
            <span class="tk-info-val mono">{{ $ticket->ticket_number ?? '-' }}</span>
          </div>
          <div class="tk-info-row">
            <span class="tk-info-label">Event</span>
            <span class="tk-info-val">{{ $ticket->eventLabel() ?: '-' }}</span>
          </div>
          <div class="tk-info-row">
            <span class="tk-info-label">Ticket Type</span>
            <span class="tk-info-val">{{ $ticket->ticketTypeLabel() ?: '-' }}</span>
          </div>
          <div class="tk-info-row">
            <span class="tk-info-label">Order #</span>
            <span class="tk-info-val">
              @if($ticket->order)
                <a href="{{ route('admin.orders.show', $ticket->order) }}" style="color:#f5b800;text-decoration:none;">
                  {{ $ticket->order->order_number }}
                </a>
              @else
                -
              @endif
            </span>
          </div>
          <div class="tk-info-row">
            <span class="tk-info-label">Phone</span>
            <span class="tk-info-val">{{ $ticket->holder_phone ?: '-' }}</span>
          </div>
        </div>
      </div>

      {{-- Timestamps --}}
      <div class="tk-card">
        <div class="tk-card-head"><div class="tk-card-title">Timeline</div></div>
        <div class="tk-card-body">
          <div class="tk-info-row">
            <span class="tk-info-label">Created At</span>
            <span class="tk-info-val mono">{{ $ticket->created_at?->format('d M Y, H:i') ?: '-' }}</span>
          </div>
          <div class="tk-info-row">
            <span class="tk-info-label">Issued At</span>
            <span class="tk-info-val mono">{{ $ticket->issued_at?->format('d M Y, H:i') ?: '-' }}</span>
          </div>
          <div class="tk-info-row">
            <span class="tk-info-label">Checked In At</span>
            <span class="tk-info-val mono" style="{{ $ticket->checked_in_at ? 'color:#22c55e;' : '' }}">
              {{ $ticket->checked_in_at?->format('d M Y, H:i') ?: '—' }}
            </span>
          </div>
          <div class="tk-info-row">
            <span class="tk-info-label">Canceled At</span>
            <span class="tk-info-val mono" style="{{ $ticket->canceled_at ? 'color:#e8445a;' : '' }}">
              {{ $ticket->canceled_at?->format('d M Y, H:i') ?: '—' }}
            </span>
          </div>
          <div class="tk-info-row">
            <span class="tk-info-label">Updated At</span>
            <span class="tk-info-val mono">{{ $ticket->updated_at?->format('d M Y, H:i') ?: '-' }}</span>
          </div>
        </div>
      </div>

      {{-- Send --}}
      <div class="tk-card">
        <div class="tk-card-head"><div class="tk-card-title">Send Ticket</div></div>
        <div class="tk-card-body">
          <form method="POST" action="{{ route('admin.tickets.send-email', $ticket) }}">
            @csrf
            <div style="margin-bottom:12px;">
              <label style="display:block;font-size:11px;font-weight:600;letter-spacing:.6px;text-transform:uppercase;color:#5e5e72;margin-bottom:7px;">Email Address</label>
              <input type="email" name="email" class="tk-send-input" value="{{ old('email', $ticket->holder_email) }}" placeholder="recipient@example.com" required>
            </div>
            <div style="display:flex;gap:10px;flex-wrap:wrap;">
              <button class="tk-btn tk-btn-gold" type="submit"><i class="fa fa-envelope"></i> Send Email</button>
              <a href="{{ route('admin.tickets.send-whatsapp', $ticket) }}" class="tk-btn tk-btn-green"><i class="fa fa-brands fa-whatsapp"></i> Send WhatsApp</a>
            </div>
          </form>
        </div>
      </div>

    </div>{{-- /left --}}

    {{-- RIGHT: QR --}}
    <div>
      <div class="tk-card">
        <div class="tk-card-head"><div class="tk-card-title">QR Code</div></div>
        <div class="tk-card-body">
          <div class="tk-qr-wrap">
            <img
              src="https://api.qrserver.com/v1/create-qr-code/?size=220x220&data={{ urlencode($ticket->qr_payload ?: $ticket->ticket_number) }}"
              alt="QR Code for {{ $ticket->ticket_number }}">
            <span class="tk-qr-number">{{ $ticket->ticket_number ?? 'N/A' }}</span>
            <span style="font-size:11px;color:#5e5e72;text-align:center;">Scan at event entrance</span>
          </div>
        </div>
      </div>

      {{-- Quick status card --}}
      <div class="tk-card">
        <div class="tk-card-head"><div class="tk-card-title">Status</div></div>
        <div class="tk-card-body" style="text-align:center;padding:24px 20px;">
          <span class="tk-status {{ $statusClass }}" style="font-size:13px;padding:8px 20px;">
            @if($ticket->status === 'checked_in')
              <i class="fa fa-circle-check"></i>
            @elseif($ticket->status === 'canceled')
              <i class="fa fa-circle-xmark"></i>
            @else
              <i class="fa fa-clock"></i>
            @endif
            {{ $statusLabel }}
          </span>
          @if($ticket->checked_in_at)
            <div style="margin-top:12px;font-size:12px;color:#5e5e72;">
              Checked in {{ $ticket->checked_in_at->diffForHumans() }}
            </div>
          @endif
        </div>
      </div>

    </div>{{-- /right --}}

  </div>
</div>
@endsection
