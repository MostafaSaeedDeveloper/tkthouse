@extends('front.layout.master')

@section('content')
@php
    $methods = collect($activePaymentMethods ?? []);
    $selected = old('payment_method', $selectedMethod ?? $order->payment_method);
@endphp

<style>
.pm-page{background:#07070a;min-height:100vh;color:#f0f0f5;padding:40px 0 80px}
.pm-wrap{max-width:1080px;margin:0 auto;display:grid;grid-template-columns:1.2fr .8fr;gap:22px}
@media (max-width:900px){.pm-wrap{grid-template-columns:1fr}}
.pm-card{background:#101018;border:1px solid rgba(255,255,255,.08);border-radius:14px;overflow:hidden}
.pm-head{padding:16px 20px;border-bottom:1px solid rgba(255,255,255,.07);font-weight:700;letter-spacing:.5px;color:#f5b800}
.pm-body{padding:18px 20px}
.pm-method{display:flex;gap:12px;padding:12px;border:1px solid rgba(255,255,255,.08);border-radius:10px;margin-bottom:10px;cursor:pointer;background:#161622}
.pm-method input{margin-top:2px}
.pm-method.active{border-color:rgba(245,184,0,.5);background:rgba(245,184,0,.08)}
.pm-sub{color:#9da3bd;font-size:13px}
.pm-item{display:flex;justify-content:space-between;border-bottom:1px dashed rgba(255,255,255,.09);padding:8px 0}
.pm-item:last-child{border-bottom:none}
.pm-total{display:flex;justify-content:space-between;font-size:20px;font-weight:800;color:#f5b800;margin-top:10px}
.pm-btn{width:100%;border:0;border-radius:10px;padding:12px 14px;font-weight:700}
.pm-btn-primary{background:#f5b800;color:#111}
.pm-btn-alt{background:#1f2538;color:#fff;border:1px solid rgba(255,255,255,.14)}
.pm-alert{background:rgba(232,68,90,.15);border:1px solid rgba(232,68,90,.4);color:#ffccd3;border-radius:10px;padding:10px 12px;margin-bottom:12px}
</style>

<section class="pm-page">
  <div class="container">
    <div class="pm-wrap">
      <div class="pm-card">
        <div class="pm-head">Checkout Payment • Order #{{ $order->order_number }}</div>
        <div class="pm-body">
          @if($errors->has('payment') || $errors->has('payment_method'))
            <div class="pm-alert">{{ $errors->first('payment') ?: $errors->first('payment_method') }}</div>
          @endif

          <div id="paymentSelection">
            <input type="hidden" id="selectedPaymentMethod" value="{{ $selected }}">

            @forelse($methods as $method)
              @php
                $methodCode = (string) $method->code;
                $isSelected = $selected === $methodCode;
              @endphp
              <label class="pm-method {{ $isSelected ? 'active' : '' }}" data-code="{{ $methodCode }}" data-provider="{{ $method->provider }}">
                <input type="radio" name="payment_method_radio" value="{{ $methodCode }}" {{ $isSelected ? 'checked' : '' }}>
                <div>
                  <div><strong>{{ $method->checkout_label ?: $method->name }}</strong></div>
                  <div class="pm-sub">{{ $method->checkout_description ?: ('Pay with '.$method->name) }}</div>
                </div>
              </label>
            @empty
              <div class="pm-alert">No active payment methods available now.</div>
            @endforelse

            <div style="margin-top:14px;">
              <button type="button" id="paymobBtn" class="pm-btn pm-btn-primary">Pay Now</button>
            </div>
          </div>
        </div>
      </div>

      <div class="pm-card">
        <div class="pm-head">Order Summary</div>
        <div class="pm-body">
          @foreach($order->items as $item)
            <div class="pm-item">
              <div>{{ $item->ticket_name }} × {{ $item->quantity }}</div>
              <div>{{ number_format($item->line_total, 2) }} EGP</div>
            </div>
          @endforeach
          <div class="pm-total"><span>Total</span><span>{{ number_format($order->total_amount, 2) }} EGP</span></div>
          <div class="pm-sub" style="margin-top:8px;">Status: {{ ucwords(str_replace('_', ' ', $order->status)) }}</div>
        </div>
      </div>
    </div>
  </div>
</section>

<script>
(() => {
  const methodCards = Array.from(document.querySelectorAll('.pm-method'));
  const hidden = document.getElementById('selectedPaymentMethod');
  const paymobBtn = document.getElementById('paymobBtn');

  function selectCard(code) {
    hidden.value = code;
    methodCards.forEach(card => {
      const isActive = card.dataset.code === code;
      card.classList.toggle('active', isActive);
      const radio = card.querySelector('input[type="radio"]');
      if (radio) radio.checked = isActive;
    });
  }

  methodCards.forEach(card => {
    card.addEventListener('click', () => selectCard(card.dataset.code));
  });

  paymobBtn?.addEventListener('click', () => {
    const active = methodCards.find(card => card.classList.contains('active'));
    const code = active?.dataset.code || hidden.value;
    window.location.href = `{{ route('front.orders.payment.paymob', ['order' => $order, 'token' => $order->payment_link_token]) }}?method=${encodeURIComponent(code)}`;
  });
})();
</script>
@endsection
