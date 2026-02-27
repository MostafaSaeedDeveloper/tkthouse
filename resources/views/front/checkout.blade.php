@extends('front.layout.master')

@section('content')

{{-- ============================================================
     CHECKOUT PAGE — Premium Dark Event Ticketing UI
     ============================================================ --}}

<style>
/* ── Variables ── */
:root {
    --bg:        #060608;
    --surface:   #0e0e12;
    --surface2:  #16161d;
    --border:    rgba(255,255,255,0.07);
    --border-h:  rgba(255,185,0,0.45);
    --gold:      #f5b800;
    --gold-dim:  #c99300;
    --text:      #e8e8ef;
    --muted:     #6b6b7e;
    --red:       #e8445a;
    --radius:    12px;
    --font-head: 'Syne', sans-serif;
    --font-body: 'DM Sans', sans-serif;
}

@import url('https://fonts.googleapis.com/css2?family=Syne:wght@600;700;800&family=DM+Sans:wght@300;400;500&display=swap');

/* ── Reset & Base ── */
.co-page { background: var(--bg); min-height: 100vh; font-family: var(--font-body); color: var(--text); }

/* ── Sub-banner ── */
.co-banner {
    background: linear-gradient(135deg, #0b0b10 0%, #12120a 100%);
    border-bottom: 1px solid var(--border);
    padding: 28px 0 24px;
    position: relative;
    overflow: hidden;
}
.co-banner::before {
    content: '';
    position: absolute; inset: 0;
    background: radial-gradient(ellipse 60% 80% at 15% 50%, rgba(245,184,0,0.06) 0%, transparent 70%);
    pointer-events: none;
}
.co-banner .co-breadcrumb {
    font-family: var(--font-head);
    font-size: 11px;
    letter-spacing: 3px;
    text-transform: uppercase;
    color: var(--muted);
    margin-bottom: 6px;
}
.co-banner h1 {
    font-family: var(--font-head);
    font-size: clamp(22px, 4vw, 36px);
    font-weight: 800;
    color: #fff;
    margin: 0;
    letter-spacing: -0.5px;
}
.co-banner h1 span { color: var(--gold); }

/* ── Layout ── */
.co-layout {
    display: grid;
    grid-template-columns: 1fr 360px;
    gap: 28px;
    padding: 40px 0 80px;
    align-items: start;
}
@media (max-width: 900px) {
    .co-layout { grid-template-columns: 1fr; }
    .co-sidebar { order: -1; }
}

/* ── Section label ── */
.co-label {
    display: flex;
    align-items: center;
    gap: 10px;
    font-family: var(--font-head);
    font-size: 11px;
    letter-spacing: 2.5px;
    text-transform: uppercase;
    color: var(--gold);
    margin-bottom: 18px;
}
.co-label::after {
    content: '';
    flex: 1;
    height: 1px;
    background: var(--border);
}

/* ── Card ── */
.co-card {
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: var(--radius);
    padding: 24px;
    margin-bottom: 20px;
    transition: border-color 0.2s;
}
.co-card:hover { border-color: rgba(255,255,255,0.12); }
.co-card-title {
    font-family: var(--font-head);
    font-size: 14px;
    font-weight: 700;
    color: #fff;
    letter-spacing: 0.3px;
    margin-bottom: 18px;
    display: flex;
    align-items: center;
    gap: 8px;
}
.co-card-title .badge-num {
    display: inline-flex; align-items: center; justify-content: center;
    width: 22px; height: 22px; border-radius: 50%;
    background: var(--gold); color: #000;
    font-size: 11px; font-weight: 800;
}

/* ── Form Controls ── */
.co-field { margin-bottom: 14px; }
.co-field label {
    display: block;
    font-size: 11px;
    font-weight: 500;
    letter-spacing: 0.8px;
    text-transform: uppercase;
    color: var(--muted);
    margin-bottom: 6px;
}
.co-field input,
.co-field select,
.co-field textarea {
    width: 100%;
    background: var(--surface2);
    border: 1px solid var(--border);
    border-radius: 8px;
    color: var(--text);
    font-family: var(--font-body);
    font-size: 14px;
    padding: 11px 14px;
    outline: none;
    transition: border-color 0.2s, box-shadow 0.2s;
    -webkit-appearance: none;
}
.co-field input::placeholder,
.co-field select::placeholder { color: var(--muted); }
.co-field input:focus,
.co-field select:focus {
    border-color: var(--gold-dim);
    box-shadow: 0 0 0 3px rgba(245,184,0,0.1);
}
.co-field select { cursor: pointer; }
.co-field select option { background: #1a1a22; }

.co-row { display: grid; gap: 12px; }
.co-row.cols-2 { grid-template-columns: 1fr 1fr; }
.co-row.cols-3 { grid-template-columns: 1fr 1fr 1fr; }
.co-row.cols-5-3 { grid-template-columns: 5fr 3fr; }
@media (max-width: 640px) {
    .co-row.cols-2,
    .co-row.cols-3,
    .co-row.cols-5-3 { grid-template-columns: 1fr; }
}

/* ── Payment radio ── */
.co-pay-options { display: flex; flex-direction: column; gap: 10px; }
.co-pay-opt {
    display: flex;
    align-items: center;
    gap: 12px;
    background: var(--surface2);
    border: 1px solid var(--border);
    border-radius: 8px;
    padding: 13px 16px;
    cursor: pointer;
    transition: border-color 0.2s, background 0.2s;
}
.co-pay-opt:hover { border-color: var(--border-h); background: rgba(245,184,0,0.04); }
.co-pay-opt input[type=radio] { accent-color: var(--gold); width: 16px; height: 16px; flex-shrink: 0; }
.co-pay-opt .pay-icon { font-size: 18px; }
.co-pay-opt .pay-name { font-size: 14px; font-weight: 500; flex: 1; }
.co-pay-pending {
    background: rgba(245,184,0,0.06);
    border: 1px dashed rgba(245,184,0,0.3);
    border-radius: 8px;
    padding: 13px 16px;
    display: flex;
    align-items: center;
    gap: 10px;
    font-size: 13px;
    color: var(--gold);
}

/* ── Ticket Row ── */
.co-ticket-row {
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: var(--radius);
    margin-bottom: 14px;
    overflow: hidden;
    transition: border-color 0.2s;
}
.co-ticket-row:hover { border-color: rgba(255,255,255,0.12); }
.co-ticket-row-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 14px 20px;
    background: var(--surface2);
    border-bottom: 1px solid var(--border);
}
.co-ticket-row-header .ticket-num {
    font-family: var(--font-head);
    font-size: 12px;
    font-weight: 700;
    letter-spacing: 1.5px;
    text-transform: uppercase;
    color: var(--gold);
}
.co-ticket-row-body { padding: 18px 20px; }

/* ── Buttons ── */
.co-btn-primary {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    width: 100%;
    background: var(--gold);
    color: #000;
    font-family: var(--font-head);
    font-size: 14px;
    font-weight: 700;
    letter-spacing: 0.5px;
    border: none;
    border-radius: 8px;
    padding: 15px 24px;
    cursor: pointer;
    transition: background 0.2s, transform 0.1s;
}
.co-btn-primary:hover { background: #ffc820; }
.co-btn-primary:active { transform: scale(0.99); }

.co-btn-add {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    width: 100%;
    background: transparent;
    color: var(--gold);
    font-family: var(--font-head);
    font-size: 13px;
    font-weight: 700;
    letter-spacing: 0.5px;
    border: 1px dashed rgba(245,184,0,0.35);
    border-radius: 8px;
    padding: 13px 24px;
    cursor: pointer;
    transition: background 0.2s, border-color 0.2s;
    margin-bottom: 16px;
}
.co-btn-add:hover { background: rgba(245,184,0,0.07); border-color: rgba(245,184,0,0.6); }

.co-btn-remove {
    background: transparent;
    color: var(--red);
    border: 1px solid rgba(232,68,90,0.25);
    border-radius: 6px;
    padding: 8px 14px;
    font-size: 12px;
    font-weight: 500;
    cursor: pointer;
    transition: background 0.2s, border-color 0.2s;
    white-space: nowrap;
}
.co-btn-remove:hover { background: rgba(232,68,90,0.1); border-color: rgba(232,68,90,0.5); }

/* ── Sidebar ── */
.co-sidebar { position: sticky; top: 24px; }
.co-summary-card {
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: var(--radius);
    overflow: hidden;
}
.co-summary-head {
    background: var(--surface2);
    border-bottom: 1px solid var(--border);
    padding: 16px 22px;
    font-family: var(--font-head);
    font-size: 12px;
    font-weight: 700;
    letter-spacing: 2px;
    text-transform: uppercase;
    color: var(--muted);
}
.co-summary-body { padding: 20px 22px; }
.co-summary-item {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: 12px;
    padding: 10px 0;
    border-bottom: 1px solid var(--border);
    font-size: 13px;
}
.co-summary-item:last-of-type { border-bottom: none; }
.co-summary-item .item-name { color: var(--text); flex: 1; }
.co-summary-item .item-price { color: var(--gold); font-weight: 500; white-space: nowrap; }
.co-summary-total {
    display: flex;
    justify-content: space-between;
    padding: 16px 22px;
    border-top: 1px solid var(--border);
    font-family: var(--font-head);
    font-size: 16px;
    font-weight: 800;
}
.co-summary-total .total-label { color: var(--muted); }
.co-summary-total .total-val { color: var(--gold); }

/* ── Alert ── */
.co-alert-err {
    background: rgba(232,68,90,0.08);
    border: 1px solid rgba(232,68,90,0.3);
    border-radius: 8px;
    padding: 14px 18px;
    margin-bottom: 24px;
    font-size: 13px;
    color: #f0849a;
}
.co-alert-err ul { margin: 0; padding-left: 18px; }

/* ── Note ── */
.co-flow-note {
    font-size: 12px;
    color: var(--muted);
    margin-top: 10px;
    line-height: 1.5;
    min-height: 16px;
}

/* ── Divider ── */
.co-divider { height: 1px; background: var(--border); margin: 20px 0; }

/* ── Attendee card title ── */
.co-att-title {
    font-family: var(--font-head);
    font-size: 13px;
    font-weight: 700;
    color: #fff;
    margin-bottom: 16px;
    display: flex;
    align-items: center;
    gap: 8px;
}
.co-att-title span {
    background: var(--gold);
    color: #000;
    font-size: 10px;
    font-weight: 800;
    padding: 2px 7px;
    border-radius: 99px;
    letter-spacing: 0.5px;
}
</style>

{{-- Banner --}}
<div class="co-banner">
    <div class="container">
        <div class="co-breadcrumb">Home / Checkout</div>
        <h1>Complete Your <span>Order</span></h1>
    </div>
</div>

<section class="co-page">
    <div class="container">

        @if($errors->any())
            <div class="co-alert-err" style="margin-top:32px;">
                <ul>
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        @php
            $buyer = $buyer ?? ['first_name' => '', 'last_name' => '', 'email' => '', 'phone' => '', 'address' => ''];
        @endphp

        {{-- ====================================================
             MODE: EVENT LOCKED
             ==================================================== --}}
        @if(($mode ?? 'open') === 'event_locked')
            @php
                $units = collect($eventSelection['units'] ?? []);
                $requiresApproval = (bool) ($eventSelection['requires_approval'] ?? true);
            @endphp

            <form method="POST" action="{{ route('front.checkout.store') }}">
                @csrf
                <div class="co-layout">

                    {{-- ── Main Column ── --}}
                    <div>
                        <div class="co-label">01 &nbsp; Buyer Information</div>
                        <div class="co-card">
                            <div class="co-row cols-2">
                                <div class="co-field">
                                    <label>First Name</label>
                                    <input name="first_name" placeholder="John" value="{{ old('first_name', $buyer['first_name']) }}" required>
                                </div>
                                <div class="co-field">
                                    <label>Last Name</label>
                                    <input name="last_name" placeholder="Doe" value="{{ old('last_name', $buyer['last_name']) }}" required>
                                </div>
                            </div>
                            <div class="co-row cols-2">
                                <div class="co-field">
                                    <label>Email</label>
                                    <input type="email" name="email" placeholder="you@example.com" value="{{ old('email', $buyer['email']) }}" required>
                                </div>
                                <div class="co-field">
                                    <label>Phone</label>
                                    <input name="phone" placeholder="+1 234 567 890" value="{{ old('phone', $buyer['phone']) }}">
                                </div>
                            </div>
                            <div class="co-field">
                                <label>Address</label>
                                <input name="address" placeholder="Street, City" value="{{ old('address', $buyer['address']) }}">
                            </div>
                        </div>

                        <div class="co-label">02 &nbsp; Attendee Details</div>
                        @foreach($units as $index => $unit)
                            <div class="co-card">
                                <div class="co-att-title">
                                    Ticket {{ $index + 1 }}
                                    <span>{{ $unit['ticket_name'] }}</span>
                                </div>
                                <div class="co-row cols-2">
                                    <div class="co-field">
                                        <label>Name</label>
                                        <input type="text" name="attendees[{{ $index }}][name]" value="{{ old('attendees.'.$index.'.name') }}" required>
                                    </div>
                                    <div class="co-field">
                                        <label>Phone</label>
                                        <input type="text" name="attendees[{{ $index }}][phone]" value="{{ old('attendees.'.$index.'.phone') }}" required>
                                    </div>
                                </div>
                                <div class="co-row cols-3">
                                    <div class="co-field">
                                        <label>Email</label>
                                        <input type="email" name="attendees[{{ $index }}][email]" value="{{ old('attendees.'.$index.'.email') }}" required>
                                    </div>
                                    <div class="co-field">
                                        <label>Gender</label>
                                        <select name="attendees[{{ $index }}][gender]" required>
                                            <option value="">Select</option>
                                            <option value="male"   @selected(old('attendees.'.$index.'.gender') === 'male')>Male</option>
                                            <option value="female" @selected(old('attendees.'.$index.'.gender') === 'female')>Female</option>
                                        </select>
                                    </div>
                                    <div class="co-field">
                                        <label>Social Profile</label>
                                        <input type="text" name="attendees[{{ $index }}][social_profile]" value="{{ old('attendees.'.$index.'.social_profile') }}">
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>

                    {{-- ── Sidebar ── --}}
                    <div class="co-sidebar">
                        <div class="co-label">03 &nbsp; Order Summary</div>
                        <div class="co-summary-card" style="margin-bottom:20px;">
                            <div class="co-summary-head">Items</div>
                            <div class="co-summary-body">
                                @foreach($units as $unit)
                                    <div class="co-summary-item">
                                        <div class="item-name">{{ $unit['event_name'] }}<br><small style="color:var(--muted)">{{ $unit['ticket_name'] }}</small></div>
                                        <div class="item-price">{{ number_format($unit['ticket_price'],2) }}</div>
                                    </div>
                                @endforeach
                            </div>
                            <div class="co-summary-total">
                                <span class="total-label">Total</span>
                                <span class="total-val">{{ number_format($units->sum('ticket_price'), 2) }}</span>
                            </div>
                        </div>

                        <div class="co-label">Payment</div>
                        <div class="co-card">
                            @if($requiresApproval)
                                <div class="co-pay-pending">
                                    🕐 &nbsp; Pending Admin Review
                                </div>
                            @else
                                <div class="co-pay-options">
                                    <label class="co-pay-opt">
                                        <input type="radio" name="payment_method" value="visa" @checked(old('payment_method') === 'visa') required>
                                        <span class="pay-icon">💳</span>
                                        <span class="pay-name">Visa / Card</span>
                                    </label>
                                    <label class="co-pay-opt">
                                        <input type="radio" name="payment_method" value="wallet" @checked(old('payment_method') === 'wallet') required>
                                        <span class="pay-icon">👛</span>
                                        <span class="pay-name">Wallet</span>
                                    </label>
                                </div>
                            @endif
                        </div>

                        <button type="submit" class="co-btn-primary">
                            {{ $requiresApproval ? '📨 Send Order' : '✅ Complete Order' }}
                        </button>
                    </div>
                </div>
            </form>

        {{-- ====================================================
             MODE: OPEN
             ==================================================== --}}
        @else
            @php
                $eventTickets = $eventTickets ?? collect();
                $legacyTickets = $legacyTickets ?? collect();
                $oldItems = old('items', [[]]);
                if (!is_array($oldItems) || empty($oldItems)) $oldItems = [[]];
            @endphp

            <form method="POST" action="{{ route('front.checkout.store') }}" id="open-checkout-form">
                @csrf
                <div class="co-layout">

                    {{-- ── Main Column ── --}}
                    <div>
                        <div class="co-label">01 &nbsp; Buyer Information</div>
                        <div class="co-card">
                            <div class="co-row cols-2">
                                <div class="co-field">
                                    <label>First Name</label>
                                    <input name="first_name" placeholder="John" value="{{ old('first_name', $buyer['first_name']) }}" required>
                                </div>
                                <div class="co-field">
                                    <label>Last Name</label>
                                    <input name="last_name" placeholder="Doe" value="{{ old('last_name', $buyer['last_name']) }}" required>
                                </div>
                            </div>
                            <div class="co-row cols-2">
                                <div class="co-field">
                                    <label>Email</label>
                                    <input type="email" name="email" placeholder="you@example.com" value="{{ old('email', $buyer['email']) }}" required>
                                </div>
                                <div class="co-field">
                                    <label>Phone</label>
                                    <input name="phone" placeholder="+1 234 567 890" value="{{ old('phone', $buyer['phone']) }}">
                                </div>
                            </div>
                            <div class="co-field">
                                <label>Address</label>
                                <input name="address" placeholder="Street, City" value="{{ old('address', $buyer['address']) }}">
                            </div>
                        </div>

                        <div class="co-label">02 &nbsp; Tickets & Order</div>
                        <div id="ticket-rows">
                            @foreach($oldItems as $i => $item)
                                <div class="co-ticket-row" data-row>
                                    <div class="co-ticket-row-header">
                                        <span class="ticket-num">Ticket {{ $i + 1 }}</span>
                                        @if($i > 0)
                                            <button type="button" class="co-btn-remove remove-row">Remove</button>
                                        @else
                                            <div></div>
                                        @endif
                                    </div>
                                    <div class="co-ticket-row-body">
                                        <div class="co-row cols-5-3" style="margin-bottom:12px;">
                                            <div class="co-field" style="margin-bottom:0">
                                                <label>Ticket Type</label>
                                                <select class="ticket-select" name="items[{{ $i }}][ticket_key]" required>
                                                    <option value="">Select a ticket</option>
                                                    @if($eventTickets->isNotEmpty())
                                                        <optgroup label="Event Tickets">
                                                            @foreach($eventTickets as $ticket)
                                                                <option value="event:{{ $ticket->id }}"
                                                                    data-requires-approval="{{ $ticket->event?->requires_booking_approval ? '1' : '0' }}"
                                                                    @selected(($item['ticket_key'] ?? '') === 'event:'.$ticket->id)>
                                                                    {{ $ticket->event?->name ? $ticket->event->name.' — ' : '' }}{{ $ticket->name }} · {{ number_format($ticket->price,2) }}
                                                                </option>
                                                            @endforeach
                                                        </optgroup>
                                                    @endif
                                                    @if($legacyTickets->isNotEmpty())
                                                        <optgroup label="General Tickets">
                                                            @foreach($legacyTickets as $ticket)
                                                                <option value="legacy:{{ $ticket->id }}"
                                                                    data-requires-approval="0"
                                                                    @selected(($item['ticket_key'] ?? '') === 'legacy:'.$ticket->id)>
                                                                    {{ $ticket->name }} · {{ number_format($ticket->price,2) }}
                                                                </option>
                                                            @endforeach
                                                        </optgroup>
                                                    @endif
                                                </select>
                                            </div>
                                            <div class="co-field" style="margin-bottom:0">
                                                <label>Quantity</label>
                                                <input type="number" min="1" name="items[{{ $i }}][quantity]" value="{{ $item['quantity'] ?? 1 }}" required>
                                            </div>
                                        </div>
                                        <div class="co-row cols-2">
                                            <div class="co-field">
                                                <label>Holder Name</label>
                                                <input type="text" name="items[{{ $i }}][holder_name]" value="{{ $item['holder_name'] ?? '' }}" required>
                                            </div>
                                            <div class="co-field">
                                                <label>Holder Email</label>
                                                <input type="email" name="items[{{ $i }}][holder_email]" value="{{ $item['holder_email'] ?? '' }}" required>
                                            </div>
                                        </div>
                                        <div class="co-field" style="max-width:280px;">
                                            <label>Holder Phone</label>
                                            <input type="text" name="items[{{ $i }}][holder_phone]" value="{{ $item['holder_phone'] ?? '' }}">
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>

                        <button type="button" id="add-ticket-row" class="co-btn-add">+ Add Another Ticket</button>
                    </div>

                    {{-- ── Sidebar ── --}}
                    <div class="co-sidebar">
                        <div class="co-label">03 &nbsp; Payment</div>
                        <div class="co-card">
                            <div id="pending-review-box">
                                <div class="co-pay-pending">
                                    🕐 &nbsp; Pending Admin Review
                                </div>
                            </div>
                            <div id="pay-now-box" style="display:none;">
                                <div class="co-pay-options">
                                    <label class="co-pay-opt">
                                        <input class="payment-method-input" type="radio" name="payment_method" value="visa">
                                        <span class="pay-icon">💳</span>
                                        <span class="pay-name">Visa / Card</span>
                                    </label>
                                    <label class="co-pay-opt">
                                        <input class="payment-method-input" type="radio" name="payment_method" value="wallet">
                                        <span class="pay-icon">👛</span>
                                        <span class="pay-name">Wallet</span>
                                    </label>
                                </div>
                            </div>
                            <div class="co-flow-note" id="checkout-flow-note"></div>
                        </div>

                        <button type="submit" class="co-btn-primary" id="submit-order-btn">
                            📨 Send Order
                        </button>
                    </div>
                </div>
            </form>

            {{-- Ticket Row Template --}}
            <template id="ticket-row-template">
                <div class="co-ticket-row" data-row>
                    <div class="co-ticket-row-header">
                        <span class="ticket-num">Ticket</span>
                        <button type="button" class="co-btn-remove remove-row">Remove</button>
                    </div>
                    <div class="co-ticket-row-body">
                        <div class="co-row cols-5-3" style="margin-bottom:12px;">
                            <div class="co-field" style="margin-bottom:0">
                                <label>Ticket Type</label>
                                <select class="ticket-select" data-name="ticket_key" required>
                                    <option value="">Select a ticket</option>
                                    @if($eventTickets->isNotEmpty())
                                        <optgroup label="Event Tickets">
                                            @foreach($eventTickets as $ticket)
                                                <option value="event:{{ $ticket->id }}" data-requires-approval="{{ $ticket->event?->requires_booking_approval ? '1' : '0' }}">{{ $ticket->event?->name ? $ticket->event->name.' — ' : '' }}{{ $ticket->name }} · {{ number_format($ticket->price,2) }}</option>
                                            @endforeach
                                        </optgroup>
                                    @endif
                                    @if($legacyTickets->isNotEmpty())
                                        <optgroup label="General Tickets">
                                            @foreach($legacyTickets as $ticket)
                                                <option value="legacy:{{ $ticket->id }}" data-requires-approval="0">{{ $ticket->name }} · {{ number_format($ticket->price,2) }}</option>
                                            @endforeach
                                        </optgroup>
                                    @endif
                                </select>
                            </div>
                            <div class="co-field" style="margin-bottom:0">
                                <label>Quantity</label>
                                <input type="number" min="1" data-name="quantity" value="1" required>
                            </div>
                        </div>
                        <div class="co-row cols-2">
                            <div class="co-field">
                                <label>Holder Name</label>
                                <input type="text" data-name="holder_name" required>
                            </div>
                            <div class="co-field">
                                <label>Holder Email</label>
                                <input type="email" data-name="holder_email" required>
                            </div>
                        </div>
                        <div class="co-field" style="max-width:280px;">
                            <label>Holder Phone</label>
                            <input type="text" data-name="holder_phone">
                        </div>
                    </div>
                </div>
            </template>

            <script>
                (function () {
                    const rowsContainer = document.getElementById('ticket-rows');
                    const addBtn = document.getElementById('add-ticket-row');
                    const template = document.getElementById('ticket-row-template');
                    const payNowBox = document.getElementById('pay-now-box');
                    const pendingReviewBox = document.getElementById('pending-review-box');
                    const submitOrderBtn = document.getElementById('submit-order-btn');
                    const note = document.getElementById('checkout-flow-note');

                    const reindexRows = () => {
                        rowsContainer.querySelectorAll('[data-row]').forEach((row, index) => {
                            row.querySelector('.ticket-num').textContent = 'Ticket ' + (index + 1);
                            row.querySelectorAll('[data-name]').forEach((field) => {
                                field.name = `items[${index}][${field.dataset.name}]`;
                            });
                        });
                    };

                    const bindRemoveButtons = () => {
                        rowsContainer.querySelectorAll('.remove-row').forEach((btn) => {
                            btn.onclick = () => {
                                if (rowsContainer.querySelectorAll('[data-row]').length <= 1) return;
                                btn.closest('[data-row]').remove();
                                reindexRows();
                                updateCheckoutFlow();
                            };
                        });
                    };

                    const updateCheckoutFlow = () => {
                        const selectedOptions = [...rowsContainer.querySelectorAll('.ticket-select')]
                            .map((s) => s.options[s.selectedIndex])
                            .filter(Boolean);
                        const requiresApproval = selectedOptions.some((o) => o.dataset.requiresApproval === '1');
                        payNowBox.style.display = requiresApproval ? 'none' : 'block';
                        pendingReviewBox.style.display = requiresApproval ? 'block' : 'none';
                        document.querySelectorAll('.payment-method-input').forEach((input) => {
                            input.required = !requiresApproval;
                            if (requiresApproval) input.checked = false;
                        });
                        submitOrderBtn.textContent = requiresApproval ? '📨 Send Order' : '✅ Complete Order';
                        note.textContent = requiresApproval
                            ? 'This order requires admin approval before payment.'
                            : 'You can complete payment immediately at checkout.';
                    };

                    addBtn.addEventListener('click', () => {
                        rowsContainer.appendChild(template.content.cloneNode(true));
                        reindexRows();
                        bindRemoveButtons();
                        rowsContainer.querySelectorAll('.ticket-select').forEach((s) => { s.onchange = updateCheckoutFlow; });
                        updateCheckoutFlow();
                    });

                    reindexRows();
                    bindRemoveButtons();
                    rowsContainer.querySelectorAll('.ticket-select').forEach((s) => { s.onchange = updateCheckoutFlow; });
                    updateCheckoutFlow();
                })();
            </script>
        @endif

    </div>
</section>

@endsection
