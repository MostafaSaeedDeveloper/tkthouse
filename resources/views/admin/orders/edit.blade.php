@extends('admin.master')
@section('title', 'Edit Order')


@section('content')
@php
  $displayOrderNumber = preg_replace('/\D+/', '', (string) $order->order_number) ?: $order->order_number;
  $paymentLink = $order->payment_link_token ? route('front.orders.payment', ['order' => $order, 'token' => $order->payment_link_token]) : null;
  $money = fn ($n) => number_format((float) $n, 2).' EGP';
@endphp

<x-admin.page-header :title="'Edit Order #'.$displayOrderNumber" subtitle="Modify order details, ticket holders, and pricing adjustments." :crumbs="['Dashboard' => route('admin.dashboard'), 'Orders' => route('admin.orders.index'), '#'.$displayOrderNumber => route('admin.orders.show', $order), 'Edit']">
  <a href="{{ route('admin.orders.show', $order) }}" class="a-btn a-btn--outline"><i class="fa fa-arrow-left"></i> Back to Order</a>
</x-admin.page-header>

<x-admin.flash />

<form action="{{ route('admin.orders.update', $order) }}" method="POST" id="editOrderForm" data-a-loading class="a-ord-form">
  @csrf
  @method('PUT')

  <div class="a-ord-grid">
    <div class="a-ord-col">
      <x-admin.card title="Ticket Items" :subtitle="$order->items->count().' ticket(s)'">
        @forelse($order->items as $item)
          @php
            $ticketName = trim((string) $item->ticket_name);
            $eventName = str_contains($ticketName, ' - ') ? trim((string) str($ticketName)->before(' - ')) : $ticketName;
            $ticketType = trim((string) \Illuminate\Support\Str::afterLast($ticketName, ' - '));
            $socialProfileRaw = trim((string) $item->holder_social_profile);
            $socialProfileLink = $socialProfileRaw !== ''
              ? (\Illuminate\Support\Str::startsWith($socialProfileRaw, ['http://', 'https://']) ? $socialProfileRaw : 'https://' . ltrim($socialProfileRaw, '/'))
              : null;
          @endphp
          <div class="a-ord-edititem">
            <div class="a-ord-item__main">
              <div class="a-ord-item__name">{{ $eventName }}
                @if($ticketType !== '' && $ticketType !== $eventName)<x-admin.badge class="a-ord-type">{{ $ticketType }}</x-admin.badge>@endif
              </div>
              <div class="a-ord-item__meta">
                @if($item->holder_name)<div><i class="fa fa-user"></i> {{ $item->holder_name }}</div>@endif
                @if($item->holder_email || $item->holder_phone || $item->holder_gender)
                  <div><i class="fa fa-id-card"></i> {{ $item->holder_email ?: '-' }} — {{ $item->holder_phone ?: '-' }} — {{ ucwords(str_replace('_', ' ', (string) ($item->holder_gender ?: '-'))) }}</div>
                @endif
                @if($socialProfileLink)
                  <div><i class="fa fa-globe"></i> <a href="{{ $socialProfileLink }}" target="_blank" rel="noopener noreferrer">{{ $socialProfileRaw }}</a></div>
                @endif
              </div>
            </div>
            <div>
              <span class="form-label">Qty</span><strong>x{{ $item->quantity }}</strong>
              <input type="hidden" name="items[{{ $item->id }}][id]" value="{{ $item->id }}">
              <input type="hidden" name="items[{{ $item->id }}][quantity]" value="{{ $item->quantity }}">
              <input type="hidden" name="items[{{ $item->id }}][holder_name]" value="{{ $item->holder_name }}">
              <input type="hidden" name="items[{{ $item->id }}][holder_email]" value="{{ $item->holder_email }}">
              <input type="hidden" name="items[{{ $item->id }}][holder_phone]" value="{{ $item->holder_phone }}">
            </div>
            <div class="a-field">
              <label class="form-label" for="itemPrice{{ $item->id }}">Ticket price (EGP)</label>
              <input class="form-control" id="itemPrice{{ $item->id }}" type="number" min="0" step="0.01"
                name="items[{{ $item->id }}][ticket_price]"
                value="{{ old('items.' . $item->id . '.ticket_price', number_format((float) $item->ticket_price, 2, '.', '')) }}">
              <div class="a-hint">Line total {{ $money($item->line_total) }}@if($item->quantity > 1) ({{ $money((float)$item->line_total / $item->quantity) }} / ea)@endif</div>
            </div>
          </div>
        @empty
          <x-admin.empty icon="fa-ticket" title="No tickets found" />
        @endforelse
      </x-admin.card>

      <x-admin.card title="Pricing Adjustments">
        <div class="a-form-grid a-form-grid--3">
          <div class="a-field">
            <label class="form-label" for="discountFixed">Discount (EGP)</label>
            <input class="form-control" type="number" min="0" step="0.01" name="discount_fixed" value="{{ old('discount_fixed', $order->discount_fixed ?? 0) }}" placeholder="0.00" id="discountFixed">
          </div>
          <div class="a-field">
            <label class="form-label" for="discountPct">Discount (%)</label>
            <input class="form-control" type="number" min="0" max="100" step="0.01" name="discount_percentage" value="{{ old('discount_percentage', $order->discount_percentage ?? 0) }}" placeholder="0.00" id="discountPct">
          </div>
          <div class="a-field">
            <label class="form-label" for="extraFees">Extra Fees (EGP)</label>
            <input class="form-control" type="number" min="0" step="0.01" name="extra_fees" value="{{ old('extra_fees', number_format((float) $existingExtraFees, 2, '.', '')) }}" placeholder="0.00" id="extraFees">
          </div>
        </div>
        <div class="a-ord-totals">
          <div><span>Subtotal</span><strong>{{ number_format(max(0, (float) $order->subtotal_amount - (float) $order->discount_amount), 2) }} EGP</strong></div>
          <div><span>Discount Applied</span><strong id="previewDiscount">— EGP</strong></div>
          <div><span>Extra Fees</span><strong id="previewFees">— EGP</strong></div>
          <div class="is-grand"><span>Grand Total</span><strong id="previewTotal">{{ number_format($order->total_amount, 2) }} EGP</strong></div>
        </div>
      </x-admin.card>
    </div>

    <div class="a-ord-col">
      <x-admin.card title="Order Settings">
        <div class="a-form-grid">
        <div class="a-field">
          <label class="form-label" for="statusSelect">Status</label>
          <select class="form-select no-select2" name="status" id="statusSelect">
            @foreach($statusOptions as $val => $label)
              <option value="{{ $val }}" {{ old('status', $order->status) === $val ? 'selected' : '' }}>{{ $label }}</option>
            @endforeach
          </select>
        </div>
        <div class="a-field">
          <label class="form-label" for="editPaymentMethod">Payment Method</label>
          <select class="form-select no-select2" name="payment_method" id="editPaymentMethod">
            @foreach($paymentMethods as $method)
              <option value="{{ $method->code }}" {{ old('payment_method', $order->payment_method) === $method->code ? 'selected' : '' }}>{{ $method->name }}{{ $method->is_active ? '' : ' (Inactive)' }}</option>
            @endforeach
          </select>
        </div>
        <div class="a-field">
          <label class="form-label" for="editPromoCode">Promo Code</label>
          <select class="form-select no-select2" name="promo_code" id="editPromoCode">
            <option value="">No Promo Code</option>
            @foreach($promoCodes as $promo)
              <option value="{{ $promo->code }}" {{ old('promo_code', $order->promo_code) === $promo->code ? 'selected' : '' }}>
                {{ $promo->code }} — {{ $promo->discount_type === 'percent' ? rtrim(rtrim(number_format((float) $promo->discount_value, 2), '0'), '.') . '%' : number_format((float) $promo->discount_value, 2) . ' EGP' }}{{ $promo->is_active ? '' : ' (Inactive)' }}
              </option>
            @endforeach
          </select>
        </div>

        @can('showing_orders')
          <div class="a-field">
            <div class="form-check">
              <input class="form-check-input" id="editExclude" type="checkbox" name="exclude_from_statistics" value="1" {{ old('exclude_from_statistics', $order->exclude_from_statistics) ? 'checked' : '' }}>
              <label class="form-check-label" for="editExclude">Exclude this order from dashboard and reports statistics</label>
            </div>
            <div class="a-hint">When enabled, this order will not be counted in analytics totals.</div>
          </div>
        @endcan

        @if($order->status === 'pending_payment' && $paymentLink)
          <div class="a-field">
            <label class="form-label" for="editPaymentLink">Payment Link</label>
            <div class="a-ord-linkbox">
              <input class="form-control" id="editPaymentLink" type="text" readonly value="{{ $paymentLink }}" onclick="this.select()">
              <button class="a-btn a-btn--outline" id="copyPaymentLinkBtn" type="button" data-payment-link="{{ $paymentLink }}">Copy Link</button>
            </div>
          </div>
        @endif
      </div>
      </x-admin.card>

      <x-admin.card title="Order Info">
        <dl class="a-kv">
          <div><dt>Order #</dt><dd>#{{ $displayOrderNumber }}</dd></div>
          <div><dt>Created</dt><dd>{{ $order->created_at?->format('d M Y, H:i') }}</dd></div>
          <div><dt>Customer</dt><dd>{{ $order->customer?->full_name ?: 'N/A' }}</dd></div>
          <div><dt>Email</dt><dd>{{ $order->customer?->email ?: '-' }}</dd></div>
          <div><dt>Promo Code</dt><dd>{{ $order->promo_code ?: '-' }}</dd></div>
          <div><dt>Subtotal</dt><dd>{{ $money($order->subtotal_amount ?: $order->total_amount) }}</dd></div>
          <div><dt>Discount</dt><dd>{{ $money($order->discount_amount) }}</dd></div>
        </dl>
      </x-admin.card>
    </div>
  </div>

  <div class="a-form-actions">
    <a href="{{ route('admin.orders.show', $order) }}" class="a-btn a-btn--outline">Cancel</a>
    <button class="a-btn a-btn--primary" type="submit"><i class="fa fa-save"></i> Save Changes</button>
  </div>
</form>

<script>
(function () {
  const subtotal = {{ (float) max(0, (float) $order->subtotal_amount - (float) $order->discount_amount) }};

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
  const copyBtn = document.getElementById('copyPaymentLinkBtn');
  if (copyBtn) {
    copyBtn.addEventListener('click', async function () {
      const originalText = this.textContent;
      try {
        await navigator.clipboard.writeText(this.dataset.paymentLink || '');
        this.textContent = 'Copied ✓';
      } catch (error) {
        this.textContent = 'Copy failed';
      }
      setTimeout(() => {
        this.textContent = originalText;
      }, 1300);
    });
  }


})();
</script>
@endsection
