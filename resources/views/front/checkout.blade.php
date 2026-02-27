@extends('front.layout.master')

@section('content')

{{-- ============================================================
     CHECKOUT PAGE — Premium Dark UI  |  Attendee Step-by-Step
     ============================================================ --}}

<style>
@import url('https://fonts.googleapis.com/css2?family=Syne:wght@600;700;800&family=DM+Sans:wght@300;400;500&display=swap');

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
    --green:     #22c55e;
    --radius:    12px;
    --font-head: 'Syne', sans-serif;
    --font-body: 'DM Sans', sans-serif;
}

.co-page { background: var(--bg); min-height: 100vh; font-family: var(--font-body); color: var(--text); padding-bottom: 80px; }

/* Banner */
.co-banner {
    background: linear-gradient(135deg, #0b0b10 0%, #12120a 100%);
    border-bottom: 1px solid var(--border);
    padding: 28px 0 24px;
    position: relative; overflow: hidden;
}
.co-banner::before {
    content: ''; position: absolute; inset: 0;
    background: radial-gradient(ellipse 60% 80% at 15% 50%, rgba(245,184,0,0.06) 0%, transparent 70%);
    pointer-events: none;
}
.co-breadcrumb { font-family: var(--font-head); font-size: 11px; letter-spacing: 3px; text-transform: uppercase; color: var(--muted); margin-bottom: 6px; }
.co-banner h1 { font-family: var(--font-head); font-size: clamp(22px, 4vw, 36px); font-weight: 800; color: #fff; margin: 0; letter-spacing: -0.5px; }
.co-banner h1 span { color: var(--gold); }

/* Layout */
.co-layout { display: grid; grid-template-columns: 1fr 420px; gap: 28px; padding: 40px 0 80px; align-items: start; }
@media (max-width: 960px) { .co-layout { grid-template-columns: 1fr; } .co-sidebar { order: -1; } }

/* Section label */
.co-label { display: flex; align-items: center; gap: 10px; font-family: var(--font-head); font-size: 11px; letter-spacing: 2.5px; text-transform: uppercase; color: var(--gold); margin-bottom: 18px; }
.co-label::after { content: ''; flex: 1; height: 1px; background: var(--border); }

/* Card */
.co-card { background: var(--surface); border: 1px solid var(--border); border-radius: var(--radius); padding: 24px; margin-bottom: 20px; transition: border-color 0.2s; }
.co-card:hover { border-color: rgba(255,255,255,0.12); }

/* Form controls */
.co-field { margin-bottom: 14px; }
.co-field:last-child { margin-bottom: 0; }
.co-field label { display: block; font-size: 11px; font-weight: 500; letter-spacing: 0.8px; text-transform: uppercase; color: var(--muted); margin-bottom: 6px; }
.co-field input, .co-field select { width: 100%; background: var(--surface2); border: 1px solid var(--border); border-radius: 8px; color: var(--text); font-family: var(--font-body); font-size: 14px; padding: 11px 14px; outline: none; transition: border-color 0.2s, box-shadow 0.2s; -webkit-appearance: none; }
.co-field input::placeholder { color: var(--muted); }
.co-field input:focus, .co-field select:focus { border-color: var(--gold-dim); box-shadow: 0 0 0 3px rgba(245,184,0,0.1); }
.co-field select { cursor: pointer; } .co-field select option { background: #1a1a22; }
.co-row { display: grid; gap: 12px; }
.co-row.cols-2 { grid-template-columns: 1fr 1fr; }
.co-row.cols-3 { grid-template-columns: 1fr 1fr 1fr; }
.co-row.cols-5-3 { grid-template-columns: 5fr 3fr; }
@media (max-width: 640px) { .co-row.cols-2, .co-row.cols-3, .co-row.cols-5-3 { grid-template-columns: 1fr; } }

/* Payment */
.co-pay-options { display: flex; flex-direction: column; gap: 10px; }
.co-pay-opt { display: flex; align-items: center; gap: 12px; background: var(--surface2); border: 1px solid var(--border); border-radius: 8px; padding: 13px 16px; cursor: pointer; transition: border-color 0.2s, background 0.2s; }
.co-pay-opt:hover { border-color: var(--border-h); background: rgba(245,184,0,0.04); }
.co-pay-opt input[type=radio] { accent-color: var(--gold); width: 16px; height: 16px; flex-shrink: 0; }
.co-pay-opt .pay-icon { font-size: 18px; }
.co-pay-opt .pay-name { font-size: 14px; font-weight: 500; flex: 1; }
.co-pay-pending { background: rgba(245,184,0,0.06); border: 1px dashed rgba(245,184,0,0.3); border-radius: 8px; padding: 13px 16px; display: flex; align-items: center; gap: 10px; font-size: 13px; color: var(--gold); }
.co-flow-note { font-size: 12px; color: var(--muted); margin-top: 10px; line-height: 1.5; min-height: 16px; }

/* Buttons */
.co-btn-primary { display: flex; align-items: center; justify-content: center; gap: 8px; width: 100%; background: var(--gold); color: #000; font-family: var(--font-head); font-size: 14px; font-weight: 700; letter-spacing: 0.5px; border: none; border-radius: 8px; padding: 15px 24px; cursor: pointer; transition: background 0.2s, transform 0.1s; }
.co-btn-primary:hover { background: #ffc820; }
.co-btn-primary:active { transform: scale(0.99); }
.co-btn-add { display: flex; align-items: center; justify-content: center; gap: 8px; width: 100%; background: transparent; color: var(--gold); font-family: var(--font-head); font-size: 13px; font-weight: 700; letter-spacing: 0.5px; border: 1px dashed rgba(245,184,0,0.35); border-radius: 8px; padding: 13px 24px; cursor: pointer; margin-bottom: 16px; transition: background 0.2s, border-color 0.2s; }
.co-btn-add:hover { background: rgba(245,184,0,0.07); border-color: rgba(245,184,0,0.6); }
.co-btn-remove { background: transparent; color: var(--red); border: 1px solid rgba(232,68,90,0.25); border-radius: 6px; padding: 8px 14px; font-size: 12px; font-weight: 500; cursor: pointer; transition: background 0.2s, border-color 0.2s; white-space: nowrap; }
.co-btn-remove:hover { background: rgba(232,68,90,0.1); border-color: rgba(232,68,90,0.5); }

/* Alert */
.co-alert-err { background: rgba(232,68,90,0.08); border: 1px solid rgba(232,68,90,0.3); border-radius: 8px; padding: 14px 18px; margin-bottom: 24px; font-size: 13px; color: #f0849a; }
.co-alert-err ul { margin: 0; padding-left: 18px; }

/* Attendee badge title */
.co-att-title { font-family: var(--font-head); font-size: 13px; font-weight: 700; color: #fff; margin-bottom: 16px; display: flex; align-items: center; gap: 8px; }
.co-att-title .att-tag { background: var(--gold); color: #000; font-size: 10px; font-weight: 800; padding: 2px 7px; border-radius: 99px; letter-spacing: 0.5px; }

/* ════════════════ ATTENDEE STEPPER ════════════════ */
.att-stepper-top {
    display: flex; align-items: center; justify-content: space-between;
    margin-bottom: 22px; gap: 12px;
}
.att-stepper-label { font-family: var(--font-head); font-size: 11px; font-weight: 700; letter-spacing: 2px; text-transform: uppercase; color: var(--gold); white-space: nowrap; }
.att-counter { font-size: 12px; color: var(--muted); font-family: var(--font-head); white-space: nowrap; }

/* Dots strip */
.att-dots { display: flex; align-items: center; }
.att-dot {
    width: 26px; height: 26px; border-radius: 50%; flex-shrink: 0;
    background: var(--surface2); border: 2px solid var(--border);
    display: flex; align-items: center; justify-content: center;
    font-family: var(--font-head); font-size: 11px; font-weight: 700;
    color: var(--muted); cursor: pointer; transition: all 0.25s; position: relative; z-index: 1;
}
.att-dot.active { background: var(--gold); border-color: var(--gold); color: #000; box-shadow: 0 0 0 3px rgba(245,184,0,0.18); }
.att-dot.done   { background: rgba(34,197,94,0.1); border-color: var(--green); color: var(--green); }
.att-line { width: 16px; height: 2px; background: var(--border); flex-shrink: 0; transition: background 0.25s; }
.att-line.done { background: var(--green); }

/* Panels */
.att-panel { display: none; }
.att-panel.active { display: block; animation: attIn 0.3s ease; }
.att-panel.active.back { animation: attBack 0.3s ease; }
@keyframes attIn   { from { opacity:0; transform:translateX(12px);  } to { opacity:1; transform:translateX(0); } }
@keyframes attBack { from { opacity:0; transform:translateX(-12px); } to { opacity:1; transform:translateX(0); } }

/* Nav */
.att-nav { display: flex; align-items: center; justify-content: space-between; gap: 10px; margin-top: 20px; border-top: 1px solid var(--border); padding-top: 18px; }
.att-btn-back { background: transparent; color: var(--muted); border: 1px solid var(--border); border-radius: 8px; padding: 9px 16px; font-family: var(--font-head); font-size: 12px; font-weight: 700; cursor: pointer; transition: color 0.2s, border-color 0.2s; }
.att-btn-back:hover:not(:disabled) { color: var(--text); border-color: rgba(255,255,255,0.2); }
.att-btn-back:disabled { opacity: 0.3; cursor: default; }
.att-btn-next { background: rgba(245,184,0,0.1); color: var(--gold); border: 1px solid rgba(245,184,0,0.3); border-radius: 8px; padding: 9px 20px; font-family: var(--font-head); font-size: 12px; font-weight: 700; cursor: pointer; transition: background 0.2s, border-color 0.2s; }
.att-btn-next:hover { background: rgba(245,184,0,0.18); border-color: rgba(245,184,0,0.6); }
.att-btn-next.done-state { background: rgba(34,197,94,0.1); color: var(--green); border-color: rgba(34,197,94,0.35); }

/* Sidebar summary */
.co-sidebar { position: sticky; top: 24px; }
.co-summary-card { background: var(--surface); border: 1px solid var(--border); border-radius: var(--radius); overflow: hidden; }
.co-summary-head { background: var(--surface2); border-bottom: 1px solid var(--border); padding: 16px 22px; font-family: var(--font-head); font-size: 12px; font-weight: 700; letter-spacing: 2px; text-transform: uppercase; color: var(--muted); }
.co-summary-body { padding: 20px 22px; }
.co-summary-item { display: flex; justify-content: space-between; align-items: flex-start; gap: 12px; padding: 10px 0; border-bottom: 1px solid var(--border); font-size: 13px; }
.co-summary-item:last-of-type { border-bottom: none; }
.co-summary-item .item-name { color: var(--text); flex: 1; }
.co-summary-item .item-price { color: var(--gold); font-weight: 500; white-space: nowrap; }
.co-summary-total { display: flex; justify-content: space-between; padding: 16px 22px; border-top: 1px solid var(--border); font-family: var(--font-head); font-size: 16px; font-weight: 800; }
.co-summary-total .total-label { color: var(--muted); }
.co-summary-total .total-val { color: var(--gold); }

/* Open-mode ticket rows */
.co-ticket-row { background: var(--surface); border: 1px solid var(--border); border-radius: var(--radius); margin-bottom: 14px; overflow: hidden; }
.co-ticket-row-header { display: flex; align-items: center; justify-content: space-between; padding: 14px 20px; background: var(--surface2); border-bottom: 1px solid var(--border); }
.co-ticket-row-header .ticket-num { font-family: var(--font-head); font-size: 12px; font-weight: 700; letter-spacing: 1.5px; text-transform: uppercase; color: var(--gold); }
.co-ticket-row-body { padding: 18px 20px; }
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
                <ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
            </div>
        @endif

        @php $buyer = $buyer ?? ['first_name'=>'','last_name'=>'','email'=>'','phone'=>'']; @endphp

        {{-- ================================================================
             MODE: EVENT LOCKED
             Left  → Buyer Info + Order Review + Payment + Submit
             Right → Attendee Details (step-by-step per ticket)
             ================================================================ --}}
        @if(($mode ?? 'open') === 'event_locked')
            @php
                $units = collect($eventSelection['units'] ?? []);
                $requiresApproval = (bool)($eventSelection['requires_approval'] ?? true);
                $unitCount = $units->count();
            @endphp

            <form method="POST" action="{{ route('front.checkout.store') }}">
                @csrf
                <div class="co-layout">

                    {{-- ── LEFT: Buyer Info + Attendee Stepper ── --}}
                    <div>
                        <div class="co-label">01 &nbsp; Buyer Information</div>
                        <div class="co-card">
                            <div class="co-row cols-2">
                                <div class="co-field"><label>First Name</label><input name="first_name" placeholder="John" value="{{ old('first_name',$buyer['first_name']) }}" required></div>
                                <div class="co-field"><label>Last Name</label> <input name="last_name"  placeholder="Doe"  value="{{ old('last_name', $buyer['last_name'])  }}" required></div>
                            </div>
                            <div class="co-row cols-2">
                                <div class="co-field"><label>Email</label> <input type="email" name="email" placeholder="you@example.com" value="{{ old('email',$buyer['email']) }}" required></div>
                                <div class="co-field"><label>Phone</label> <input name="phone" placeholder="+1 234 567 890"   value="{{ old('phone',$buyer['phone']) }}"></div>
                            </div>
                        </div>

                        {{-- Attendee Stepper --}}
                        @if($units->count() > 0)
                        <div class="co-label">02 &nbsp; Attendee Details</div>
                        <div class="co-card">

                            <div class="att-stepper-top">
                                <span class="att-stepper-label">Attendees</span>
                                <div style="display:flex;align-items:center;gap:10px;">
                                    <span class="att-counter" id="att-counter">1 / {{ $unitCount }}</span>
                                    @if($unitCount > 1)
                                    <div class="att-dots" id="att-dots">
                                        @foreach($units as $idx => $unit)
                                            @if($idx > 0)<div class="att-line" data-line="{{ $idx }}"></div>@endif
                                            <div class="att-dot {{ $idx===0?'active':'' }}" data-dot="{{ $idx }}" title="{{ $unit['ticket_name'] }}">{{ $idx+1 }}</div>
                                        @endforeach
                                    </div>
                                    @endif
                                </div>
                            </div>

                            @foreach($units as $index => $unit)
                                <div class="att-panel {{ $index===0?'active':'' }}" data-att="{{ $index }}">
                                    <div class="co-att-title">
                                        {{ $unit['event_name'] }}
                                        <span class="att-tag">{{ $unit['ticket_name'] }}</span>
                                    </div>
                                    <div class="co-row cols-2">
                                        <div class="co-field"><label>Name</label> <input type="text" name="attendees[{{ $index }}][name]"  value="{{ old('attendees.'.$index.'.name') }}"  required></div>
                                        <div class="co-field"><label>Phone</label><input type="text" name="attendees[{{ $index }}][phone]" value="{{ old('attendees.'.$index.'.phone') }}" required></div>
                                    </div>
                                    <div class="co-row cols-2">
                                        <div class="co-field"><label>Email</label><input type="email" name="attendees[{{ $index }}][email]" value="{{ old('attendees.'.$index.'.email') }}" required></div>
                                        <div class="co-field"><label>Gender</label>
                                            <select name="attendees[{{ $index }}][gender]" required>
                                                <option value="">Select</option>
                                                <option value="male"   @selected(old('attendees.'.$index.'.gender')==='male')>Male</option>
                                                <option value="female" @selected(old('attendees.'.$index.'.gender')==='female')>Female</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="co-field"><label>Social Profile</label><input type="text" name="attendees[{{ $index }}][social_profile]" value="{{ old('attendees.'.$index.'.social_profile') }}"></div>
                                </div>
                            @endforeach

                            @if($unitCount > 1)
                            <div class="att-nav">
                                <button type="button" class="att-btn-back" id="att-back" disabled>← Back</button>
                                <button type="button" class="att-btn-next" id="att-next">Next →</button>
                            </div>
                            @endif

                        </div>
                        @endif
                    </div>

                    {{-- ── RIGHT: Order Review + Payment + Submit ── --}}
                    <div class="co-sidebar">
                        <div class="co-label">Order Review</div>
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
                                <span class="total-val">{{ number_format($units->sum('ticket_price'),2) }}</span>
                            </div>
                        </div>

                        <div class="co-label">Payment Method</div>
                        <div class="co-card">
                            @if($requiresApproval)
                                <div class="co-pay-pending">🕐 &nbsp; Need Approval</div>
                            @else
                                <div class="co-pay-options">
                                    <label class="co-pay-opt"><input type="radio" name="payment_method" value="visa"   @checked(old('payment_method')==='visa')   required><span class="pay-icon">💳</span><span class="pay-name">Visa / Card</span></label>
                                    <label class="co-pay-opt"><input type="radio" name="payment_method" value="wallet" @checked(old('payment_method')==='wallet') required><span class="pay-icon">👛</span><span class="pay-name">Wallet</span></label>
                                </div>
                            @endif
                        </div>

                        <button type="submit" class="co-btn-primary">
                            {{ $requiresApproval ? '📨 Send Order' : '✅ Complete Order' }}
                        </button>
                    </div>

                </div>
            </form>

            @if($unitCount > 1)
            <script>
            (function(){
                const total   = {{ $unitCount }};
                let cur       = 0;
                const panels  = document.querySelectorAll('[data-att]');
                const dots    = document.querySelectorAll('[data-dot]');
                const lines   = document.querySelectorAll('[data-line]');
                const counter = document.getElementById('att-counter');
                const btnBack = document.getElementById('att-back');
                const btnNext = document.getElementById('att-next');

                function goTo(n, back=false){
                    panels[cur].classList.remove('active','back');
                    void panels[n].offsetWidth; // reflow
                    if(back) panels[n].classList.add('back');
                    panels[n].classList.add('active');
                    cur = n; render();
                }
                function render(){
                    counter.textContent = (cur+1)+' / '+total;
                    dots.forEach(d=>{
                        const i=parseInt(d.dataset.dot);
                        d.classList.toggle('active', i===cur);
                        d.classList.toggle('done',   i<cur);
                    });
                    lines.forEach(l=>{
                        l.classList.toggle('done', parseInt(l.dataset.line)<=cur);
                    });
                    btnBack.disabled = cur===0;
                    const last = cur===total-1;
                    btnNext.textContent = last ? '✓ All Done' : 'Next →';
                    btnNext.classList.toggle('done-state', last);
                }
                function validate(){
                    let ok=true;
                    panels[cur].querySelectorAll('[required]').forEach(el=>{
                        if(!el.value.trim()){
                            el.style.borderColor='var(--red)';
                            el.addEventListener('input',()=>el.style.borderColor='',{once:true});
                            ok=false;
                        }
                    });
                    return ok;
                }
                btnNext.addEventListener('click',()=>{ if(cur<total-1 && validate()) goTo(cur+1); });
                btnBack.addEventListener('click',()=>{ if(cur>0) goTo(cur-1,true); });
                dots.forEach(d=>d.addEventListener('click',()=>{
                    const n=parseInt(d.dataset.dot);
                    if(n===cur) return;
                    if(n>cur && !validate()) return;
                    goTo(n, n<cur);
                }));
                render();
            })();
            </script>
            @endif


        {{-- ================================================================
             MODE: OPEN  (left = buyer + payment, right = ticket rows)
             ================================================================ --}}
        @else
            @php
                $eventTickets  = $eventTickets  ?? collect();
                $legacyTickets = $legacyTickets ?? collect();
                $oldItems      = old('items', [[]]);
                if(!is_array($oldItems)||empty($oldItems)) $oldItems=[[]];
            @endphp

            <form method="POST" action="{{ route('front.checkout.store') }}" id="open-checkout-form">
                @csrf
                <div class="co-layout">

                    {{-- LEFT: Buyer + Payment --}}
                    <div>
                        <div class="co-label">01 &nbsp; Buyer Information</div>
                        <div class="co-card">
                            <div class="co-row cols-2">
                                <div class="co-field"><label>First Name</label><input name="first_name" placeholder="John" value="{{ old('first_name',$buyer['first_name']) }}" required></div>
                                <div class="co-field"><label>Last Name</label> <input name="last_name"  placeholder="Doe"  value="{{ old('last_name', $buyer['last_name'])  }}" required></div>
                            </div>
                            <div class="co-row cols-2">
                                <div class="co-field"><label>Email</label><input type="email" name="email" placeholder="you@example.com" value="{{ old('email',$buyer['email']) }}" required></div>
                                <div class="co-field"><label>Phone</label><input name="phone" placeholder="+1 234 567 890" value="{{ old('phone',$buyer['phone']) }}"></div>
                            </div>
                        </div>

                        <div class="co-label">Payment Method</div>
                        <div class="co-card">
                            <div id="pending-review-box">
                                <div class="co-pay-pending">🕐 &nbsp; Need Approval</div>
                            </div>
                            <div id="pay-now-box" style="display:none;">
                                <div class="co-pay-options">
                                    <label class="co-pay-opt"><input class="payment-method-input" type="radio" name="payment_method" value="visa">  <span class="pay-icon">💳</span><span class="pay-name">Visa / Card</span></label>
                                    <label class="co-pay-opt"><input class="payment-method-input" type="radio" name="payment_method" value="wallet"><span class="pay-icon">👛</span><span class="pay-name">Wallet</span></label>
                                </div>
                            </div>
                            <div class="co-flow-note" id="checkout-flow-note"></div>
                        </div>

                        <button type="submit" class="co-btn-primary" id="submit-order-btn">📨 Send Order</button>
                    </div>

                    {{-- RIGHT: Tickets --}}
                    <div>
                        <div class="co-label">02 &nbsp; Tickets & Order Review</div>
                        <div id="ticket-rows">
                            @foreach($oldItems as $i => $item)
                                <div class="co-ticket-row" data-row>
                                    <div class="co-ticket-row-header">
                                        <span class="ticket-num">Ticket {{ $i+1 }}</span>
                                        @if($i>0)<button type="button" class="co-btn-remove remove-row">Remove</button>@else<div></div>@endif
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
                                                                <option value="event:{{ $ticket->id }}" data-requires-approval="{{ $ticket->event?->requires_booking_approval?'1':'0' }}" @selected(($item['ticket_key']??'')==='event:'.$ticket->id)>{{ $ticket->event?->name?$ticket->event->name.' — ':'' }}{{ $ticket->name }} · {{ number_format($ticket->price,2) }}</option>
                                                            @endforeach
                                                        </optgroup>
                                                    @endif
                                                    @if($legacyTickets->isNotEmpty())
                                                        <optgroup label="General Tickets">
                                                            @foreach($legacyTickets as $ticket)
                                                                <option value="legacy:{{ $ticket->id }}" data-requires-approval="0" @selected(($item['ticket_key']??'')==='legacy:'.$ticket->id)>{{ $ticket->name }} · {{ number_format($ticket->price,2) }}</option>
                                                            @endforeach
                                                        </optgroup>
                                                    @endif
                                                </select>
                                            </div>
                                            <div class="co-field" style="margin-bottom:0"><label>Quantity</label><input type="number" min="1" name="items[{{ $i }}][quantity]" value="{{ $item['quantity']??1 }}" required></div>
                                        </div>
                                        <div class="co-row cols-2">
                                            <div class="co-field"><label>Holder Name</label> <input type="text"  name="items[{{ $i }}][holder_name]"  value="{{ $item['holder_name']??''  }}" required></div>
                                            <div class="co-field"><label>Holder Email</label><input type="email" name="items[{{ $i }}][holder_email]" value="{{ $item['holder_email']??'' }}" required></div>
                                        </div>
                                        <div class="co-field" style="max-width:280px;"><label>Holder Phone</label><input type="text" name="items[{{ $i }}][holder_phone]" value="{{ $item['holder_phone']??'' }}"></div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                        <button type="button" id="add-ticket-row" class="co-btn-add">+ Add Another Ticket</button>
                    </div>

                </div>
            </form>

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
                                                <option value="event:{{ $ticket->id }}" data-requires-approval="{{ $ticket->event?->requires_booking_approval?'1':'0' }}">{{ $ticket->event?->name?$ticket->event->name.' — ':'' }}{{ $ticket->name }} · {{ number_format($ticket->price,2) }}</option>
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
                            <div class="co-field" style="margin-bottom:0"><label>Quantity</label><input type="number" min="1" data-name="quantity" value="1" required></div>
                        </div>
                        <div class="co-row cols-2">
                            <div class="co-field"><label>Holder Name</label> <input type="text"  data-name="holder_name"  required></div>
                            <div class="co-field"><label>Holder Email</label><input type="email" data-name="holder_email" required></div>
                        </div>
                        <div class="co-field" style="max-width:280px;"><label>Holder Phone</label><input type="text" data-name="holder_phone"></div>
                    </div>
                </div>
            </template>

            <script>
            (function(){
                const rows   = document.getElementById('ticket-rows');
                const addBtn = document.getElementById('add-ticket-row');
                const tpl    = document.getElementById('ticket-row-template');
                const payBox = document.getElementById('pay-now-box');
                const penBox = document.getElementById('pending-review-box');
                const subBtn = document.getElementById('submit-order-btn');
                const note   = document.getElementById('checkout-flow-note');

                const reindex = () => rows.querySelectorAll('[data-row]').forEach((r,i)=>{
                    r.querySelector('.ticket-num').textContent='Ticket '+(i+1);
                    r.querySelectorAll('[data-name]').forEach(f=>f.name=`items[${i}][${f.dataset.name}]`);
                });
                const bindRemove = () => rows.querySelectorAll('.remove-row').forEach(b=>{
                    b.onclick=()=>{ if(rows.querySelectorAll('[data-row]').length<=1) return; b.closest('[data-row]').remove(); reindex(); flow(); };
                });
                const flow = () => {
                    const req=[...rows.querySelectorAll('.ticket-select')].some(s=>s.options[s.selectedIndex]?.dataset.requiresApproval==='1');
                    payBox.style.display=req?'none':'block';
                    penBox.style.display=req?'block':'none';
                    document.querySelectorAll('.payment-method-input').forEach(i=>{i.required=!req;if(req)i.checked=false;});
                    subBtn.textContent=req?'📨 Send Order':'✅ Complete Order';
                    note.textContent=req?'This order requires admin approval before payment.':'You can complete payment immediately.';
                };
                addBtn.addEventListener('click',()=>{
                    rows.appendChild(tpl.content.cloneNode(true));
                    reindex(); bindRemove();
                    rows.querySelectorAll('.ticket-select').forEach(s=>s.onchange=flow);
                    flow();
                });
                reindex(); bindRemove();
                rows.querySelectorAll('.ticket-select').forEach(s=>s.onchange=flow);
                flow();
            })();
            </script>
        @endif

    </div>
</section>

@endsection
