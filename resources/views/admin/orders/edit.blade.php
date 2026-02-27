@extends('admin.master')

@section('content')
@php
  $displayOrderNumber = preg_replace('/\D+/', '', (string) $order->order_number) ?: $order->order_number;
@endphp

<style>
.od-wrap { padding: 8px 0 60px; }
.od-head { display:flex;justify-content:space-between;align-items:flex-start;gap:12px;flex-wrap:wrap;margin-bottom:24px; }
.od-head h1{font-family:'Syne',sans-serif;font-weight:800;font-size:24px;color:#fff;margin:0}.od-head h1 span{color:#f5b800}
.od-head p{margin:4px 0 0;color:#5e5e72;font-size:13px}
.od-btn{display:inline-flex;align-items:center;gap:8px;padding:9px 16px;border-radius:8px;text-decoration:none;border:1px solid rgba(255,255,255,.07);background:#15151b;color:#ddd}
.od-btn-primary{background:#f5b800;border:0;color:#000;font-weight:700}
.od-grid{display:grid;grid-template-columns:1fr 340px;gap:20px}.od-card{background:#0d0d10;border:1px solid rgba(255,255,255,.07);border-radius:12px;overflow:hidden}
.od-card-head{padding:15px 20px;border-bottom:1px solid rgba(255,255,255,.07);color:#f5b800;font-family:'Syne',sans-serif;font-size:11px;letter-spacing:2px;text-transform:uppercase}
.od-card-body{padding:20px}.od-item{background:#15151b;border:1px solid rgba(255,255,255,.07);border-radius:10px;padding:14px;margin-bottom:12px}
.od-row{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px}.od-row-3{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:12px}
label{font-size:12px;color:#5e5e72;margin-bottom:6px} input,textarea{width:100%;background:#0d0d10;border:1px solid rgba(255,255,255,.12);border-radius:8px;color:#ddd;padding:9px}
@media (max-width:900px){.od-grid{grid-template-columns:1fr}.od-row,.od-row-3{grid-template-columns:1fr}}
</style>

<div class="content od-wrap">
  @include('admin.partials.flash')
  <form action="{{ route('admin.orders.update', $order) }}" method="POST">
    @csrf
    @method('PUT')

    <div class="od-head">
      <div>
        <h1>Edit Order <span>#{{ $displayOrderNumber }}</span></h1>
        <p>Keep the same style and update dynamic values.</p>
      </div>
      <div class="d-flex gap-2">
        <a href="{{ route('admin.orders.show', $order) }}" class="od-btn"><i class="fa fa-arrow-left"></i> Back</a>
        <button class="od-btn od-btn-primary" type="submit"><i class="fa fa-save"></i> Save Changes</button>
      </div>
    </div>

    <div class="od-grid">
      <div>
        <div class="od-card">
          <div class="od-card-head">Ticket Items</div>
          <div class="od-card-body">
            @foreach($order->items as $item)
              <div class="od-item">
                <input type="hidden" name="items[{{ $loop->index }}][id]" value="{{ $item->id }}">
                <div style="color:#fff;font-weight:700;margin-bottom:10px;">{{ $item->ticket_name }}</div>
                <div class="od-row-3">
                  <div>
                    <label>Quantity</label>
                    <input type="number" min="1" name="items[{{ $loop->index }}][quantity]" value="{{ old("items.$loop->index.quantity", $item->quantity) }}">
                  </div>
                  <div style="grid-column:span 2;">
                    <label>Holder Name</label>
                    <input type="text" name="items[{{ $loop->index }}][holder_name]" value="{{ old("items.$loop->index.holder_name", $item->holder_name) }}">
                  </div>
                </div>
                <div class="od-row" style="margin-top:10px;">
                  <div>
                    <label>Holder Email</label>
                    <input type="email" name="items[{{ $loop->index }}][holder_email]" value="{{ old("items.$loop->index.holder_email", $item->holder_email) }}">
                  </div>
                  <div>
                    <label>Holder Phone</label>
                    <input type="text" name="items[{{ $loop->index }}][holder_phone]" value="{{ old("items.$loop->index.holder_phone", $item->holder_phone) }}">
                  </div>
                </div>
              </div>
            @endforeach
          </div>
        </div>
      </div>
      <div>
        <div class="od-card">
          <div class="od-card-head">Order Settings</div>
          <div class="od-card-body">
            <div style="margin-bottom:10px;"><label>Status</label><input type="text" name="status" value="{{ old('status', $order->status) }}"></div>
            <div style="margin-bottom:10px;"><label>Payment Method</label><input type="text" name="payment_method" value="{{ old('payment_method', $order->payment_method) }}"></div>
            <div style="margin-bottom:10px;"><label>Payment Status</label><input type="text" name="payment_status" value="{{ old('payment_status', $order->payment_status) }}"></div>
            <label style="display:flex;gap:8px;align-items:center;color:#ddd;"><input type="hidden" name="requires_approval" value="0"><input type="checkbox" name="requires_approval" value="1" {{ old('requires_approval', $order->requires_approval) ? 'checked' : '' }} style="width:auto;"> Requires Approval</label>
          </div>
        </div>
      </div>
    </div>
  </form>
</div>
@endsection
