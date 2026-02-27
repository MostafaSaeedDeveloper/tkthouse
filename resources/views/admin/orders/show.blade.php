@extends('admin.master')

@section('content')

<style>
.od-wrap { padding: 8px 0 60px; }

/* ── Page header ── */
.od-head { display: flex; align-items: flex-start; justify-content: space-between; gap: 16px; flex-wrap: wrap; margin-bottom: 28px; }
.od-head-left p  { font-size: 13px; color: #5e5e72; margin: 4px 0 0; }
.od-head-left h1 { font-family: 'Syne', sans-serif; font-size: 24px; font-weight: 800; color: #fff; margin: 0; letter-spacing: -0.3px; }
.od-head-left h1 span { color: #f5b800; }

/* ── Grid ── */
.od-grid { display: grid; grid-template-columns: 1fr 340px; gap: 20px; align-items: start; }
@media (max-width: 900px) { .od-grid { grid-template-columns: 1fr; } }

/* ── Card ── */
.od-card { background: #0d0d10; border: 1px solid rgba(255,255,255,0.07); border-radius: 12px; overflow: hidden; margin-bottom: 20px; }
.od-card-head { display: flex; align-items: center; justify-content: space-between; padding: 15px 20px; border-bottom: 1px solid rgba(255,255,255,0.07); gap: 10px; }
.od-card-title { font-family: 'Syne', sans-serif; font-size: 11px; font-weight: 700; letter-spacing: 2px; text-transform: uppercase; color: #f5b800; display: flex; align-items: center; gap: 8px; }
.od-card-title::before { content: ''; width: 3px; height: 13px; background: #f5b800; border-radius: 2px; flex-shrink: 0; }
.od-card-body { padding: 20px; }

/* ── Info rows ── */
.od-info-row { display: flex; align-items: center; justify-content: space-between; padding: 11px 0; border-bottom: 1px solid rgba(255,255,255,0.05); font-size: 13.5px; gap: 12px; }
.od-info-row:last-child { border-bottom: none; }
.od-info-label { color: #5e5e72; font-size: 12px; text-transform: uppercase; letter-spacing: 0.6px; flex-shrink: 0; }
.od-info-val   { color: #dddde8; font-weight: 500; text-align: right; }

/* ── Status badge ── */
.od-status { display: inline-flex; align-items: center; gap: 6px; font-family: 'Syne', sans-serif; font-size: 11px; font-weight: 700; letter-spacing: 0.5px; padding: 4px 11px; border-radius: 99px; }
.od-status::before { content: ''; width: 6px; height: 6px; border-radius: 50%; background: currentColor; }
.od-status.pending          { color: #f5b800; background: rgba(245,184,0,0.12);  border: 1px solid rgba(245,184,0,0.25); }
.od-status.approved         { color: #22c55e; background: rgba(34,197,94,0.10);  border: 1px solid rgba(34,197,94,0.25); }
.od-status.paid             { color: #3b82f6; background: rgba(59,130,246,0.10); border: 1px solid rgba(59,130,246,0.25); }
.od-status.rejected         { color: #e8445a; background: rgba(232,68,90,0.10);  border: 1px solid rgba(232,68,90,0.25); }
.od-status.pending_approval { color: #f5b800; background: rgba(245,184,0,0.12);  border: 1px dashed rgba(245,184,0,0.4); animation: blink-dot 1.8s ease-in-out infinite; }
@keyframes blink-dot { 0%,100%{opacity:1} 50%{opacity:0.5} }

/* ── Customer ── */
.od-customer { display: flex; align-items: center; gap: 14px; }
.od-avatar { width: 44px; height: 44px; border-radius: 50%; background: rgba(245,184,0,0.12); border: 1px solid rgba(245,184,0,0.25); display: flex; align-items: center; justify-content: center; font-family: 'Syne', sans-serif; font-size: 16px; font-weight: 800; color: #f5b800; flex-shrink: 0; }
.od-customer-name { font-size: 14px; font-weight: 600; color: #fff; margin-bottom: 2px; }
.od-customer-meta { font-size: 12px; color: #5e5e72; }

/* ── Buttons ── */
.od-btn-approve { display: inline-flex; align-items: center; gap: 8px; background: #f5b800; color: #000; font-family: 'Syne', sans-serif; font-size: 13px; font-weight: 700; border: none; border-radius: 8px; padding: 9px 18px; cursor: pointer; transition: background 0.2s; white-space: nowrap; }
.od-btn-approve:hover { background: #ffc820; }
.od-btn-back { display: inline-flex; align-items: center; gap: 8px; background: #15151b; color: #5e5e72; font-size: 13px; font-weight: 500; border: 1px solid rgba(255,255,255,0.07); border-radius: 8px; padding: 9px 18px; text-decoration: none; transition: border-color 0.2s, color 0.2s; }
.od-btn-back:hover { border-color: rgba(245,184,0,0.35); color: #f5b800; }

/* ── Tickets ── */
.od-ticket { background: #15151b; border: 1px solid rgba(255,255,255,0.07); border-radius: 10px; padding: 16px 18px; margin-bottom: 12px; display: grid; grid-template-columns: 1fr auto; gap: 12px; align-items: start; transition: border-color 0.2s; }
.od-ticket:last-child { margin-bottom: 0; }
.od-ticket:hover { border-color: rgba(245,184,0,0.18); }
.od-ticket-name { font-family: 'Syne', sans-serif; font-size: 14px; font-weight: 700; color: #fff; margin-bottom: 4px; }
.od-ticket-type { display: inline-block; background: rgba(245,184,0,0.10); border: 1px solid rgba(245,184,0,0.2); color: #f5b800; font-size: 10px; font-weight: 700; font-family: 'Syne', sans-serif; letter-spacing: 0.5px; padding: 2px 8px; border-radius: 99px; margin-bottom: 10px; }
.od-ticket-holder { display: flex; align-items: center; gap: 8px; }
.od-ticket-holder-avatar { width: 28px; height: 28px; border-radius: 50%; background: rgba(255,255,255,0.06); display: flex; align-items: center; justify-content: center; font-size: 11px; font-weight: 700; color: #dddde8; flex-shrink: 0; }
.od-ticket-holder-info { font-size: 12.5px; color: #dddde8; }
.od-ticket-holder-info span { display: block; font-size: 11px; color: #5e5e72; margin-top: 1px; }
.od-ticket-price { font-family: 'Inter', sans-serif; font-size: 18px; font-weight: 700; color: #f5b800; text-align: right; white-space: nowrap; }
.od-ticket-qty   { font-size: 11px; color: #5e5e72; text-align: right; margin-top: 3px; }

/* ── Total bar ── */
.od-total-bar { display: flex; align-items: center; justify-content: space-between; background: rgba(245,184,0,0.06); border: 1px solid rgba(245,184,0,0.2); border-radius: 10px; padding: 14px 20px; margin-top: 16px; }
.od-total-label { font-family: 'Syne', sans-serif; font-size: 12px; font-weight: 700; letter-spacing: 1px; text-transform: uppercase; color: #5e5e72; }
.od-total-val   { font-family: 'Inter', sans-serif; font-size: 22px; font-weight: 700; color: #f5b800; }

/* ── Timeline ── */
.od-timeline { display: flex; flex-direction: column; }
.od-tl-item { display: flex; gap: 14px; padding-bottom: 20px; position: relative; }
.od-tl-item:last-child { padding-bottom: 0; }
.od-tl-item:last-child .od-tl-line { display: none; }
.od-tl-dot  { width: 10px; height: 10px; border-radius: 50%; background: rgba(245,184,0,0.2); border: 2px solid rgba(245,184,0,0.35); flex-shrink: 0; margin-top: 3px; position: relative; z-index: 1; }
.od-tl-dot.active { background: #f5b800; border-color: #f5b800; box-shadow: 0 0 8px rgba(245,184,0,0.4); }
.od-tl-line { position: absolute; left: 4px; top: 14px; bottom: 0; width: 1px; background: rgba(255,255,255,0.07); }
.od-tl-body  { flex: 1; }
.od-tl-event { font-size: 13px; color: #dddde8; font-weight: 500; margin-bottom: 2px; }
.od-tl-time  { font-size: 11px; color: #5e5e72; }

/* ════════════════════════════════════
   NOTES
   ════════════════════════════════════ */

/* Existing notes list */
.od-note-item { display: flex; gap: 12px; padding: 14px 0; border-bottom: 1px solid rgba(255,255,255,0.05); }
.od-note-item:first-child { padding-top: 0; }
.od-note-item:last-child  { border-bottom: none; padding-bottom: 0; }
.od-note-avatar { width: 32px; height: 32px; border-radius: 50%; background: rgba(255,255,255,0.06); border: 1px solid rgba(255,255,255,0.08); display: flex; align-items: center; justify-content: center; font-size: 11px; font-weight: 700; color: #dddde8; flex-shrink: 0; }
.od-note-author { font-size: 12px; font-weight: 600; color: #fff; margin-bottom: 3px; }
.od-note-author span { font-weight: 400; color: #5e5e72; margin-left: 6px; font-size: 11px; }
.od-note-text { font-size: 13px; color: #b0b0c0; line-height: 1.6; }

/* No notes state */
.od-notes-empty { text-align: center; padding: 24px 0; color: #5e5e72; font-size: 13px; }
.od-notes-empty i { display: block; font-size: 24px; margin-bottom: 8px; opacity: 0.3; }

/* Add note textarea */
.od-note-form { margin-top: 16px; border-top: 1px solid rgba(255,255,255,0.06); padding-top: 16px; }
.od-note-form textarea {
    width: 100%; background: #15151b; border: 1px solid rgba(255,255,255,0.07);
    border-radius: 8px; color: #dddde8; font-family: 'DM Sans', sans-serif;
    font-size: 13px; padding: 12px 14px; resize: vertical; min-height: 80px;
    outline: none; transition: border-color 0.2s, box-shadow 0.2s;
}
.od-note-form textarea:focus { border-color: rgba(245,184,0,0.35); box-shadow: 0 0 0 3px rgba(245,184,0,0.08); }
.od-note-form textarea::placeholder { color: #5e5e72; }
.od-note-submit { display: inline-flex; align-items: center; gap: 7px; background: rgba(245,184,0,0.10); border: 1px solid rgba(245,184,0,0.25); color: #f5b800; font-family: 'Syne', sans-serif; font-size: 12px; font-weight: 700; padding: 8px 16px; border-radius: 7px; cursor: pointer; margin-top: 10px; transition: background 0.2s; }
.od-note-submit:hover { background: rgba(245,184,0,0.18); }

/* ════════════════════════════════════
   ORDER HISTORY
   ════════════════════════════════════ */
.od-hist-item { display: flex; gap: 14px; padding: 13px 0; border-bottom: 1px solid rgba(255,255,255,0.05); align-items: flex-start; }
.od-hist-item:first-child { padding-top: 0; }
.od-hist-item:last-child  { border-bottom: none; padding-bottom: 0; }

.od-hist-icon { width: 32px; height: 32px; border-radius: 8px; display: flex; align-items: center; justify-content: center; font-size: 13px; flex-shrink: 0; }
.od-hist-icon.gold  { background: rgba(245,184,0,0.10); color: #f5b800; }
.od-hist-icon.green { background: rgba(34,197,94,0.10);  color: #22c55e; }
.od-hist-icon.red   { background: rgba(232,68,90,0.10);  color: #e8445a; }
.od-hist-icon.blue  { background: rgba(59,130,246,0.10); color: #3b82f6; }
.od-hist-icon.grey  { background: rgba(255,255,255,0.05); color: #5e5e72; }

.od-hist-body   { flex: 1; }
.od-hist-action { font-size: 13px; color: #dddde8; font-weight: 500; margin-bottom: 2px; }
.od-hist-action strong { color: #fff; }
.od-hist-meta   { font-size: 11px; color: #5e5e72; }
.od-hist-badge  { display: inline-flex; align-items: center; gap: 4px; font-family: 'Syne', sans-serif; font-size: 10px; font-weight: 700; padding: 2px 8px; border-radius: 99px; margin-left: 8px; }
.od-hist-badge.from { background: rgba(232,68,90,0.10);  color: #e8445a; border: 1px solid rgba(232,68,90,0.2); }
.od-hist-badge.to   { background: rgba(34,197,94,0.10);  color: #22c55e; border: 1px solid rgba(34,197,94,0.2); }
</style>

<div class="content od-wrap">

    {{-- ── Page header ── --}}
    <div class="od-head">
        <div class="od-head-left">
            <p>Admin Panel / Orders</p>
            {{-- WIRE: $order->order_number --}}
            <h1>Order <span>#ORD-1041</span></h1>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            {{-- WIRE: @if($order->status === 'pending_approval')
            <form method="POST" action="{{ route('admin.orders.approve', $order) }}">@csrf
                <button type="submit" class="od-btn-approve"><i class="fa fa-check"></i> Approve & Send Payment Link</button>
            </form>
            @endif --}}
            <button class="od-btn-approve"><i class="fa fa-check"></i> Approve & Send Payment Link</button>
            <a href="{{ route('admin.orders.index') }}" class="od-btn-back"><i class="fa fa-arrow-left"></i> Back</a>
        </div>
    </div>

    <div class="od-grid">

        {{-- ══════════════════ LEFT ══════════════════ --}}
        <div>

            {{-- Order Summary --}}
            <div class="od-card">
                <div class="od-card-head">
                    <div class="od-card-title">Order Summary</div>
                    {{-- WIRE: <span class="od-status {{ $order->status }}">...</span> --}}
                    <span class="od-status pending_approval">Pending Approval</span>
                </div>
                <div class="od-card-body">
                    <div class="od-info-row">
                        <span class="od-info-label">Order #</span>
                        <span class="od-info-val" style="color:#f5b800;font-family:'Inter',sans-serif;font-weight:700;">#ORD-1041</span>
                        {{-- WIRE: {{ $order->order_number }} --}}
                    </div>
                    <div class="od-info-row">
                        <span class="od-info-label">Date</span>
                        <span class="od-info-val">27 Feb 2026, 14:32</span>
                        {{-- WIRE: {{ $order->created_at->format('d M Y, H:i') }} --}}
                    </div>
                    <div class="od-info-row">
                        <span class="od-info-label">Status</span>
                        <span class="od-status pending_approval">Pending Approval</span>
                        {{-- WIRE: <span class="od-status {{ $order->status }}">{{ ucwords(str_replace('_',' ',$order->status)) }}</span> --}}
                    </div>
                    <div class="od-info-row">
                        <span class="od-info-label">Payment Method</span>
                        <span class="od-info-val">Pending Admin Review</span>
                        {{-- WIRE: {{ ucwords(str_replace('_',' ',$order->payment_method)) }} --}}
                    </div>
                    <div class="od-info-row">
                        <span class="od-info-label">Payment Status</span>
                        <span class="od-info-val">Unpaid</span>
                        {{-- WIRE: {{ ucfirst($order->payment_status) }} --}}
                    </div>
                </div>
            </div>

            {{-- Customer --}}
            <div class="od-card">
                <div class="od-card-head">
                    <div class="od-card-title">Customer</div>
                </div>
                <div class="od-card-body">
                    <div class="od-customer">
                        <div class="od-avatar">AY</div>
                        {{-- WIRE: initials from $order->customer->full_name --}}
                        <div>
                            <div class="od-customer-name">Ahmed Youssef</div>
                            {{-- WIRE: {{ $order->customer?->full_name }} --}}
                            <div class="od-customer-meta">ahmed@example.com</div>
                            {{-- WIRE: {{ $order->customer?->email }} --}}
                            <div class="od-customer-meta" style="margin-top:2px;">+20 100 000 0000</div>
                            {{-- WIRE: {{ $order->customer?->phone }} --}}
                        </div>
                    </div>
                </div>
            </div>

            {{-- Tickets --}}
            <div class="od-card">
                <div class="od-card-head">
                    <div class="od-card-title">Tickets</div>
                    <span style="font-size:12px;color:#5e5e72;">2 tickets</span>
                    {{-- WIRE: {{ $order->items->count() }} ticket(s) --}}
                </div>
                <div class="od-card-body">

                    {{-- WIRE: @foreach($order->items as $item) --}}

                    <div class="od-ticket">
                        <div>
                            <div class="od-ticket-name">Sideral</div>
                            {{-- WIRE: {{ $item->ticket_name }} --}}
                            <div class="od-ticket-type">Early Bird</div>
                            {{-- WIRE: tier name --}}
                            <div class="od-ticket-holder">
                                <div class="od-ticket-holder-avatar">AY</div>
                                <div class="od-ticket-holder-info">
                                    Ahmed Youssef
                                    <span>ahmed@example.com — +20 100 000 0000</span>
                                    {{-- WIRE: {{ $item->holder_name }} / {{ $item->holder_email }} / {{ $item->holder_phone }} --}}
                                </div>
                            </div>
                        </div>
                        <div>
                            <div class="od-ticket-price">450.00</div>
                            {{-- WIRE: {{ number_format($item->ticket_price, 2) }} --}}
                            <div class="od-ticket-qty">Qty × 1</div>
                            {{-- WIRE: Qty × {{ $item->quantity }} --}}
                        </div>
                    </div>

                    <div class="od-ticket">
                        <div>
                            <div class="od-ticket-name">Sideral</div>
                            <div class="od-ticket-type">Regular</div>
                            <div class="od-ticket-holder">
                                <div class="od-ticket-holder-avatar">SK</div>
                                <div class="od-ticket-holder-info">
                                    Sara Khalil
                                    <span>sara@example.com — +20 111 000 0000</span>
                                </div>
                            </div>
                        </div>
                        <div>
                            <div class="od-ticket-price">650.00</div>
                            <div class="od-ticket-qty">Qty × 1</div>
                        </div>
                    </div>

                    {{-- WIRE: @endforeach --}}

                    <div class="od-total-bar">
                        <span class="od-total-label">Order Total</span>
                        <span class="od-total-val">1,100.00</span>
                        {{-- WIRE: {{ number_format($order->total_amount, 2) }} --}}
                    </div>
                </div>
            </div>

            {{-- ════════════════════════════════════
                 NOTES
                 WIRE backend:
                   - Model: OrderNote (id, order_id, admin_id, body, created_at)
                   - Route POST: admin.orders.notes.store
                   - $order->notes()->with('admin')->latest()->get()
                 ════════════════════════════════════ --}}
            <div class="od-card">
                <div class="od-card-head">
                    <div class="od-card-title">Notes</div>
                    <span style="font-size:12px;color:#5e5e72;">2 notes</span>
                    {{-- WIRE: {{ $order->notes->count() }} notes --}}
                </div>
                <div class="od-card-body">

                    {{-- Existing notes — WIRE: @forelse($order->notes as $note) --}}
                    <div class="od-note-item">
                        <div class="od-note-avatar">SA</div>
                        {{-- WIRE: initials from $note->admin->name --}}
                        <div>
                            <div class="od-note-author">
                                Super Admin
                                {{-- WIRE: {{ $note->admin->name }} --}}
                                <span>27 Feb 2026, 14:45</span>
                                {{-- WIRE: {{ $note->created_at->format('d M Y, H:i') }} --}}
                            </div>
                            <div class="od-note-text">
                                Customer called to confirm they are attending. Please prioritize approval.
                                {{-- WIRE: {{ $note->body }} --}}
                            </div>
                        </div>
                    </div>

                    <div class="od-note-item">
                        <div class="od-note-avatar">MA</div>
                        <div>
                            <div class="od-note-author">
                                Mohamed Ali
                                <span>27 Feb 2026, 15:10</span>
                            </div>
                            <div class="od-note-text">
                                Sent a reminder to check payment. Waiting on bank transfer confirmation.
                            </div>
                        </div>
                    </div>
                    {{-- WIRE: @empty --}}
                    {{-- <div class="od-notes-empty"><i class="fa fa-note-sticky"></i>No notes yet.</div> --}}
                    {{-- WIRE: @endforelse --}}

                    {{-- Add note form --}}
                    {{-- WIRE: action="{{ route('admin.orders.notes.store', $order) }}" --}}
                    <form class="od-note-form" method="POST" action="#">
                        @csrf
                        <textarea name="body" placeholder="Add an internal note about this order…"></textarea>
                        {{-- WIRE: name="body" --}}
                        <button type="submit" class="od-note-submit">
                            <i class="fa fa-paper-plane"></i> Add Note
                        </button>
                    </form>

                </div>
            </div>

            {{-- ════════════════════════════════════
                 ORDER HISTORY
                 WIRE backend:
                   - Model: OrderHistory (id, order_id, admin_id, action, from_status, to_status, meta, created_at)
                   - Auto-logged on status changes, approvals, note additions
                   - $order->history()->with('admin')->latest()->get()
                 ════════════════════════════════════ --}}
            <div class="od-card">
                <div class="od-card-head">
                    <div class="od-card-title">Order History</div>
                    <span style="font-size:12px;color:#5e5e72;">3 events</span>
                    {{-- WIRE: {{ $order->history->count() }} events --}}
                </div>
                <div class="od-card-body">

                    {{-- WIRE: @foreach($order->history as $log) --}}

                    {{-- Log 1: Order placed --}}
                    <div class="od-hist-item">
                        <div class="od-hist-icon gold"><i class="fa fa-plus"></i></div>
                        <div class="od-hist-body">
                            <div class="od-hist-action">
                                Order placed by <strong>Ahmed Youssef</strong>
                                {{-- WIRE: $log->action + $log->causer_name --}}
                            </div>
                            <div class="od-hist-meta">
                                27 Feb 2026, 14:32
                                {{-- WIRE: {{ $log->created_at->format('d M Y, H:i') }} --}}
                            </div>
                        </div>
                    </div>

                    {{-- Log 2: Status changed --}}
                    <div class="od-hist-item">
                        <div class="od-hist-icon blue"><i class="fa fa-arrow-right-arrow-left"></i></div>
                        <div class="od-hist-body">
                            <div class="od-hist-action">
                                Status changed by <strong>Super Admin</strong>
                                {{-- WIRE: $log->causer_name --}}
                                <span class="od-hist-badge from">Draft</span>
                                {{-- WIRE: {{ ucwords($log->from_status) }} --}}
                                <i class="fa fa-arrow-right" style="font-size:9px;color:#5e5e72;margin:0 2px;"></i>
                                <span class="od-hist-badge to">Pending Approval</span>
                                {{-- WIRE: {{ ucwords($log->to_status) }} --}}
                            </div>
                            <div class="od-hist-meta">27 Feb 2026, 14:33</div>
                        </div>
                    </div>

                    {{-- Log 3: Note added --}}
                    <div class="od-hist-item">
                        <div class="od-hist-icon grey"><i class="fa fa-note-sticky"></i></div>
                        <div class="od-hist-body">
                            <div class="od-hist-action">
                                Note added by <strong>Super Admin</strong>
                            </div>
                            <div class="od-hist-meta">27 Feb 2026, 14:45</div>
                        </div>
                    </div>

                    {{-- WIRE: @endforeach --}}

                </div>
            </div>

        </div>

        {{-- ══════════════════ RIGHT ══════════════════ --}}
        <div>

            {{-- Activity Timeline --}}
            <div class="od-card">
                <div class="od-card-head">
                    <div class="od-card-title">Activity</div>
                </div>
                <div class="od-card-body">
                    <div class="od-timeline">

                        <div class="od-tl-item">
                            <div><div class="od-tl-dot active"></div><div class="od-tl-line"></div></div>
                            <div class="od-tl-body">
                                <div class="od-tl-event">Order submitted</div>
                                <div class="od-tl-time">27 Feb 2026, 14:32</div>
                                {{-- WIRE: {{ $order->created_at->format('d M Y, H:i') }} --}}
                            </div>
                        </div>

                        <div class="od-tl-item">
                            <div><div class="od-tl-dot active"></div><div class="od-tl-line"></div></div>
                            <div class="od-tl-body">
                                <div class="od-tl-event">Awaiting admin approval</div>
                                <div class="od-tl-time">27 Feb 2026, 14:33</div>
                            </div>
                        </div>

                        <div class="od-tl-item">
                            <div><div class="od-tl-dot"></div><div class="od-tl-line"></div></div>
                            <div class="od-tl-body">
                                <div class="od-tl-event" style="color:#5e5e72;">Payment link sent</div>
                                <div class="od-tl-time">—</div>
                            </div>
                        </div>

                        <div class="od-tl-item">
                            <div><div class="od-tl-dot"></div></div>
                            <div class="od-tl-body">
                                <div class="od-tl-event" style="color:#5e5e72;">Payment confirmed</div>
                                <div class="od-tl-time">—</div>
                            </div>
                        </div>

                    </div>
                </div>
            </div>

            {{-- Quick Info --}}
            <div class="od-card">
                <div class="od-card-head">
                    <div class="od-card-title">Quick Info</div>
                </div>
                <div class="od-card-body">
                    <div class="od-info-row">
                        <span class="od-info-label">Tickets</span>
                        <span class="od-info-val">2</span>
                        {{-- WIRE: {{ $order->items->count() }} --}}
                    </div>
                    <div class="od-info-row">
                        <span class="od-info-label">Event</span>
                        <span class="od-info-val">Sideral</span>
                        {{-- WIRE: $order->items->first()->ticket->event->name --}}
                    </div>
                    <div class="od-info-row">
                        <span class="od-info-label">Event Date</span>
                        <span class="od-info-val">20 Mar 2026</span>
                        {{-- WIRE: event_date->format('d M Y') --}}
                    </div>
                    <div class="od-info-row">
                        <span class="od-info-label">Total</span>
                        <span class="od-info-val" style="color:#f5b800;font-family:'Inter',sans-serif;font-weight:700;">
                            1,100.00
                            {{-- WIRE: {{ number_format($order->total_amount, 2) }} --}}
                        </span>
                    </div>
                </div>
            </div>

        </div>

    </div>

</div>
@endsection
