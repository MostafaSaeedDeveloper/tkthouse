@extends('admin.master')

@section('content')
@php
  $displayOrderNumber = preg_replace('/\D+/', '', (string) $order->order_number) ?: $order->order_number;

  $statusOptions = [
    'pending_approval' => 'Pending Approval',
    'pending_payment' => 'Pending Payment',
    'on_hold' => 'On Hold',
    'complete' => 'Complete',
    'canceled' => 'Canceled',
    'rejected' => 'Rejected',
  ];

  $paymentMethodOptions = [
    'cash'          => 'Cash',
    'card'          => 'Card',
    'vodafone_cash' => 'Vodafone Cash',
    'instapay'      => 'InstaPay',
    'bank_transfer' => 'Bank Transfer',
  ];

  $paymentStatusOptions = [
    'unpaid'           => 'Unpaid',
    'pending'          => 'Pending',
    'paid'             => 'Paid',
    'refunded'         => 'Refunded',
    'partially_refunded' => 'Partially Refunded',
  ];

  $paymentLink = $order->payment_link_token ? route('front.orders.payment', ['order' => $order, 'token' => $order->payment_link_token]) : null;
@endphp

<style>
/* ── Base ─────────────────────────────────────────────── */
.oe-wrap { padding: 12px 0 80px; }

/* ── Header ───────────────────────────────────────────── */
.oe-head {
  display: flex; justify-content: space-between; align-items: flex-start;
  gap: 16px; flex-wrap: wrap; margin-bottom: 28px;
}
.oe-head-title { font-family: 'Syne', sans-serif; font-weight: 800; font-size: 26px; color: #fff; margin: 0; letter-spacing: -0.4px; }
.oe-head-title span { color: #f5b800; }
.oe-head-sub { margin: 5px 0 0; color: #5e5e72; font-size: 13px; }
.oe-head-actions { display: flex; gap: 10px; flex-wrap: wrap; align-items: center; }

/* ── Buttons ──────────────────────────────────────────── */
.oe-btn {
  display: inline-flex; align-items: center; gap: 8px;
  padding: 10px 18px; border-radius: 9px; font-size: 13px; font-weight: 600;
  text-decoration: none; border: 1px solid rgba(255,255,255,.08);
  background: #15151b; color: #bbb; cursor: pointer; transition: all .18s;
}
.oe-btn:hover { border-color: rgba(255,255,255,.18); color: #fff; background: #1e1e27; }
.oe-btn-primary {
  background: #f5b800; border-color: #f5b800; color: #0d0d10;
  font-family: 'Syne', sans-serif; font-weight: 800; letter-spacing: 0.3px;
}
.oe-btn-primary:hover { background: #ffc107; border-color: #ffc107; color: #0d0d10; }
.oe-btn-primary i { font-size: 12px; }

/* ── Layout Grid ──────────────────────────────────────── */
.oe-grid { display: grid; grid-template-columns: 1fr 320px; gap: 20px; align-items: start; }
@media (max-width: 960px) { .oe-grid { grid-template-columns: 1fr; } }

/* ── Card ─────────────────────────────────────────────── */
.oe-card {
  background: #0d0d10;
  border: 1px solid rgba(255,255,255,.07);
  border-radius: 14px; overflow: hidden; margin-bottom: 20px;
}
.oe-card-head {
  display: flex; align-items: center; justify-content: space-between;
  padding: 14px 20px; border-bottom: 1px solid rgba(255,255,255,.06); gap: 10px;
}
.oe-card-title {
  font-family: 'Syne', sans-serif; font-size: 11px; font-weight: 700;
  letter-spacing: 2px; text-transform: uppercase; color: #f5b800;
  display: flex; align-items: center; gap: 8px;
}
.oe-card-title::before {
  content: ''; width: 3px; height: 13px; background: #f5b800;
  border-radius: 2px; flex-shrink: 0;
}
.oe-card-body { padding: 20px; }

/* ── Invoice Table ────────────────────────────────────── */
.oe-invoice-table { width: 100%; border-collapse: collapse; }
.oe-invoice-table thead tr {
  border-bottom: 1px solid rgba(255,255,255,.08);
}
.oe-invoice-table thead th {
  font-size: 10px; font-weight: 700; letter-spacing: 1.2px; text-transform: uppercase;
  color: #5e5e72; padding: 0 12px 12px; text-align: left;
}
.oe-invoice-table thead th:last-child { text-align: right; }
.oe-invoice-table tbody tr {
  border-bottom: 1px solid rgba(255,255,255,.05);
  transition: background .15s;
}
.oe-invoice-table tbody tr:last-child { border-bottom: none; }
.oe-invoice-table tbody tr:hover { background: rgba(255,255,255,.02); }
.oe-invoice-table tbody td {
  padding: 14px 12px; vertical-align: middle; font-size: 13.5px;
}
.oe-inv-name { color: #fff; font-weight: 700; font-size: 14px; }
.oe-inv-meta { margin-top: 5px; display: flex; flex-wrap: wrap; gap: 8px; }
.oe-inv-meta-chip {
  display: inline-flex; align-items: center; gap: 5px;
  font-size: 11px; color: #9ba0bd;
  background: rgba(255,255,255,.04); border: 1px solid rgba(255,255,255,.07);
  border-radius: 5px; padding: 2px 8px;
}
.oe-inv-meta-chip i { font-size: 9px; color: #5e5e72; }
.oe-inv-qty {
  display: inline-block;
  min-width: 40px; padding: 4px 10px;
  background: rgba(245,184,0,.1); border: 1px solid rgba(245,184,0,.2);
  color: #f5b800; font-weight: 700; font-size: 13px;
  border-radius: 6px; text-align: center; white-space: nowrap;
}
.oe-inv-price { color: #dddde8; font-weight: 600; text-align: right; font-size: 14px; }
.oe-inv-unit  { display: block; font-size: 11px; color: #5e5e72; text-align: right; margin-top: 2px; }

/* ── Form Fields ──────────────────────────────────────── */
.oe-field { margin-bottom: 14px; }
.oe-field:last-child { margin-bottom: 0; }
.oe-label {
  display: block; font-size: 11px; font-weight: 600; letter-spacing: 0.6px;
  text-transform: uppercase; color: #5e5e72; margin-bottom: 7px;
}
.oe-input, .oe-select, .oe-textarea {
  width: 100%; background: #0d0d10; border: 1px solid rgba(255,255,255,.1);
  border-radius: 8px; color: #dddde8; padding: 9px 12px; font-size: 13.5px;
  outline: none; transition: border-color .18s, box-shadow .18s;
  box-sizing: border-box;
}
.oe-select { cursor: pointer; appearance: none; background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 24 24' fill='none' stroke='%235e5e72' stroke-width='2.5'%3E%3Cpath d='M6 9l6 6 6-6'/%3E%3C/svg%3E"); background-repeat: no-repeat; background-position: right 12px center; padding-right: 36px; }
.oe-input:focus, .oe-select:focus, .oe-textarea:focus {
  border-color: rgba(245,184,0,.5); box-shadow: 0 0 0 3px rgba(245,184,0,.08);
}
.oe-input::placeholder, .oe-textarea::placeholder { color: #3e3e52; }
.oe-textarea { min-height: 80px; resize: vertical; }

/* ── Grid helpers ─────────────────────────────────────── */
.oe-row-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
.oe-row-3 { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 12px; }
@media (max-width: 600px) { .oe-row-2, .oe-row-3 { grid-template-columns: 1fr; } }

/* ── Divider ──────────────────────────────────────────── */
.oe-divider { border: none; border-top: 1px solid rgba(255,255,255,.06); margin: 16px 0; }

/* ── Checkbox ─────────────────────────────────────────── */
.oe-check-label {
  display: flex; align-items: center; gap: 10px; cursor: pointer;
  color: #bbb; font-size: 13.5px; padding: 10px 14px;
  background: #15151b; border: 1px solid rgba(255,255,255,.07);
  border-radius: 9px; transition: border-color .18s;
}
.oe-check-label:hover { border-color: rgba(245,184,0,.2); }
.oe-check-label input[type="checkbox"] {
  width: 16px; height: 16px; accent-color: #f5b800; flex-shrink: 0;
}

/* ── Pricing card ─────────────────────────────────────── */
.oe-pricing-row {
  display: flex; align-items: center; justify-content: space-between;
  padding: 11px 0; border-bottom: 1px solid rgba(255,255,255,.05);
  font-size: 13px;
}
.oe-pricing-row:last-child { border-bottom: none; }
.oe-pricing-label { color: #5e5e72; }
.oe-pricing-val { color: #dddde8; font-weight: 600; }
.oe-pricing-val.highlight { color: #f5b800; font-size: 15px; font-family: 'Syne', sans-serif; }

/* ── Status badge preview ─────────────────────────────── */
.oe-status-preview {
  display: inline-flex; align-items: center; gap: 6px;
  font-family: 'Syne', sans-serif; font-size: 11px; font-weight: 700;
  letter-spacing: 0.5px; padding: 4px 12px; border-radius: 99px;
}
.status-pending_approval, .status-pending_payment { color: #f5b800; background: rgba(245,184,0,.12); border: 1px solid rgba(245,184,0,.25); }
.status-on_hold { color: #60a5fa; background: rgba(96,165,250,.10); border: 1px solid rgba(96,165,250,.25); }
.status-complete { color: #22c55e; background: rgba(34,197,94,.10); border: 1px solid rgba(34,197,94,.25); }
.status-canceled, .status-rejected { color: #e8445a; background: rgba(232,68,90,.10); border: 1px solid rgba(232,68,90,.25); }
</style>

<div class="content oe-wrap">
  @include('admin.partials.flash')

  <form action="{{ route('admin.orders.update', $order) }}" method="POST" id="editOrderForm">
    @csrf
    @method('PUT')

    {{-- Header --}}
    <div class="oe-head">
      <div>
        <h1 class="oe-head-title">Edit Order <span>#{{ $displayOrderNumber }}</span></h1>
        <p class="oe-head-sub">Modify order details, ticket holders, and pricing adjustments.</p>
      </div>
      <div class="oe-head-actions">
        <a href="{{ route('admin.orders.show', $order) }}" class="oe-btn">
          <i class="fa fa-arrow-left"></i> Back to Order
        </a>
        <button class="oe-btn oe-btn-primary" type="submit">
          <i class="fa fa-save"></i> Save Changes
        </button>
      </div>
    </div>

    {{-- Main Grid --}}
    <div class="oe-grid">

      {{-- LEFT: Ticket Items (invoice-style, read-only) --}}
      <div>
        <div class="oe-card">
          <div class="oe-card-head">
            <div class="oe-card-title">Ticket Items</div>
            <span style="font-size:12px;color:#5e5e72;">{{ $order->items->count() }} ticket(s)</span>
          </div>
          <div class="oe-card-body" style="padding: 0 20px 4px;">
            <table class="oe-invoice-table">
              <thead>
                <tr>
                  <th style="padding-left:0;">Ticket / Holder</th>
                  <th style="width:60px;text-align:center;">Qty</th>
                  <th style="width:120px;">Amount</th>
                </tr>
              </thead>
              <tbody>
                @forelse($order->items as $item)
                  <tr>
                    <td style="padding-left:0;">
                      <div class="oe-inv-name">{{ $item->ticket_name }}</div>
                      <div class="oe-inv-meta">
                        @if($item->holder_name)
                          <span class="oe-inv-meta-chip"><i class="fa fa-user"></i> {{ $item->holder_name }}</span>
                        @endif
                        @if($item->holder_email)
                          <span class="oe-inv-meta-chip"><i class="fa fa-envelope"></i> {{ $item->holder_email }}</span>
                        @endif
                        @if($item->holder_phone)
                          <span class="oe-inv-meta-chip"><i class="fa fa-phone"></i> {{ $item->holder_phone }}</span>
                        @endif
                      </div>
                    </td>
                    <td style="text-align:center;">
                      <span class="oe-inv-qty">x{{ $item->quantity }}</span>
                    </td>
                    <td>
                      <div class="oe-inv-price">{{ number_format((float)$item->line_total, 2) }} EGP</div>
                      @if($item->quantity > 1)
                        <span class="oe-inv-unit">{{ number_format((float)$item->line_total / $item->quantity, 2) }} EGP / ea</span>
                      @endif
                    </td>
                  </tr>
                @empty
                  <tr><td colspan="3" style="text-align:center;color:#5e5e72;padding:24px 0;">No tickets found.</td></tr>
                @endforelse
              </tbody>
            </table>
          </div>
        </div>

        {{-- Pricing Adjustments --}}
        <div class="oe-card">
          <div class="oe-card-head">
            <div class="oe-card-title">Pricing Adjustments</div>
          </div>
          <div class="oe-card-body">
            <div class="oe-row-3">
              <div class="oe-field">
                <label class="oe-label">Discount (EGP)</label>
                <input class="oe-input" type="number" min="0" step="0.01"
                  name="discount_fixed"
                  value="{{ old('discount_fixed', $order->discount_fixed ?? 0) }}"
                  placeholder="0.00" id="discountFixed">
              </div>
              <div class="oe-field">
                <label class="oe-label">Discount (%)</label>
                <input class="oe-input" type="number" min="0" max="100" step="0.01"
                  name="discount_percentage"
                  value="{{ old('discount_percentage', $order->discount_percentage ?? 0) }}"
                  placeholder="0.00" id="discountPct">
              </div>
              <div class="oe-field">
                <label class="oe-label">Extra Fees (EGP)</label>
                <input class="oe-input" type="number" min="0" step="0.01"
                  name="extra_fees"
                  value="{{ old('extra_fees', $order->extra_fees ?? 0) }}"
                  placeholder="0.00" id="extraFees">
              </div>
            </div>

            <hr class="oe-divider">

            <div class="oe-pricing-row">
              <span class="oe-pricing-label">Subtotal</span>
              <span class="oe-pricing-val">{{ number_format($order->total_amount, 2) }} EGP</span>
            </div>
            <div class="oe-pricing-row">
              <span class="oe-pricing-label">Discount Applied</span>
              <span class="oe-pricing-val" id="previewDiscount">— EGP</span>
            </div>
            <div class="oe-pricing-row">
              <span class="oe-pricing-label">Extra Fees</span>
              <span class="oe-pricing-val" id="previewFees">— EGP</span>
            </div>
            <div class="oe-pricing-row">
              <span class="oe-pricing-label" style="color:#fff;font-weight:700;">Grand Total</span>
              <span class="oe-pricing-val highlight" id="previewTotal">{{ number_format($order->total_amount, 2) }} EGP</span>
            </div>
          </div>
        </div>
      </div>

      {{-- RIGHT: Order Settings --}}
      <div>
        <div class="oe-card">
          <div class="oe-card-head">
            <div class="oe-card-title">Order Settings</div>
          </div>
          <div class="oe-card-body">

            <div class="oe-field">
              <label class="oe-label">Status</label>
              <select class="oe-select" name="status" id="statusSelect">
                @foreach($statusOptions as $val => $label)
                  <option value="{{ $val }}" {{ old('status', $order->status) === $val ? 'selected' : '' }}>
                    {{ $label }}
                  </option>
                @endforeach
              </select>
              <div style="margin-top:8px;">
                <span class="oe-status-preview status-{{ old('status', $order->status) }}" id="statusBadge">
                  {{ $statusOptions[old('status', $order->status)] ?? ucwords(str_replace('_',' ',$order->status)) }}
                </span>
              </div>
            </div>

            <hr class="oe-divider">

            <div class="oe-field">
              <label class="oe-label">Payment Method</label>
              <select class="oe-select" name="payment_method">
                @foreach($paymentMethodOptions as $val => $label)
                  <option value="{{ $val }}" {{ old('payment_method', $order->payment_method) === $val ? 'selected' : '' }}>
                    {{ $label }}
                  </option>
                @endforeach
              </select>
            </div>

            <div class="oe-field">
              <label class="oe-label">Payment Status</label>
              <select class="oe-select" name="payment_status">
                @foreach($paymentStatusOptions as $val => $label)
                  <option value="{{ $val }}" {{ old('payment_status', $order->payment_status) === $val ? 'selected' : '' }}>
                    {{ $label }}
                  </option>
                @endforeach
              </select>
            </div>

            <hr class="oe-divider">

            <div class="oe-field">
              <input type="hidden" name="requires_approval" value="0">
              <label class="oe-check-label">
                <input type="checkbox" name="requires_approval" value="1"
                  {{ old('requires_approval', $order->requires_approval) ? 'checked' : '' }}>
                Requires Approval
              </label>
            </div>

          </div>
        </div>

        {{-- Quick Info card --}}
        <div class="oe-card">
          <div class="oe-card-head"><div class="oe-card-title">Order Info</div></div>
          <div class="oe-card-body">
            <div class="oe-pricing-row">
              <span class="oe-pricing-label">Order #</span>
              <span class="oe-pricing-val">#{{ $displayOrderNumber }}</span>
            </div>
            <div class="oe-pricing-row">
              <span class="oe-pricing-label">Created</span>
              <span class="oe-pricing-val">{{ $order->created_at?->format('d M Y, H:i') }}</span>
            </div>
            <div class="oe-pricing-row">
              <span class="oe-pricing-label">Customer</span>
              <span class="oe-pricing-val">{{ $order->customer?->full_name ?: 'N/A' }}</span>
            </div>
            <div class="oe-pricing-row">
              <span class="oe-pricing-label">Email</span>
              <span class="oe-pricing-val" style="font-size:12px;">{{ $order->customer?->email ?: '-' }}</span>
            </div>
            @if($order->status === 'pending_payment' && $paymentLink)
              <div class="oe-field mt-3">
                <label class="oe-label">Payment Link</label>
                <div class="input-group">
                  <input type="text" class="oe-input" id="paymentLinkField" value="{{ $paymentLink }}" readonly>
                  <button type="button" class="oe-btn" id="copyPaymentLinkBtn"><i class="fa fa-copy"></i> Copy</button>
                </div>
              </div>
            @endif
          </div>
        </div>
      </div>

    </div>{{-- /oe-grid --}}
  </form>
</div>

<script>
(function () {
  const subtotal = {{ (float) $order->total_amount }};

  const dFixed  = document.getElementById('discountFixed');
  const dPct    = document.getElementById('discountPct');
  const fees    = document.getElementById('extraFees');
  const pDisc   = document.getElementById('previewDiscount');
  const pFees   = document.getElementById('previewFees');
  const pTotal  = document.getElementById('previewTotal');

  function fmt(n) { return n.toLocaleString('en-EG', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ' EGP'; }

  function recalc() {
    const discFixed = parseFloat(dFixed.value) || 0;
    const discPct   = parseFloat(dPct.value)   || 0;
    const extraFees = parseFloat(fees.value)   || 0;
    const discFromPct = subtotal * (discPct / 100);
    const totalDisc = discFixed + discFromPct;
    const grand = Math.max(0, subtotal - totalDisc + extraFees);
    pDisc.textContent  = totalDisc > 0 ? '- ' + fmt(totalDisc) : '— EGP';
    pFees.textContent  = extraFees > 0 ? '+ ' + fmt(extraFees) : '— EGP';
    pTotal.textContent = fmt(grand);
  }

  [dFixed, dPct, fees].forEach(el => el.addEventListener('input', recalc));
  recalc();

  // Status badge live preview
  const statusMap = {
    pending_approval: 'Pending Approval',
    pending_payment:  'Pending Payment',
    on_hold:          'On Hold',
    complete:         'Complete',
    canceled:         'Canceled',
    rejected:         'Rejected',
  };
  const sel   = document.getElementById('statusSelect');
  const badge = document.getElementById('statusBadge');

  sel.addEventListener('change', function () {
    badge.className = 'oe-status-preview status-' + this.value;
    badge.textContent = statusMap[this.value] || this.value;
  });

  const copyBtn = document.getElementById('copyPaymentLinkBtn');
  const paymentField = document.getElementById('paymentLinkField');
  if (copyBtn && paymentField) {
    copyBtn.addEventListener('click', async function () {
      try {
        await navigator.clipboard.writeText(paymentField.value);
        this.innerHTML = '<i class="fa fa-check"></i> Copied';
      } catch (e) {
        paymentField.select();
        document.execCommand('copy');
      }
    });
  }

})();
</script>
@endsection
