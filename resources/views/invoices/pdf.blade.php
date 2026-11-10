<!doctype html>
<html>
<head>
<meta charset="utf-8">
<title>Invoice {{ $i->number }}</title>
<style>
  @page { margin: 0; }
  * { box-sizing: border-box; }
  body { margin: 0; font-family: DejaVu Sans, sans-serif; font-size: 10.5px; color: #15151a; line-height: 1.45; }
  table { border-collapse: collapse; width: 100%; }
  td, th { vertical-align: top; }
  .head { background: #0e0e13; padding: 26px 40px 22px; border-bottom: 5px solid #f5b800; }
  .head td { vertical-align: middle; }
  .doc-title { color: #fff; font-size: 20px; font-weight: bold; letter-spacing: 3px; text-align: right; }
  .doc-sub { color: #f5b800; font-size: 10px; text-align: right; margin-top: 4px; letter-spacing: 1px; }
  .body { padding: 26px 40px 0; position: relative; }
  .label { font-size: 8px; color: #8a8a96; letter-spacing: 1.2px; text-transform: uppercase; margin-bottom: 3px; }
  .val { font-size: 11px; font-weight: bold; color: #15151a; }
  .muted { color: #6b6b78; font-weight: normal; }
  .meta td { padding: 0 14px 12px 0; width: 25%; }
  .box { border: 1px solid #e4e4ea; padding: 12px 14px; }
  .box-y { border-left: 4px solid #f5b800; background: #fffaf0; }
  .stamp { position: absolute; right: 48px; top: 72px; border: 4px solid #f5b800; color: #0e0e13; background: #fff; padding: 4px 22px; font-size: 30px; font-weight: bold; letter-spacing: 6px; transform: rotate(-12deg); }
  .items { margin-top: 20px; }
  .items th { background: #0e0e13; color: #f5b800; font-size: 8.5px; letter-spacing: 1px; text-transform: uppercase; padding: 9px 10px; text-align: left; }
  .items td { padding: 10px; border-bottom: 1px solid #ececf0; }
  .r { text-align: right !important; }
  .c { text-align: center !important; }
  .sum { width: 330px; margin-left: auto; margin-top: 14px; }
  .sum td { padding: 5px 10px; font-size: 10.5px; white-space: nowrap; }
  .sum .good td { color: #1a8f4c; }
  .sum .tot td { background: #f5b800; color: #0e0e13; font-size: 13px; font-weight: bold; padding: 10px; }
  .sum .net td { border-top: 1px solid #0e0e13; font-weight: bold; padding-top: 8px; }
  .refund { margin-top: 18px; border: 1px solid #e8b4bc; background: #fdf3f4; padding: 10px 14px; }
  .refund-h { color: #b3263a; font-weight: bold; font-size: 10px; letter-spacing: 1px; text-transform: uppercase; margin-bottom: 6px; }
  .foot { position: absolute; left: 0; right: 0; bottom: 0; background: #0e0e13; color: #b8b8c4; padding: 14px 40px; font-size: 8.5px; border-top: 3px solid #f5b800; }
  .foot b { color: #f5b800; }
  .note { margin-top: 20px; font-size: 8.5px; color: #6b6b78; }
</style>
</head>
<body>
  <div class="head">
    <table><tr>
      <td style="width:50%;"><img src="{{ public_path('images/logo-light.png') }}" style="width:150px;height:auto;" alt="TKT House"></td>
      <td>
        <div class="doc-title">PAYMENT INVOICE</div>
        <div class="doc-sub">{{ $i->number }}</div>
      </td>
    </tr></table>
  </div>

  <div class="body">
    <div class="stamp">PAID</div>

    <table class="meta" style="width:62%;">
      <tr>
        <td><div class="label">Invoice no.</div><div class="val">{{ $i->number }}</div></td>
        <td><div class="label">Order no.</div><div class="val">#{{ $i->orderNumber }}</div></td>
      </tr>
      <tr>
        <td><div class="label">Payment date</div><div class="val">{{ $i->paidAt ? $i->paidAt->format('d M Y') : '—' }}</div>@if($i->paidAt)<div class="muted">{{ $i->paidAt->format('H:i') }}</div>@endif</td>
        <td><div class="label">Payment method</div><div class="val">{{ \App\Support\ArabicPdfText::shape($i->paymentMethod) }}</div></td>
      </tr>
      @if($i->paymentReference)
      <tr><td colspan="2"><div class="label">Payment reference</div><div class="val">{{ $i->paymentReference }}</div></td></tr>
      @endif
    </table>

    <table style="margin-top:6px;"><tr>
      <td style="width:49%;">
        <div class="box">
          <div class="label">Billed to</div>
          <div class="val">{{ \App\Support\ArabicPdfText::shape($i->customerName) }}</div>
          <div class="muted">{{ $i->customerEmail }}</div>
          @if($i->customerPhone)<div class="muted">{{ $i->customerPhone }}</div>@endif
        </div>
      </td>
      <td style="width:2%;"></td>
      <td style="width:49%;">
        <div class="box box-y">
          <div class="label">Event</div>
          <div class="val">{{ \App\Support\ArabicPdfText::shape($i->event['name']) }}</div>
          <div class="muted">{{ collect([$i->event['date'], $i->event['time']])->filter()->implode(' · ') }}</div>
          @if($i->event['location'])<div class="muted">{{ \App\Support\ArabicPdfText::shape($i->event['location']) }}</div>@endif
        </div>
      </td>
    </tr></table>

    <table class="items">
      <thead><tr>
        <th>Ticket type</th><th class="c" style="width:50px;">Qty</th><th class="r" style="width:95px;">Unit price</th><th class="r" style="width:100px;">Amount</th>
      </tr></thead>
      <tbody>
        @foreach($i->lines as $line)
          <tr>
            <td><b>{{ \App\Support\ArabicPdfText::shape($line['name']) }}</b></td>
            <td class="c">{{ $line['qty'] }}</td>
            <td class="r">{{ $i->money($line['price']) }}</td>
            <td class="r">{{ $i->money($line['total']) }}</td>
          </tr>
        @endforeach
      </tbody>
    </table>

    <table class="sum">
      <tr><td>Tickets subtotal ({{ $i->ticketCount }})</td><td class="r">{{ $i->money($i->subtotal) }}</td></tr>
      @if($i->discount > 0)
        <tr class="good"><td>Discount @if($i->promoCode)({{ \App\Support\ArabicPdfText::shape($i->promoCode) }})@endif</td><td class="r">− {{ $i->money($i->discount) }}</td></tr>
      @endif
      @foreach($i->fees as $fee)
        <tr><td>{{ \App\Support\ArabicPdfText::shape($fee['name']) }}@if($fee['detail']) <span class="muted">({{ $fee['detail'] }})</span>@endif</td><td class="r">{{ $i->money($fee['amount']) }}</td></tr>
      @endforeach
      @if(abs($i->adjustment) >= 0.01)
        <tr><td>Adjustment</td><td class="r">{{ $i->adjustment < 0 ? '− ' : '' }}{{ $i->money(abs($i->adjustment)) }}</td></tr>
      @endif
      <tr class="tot"><td>Total paid</td><td class="r">{{ $i->money($i->total) }}</td></tr>
      @if($i->isRefunded())
        <tr class="good"><td style="color:#b3263a;">Refunded</td><td class="r" style="color:#b3263a;">− {{ $i->money($i->refunded) }}</td></tr>
        <tr class="net"><td>Net paid</td><td class="r">{{ $i->money($i->netPaid()) }}</td></tr>
      @endif
    </table>

    @if($i->isRefunded())
      <div class="refund">
        <div class="refund-h">{{ $i->isFullyRefunded() ? 'Fully refunded' : 'Partially refunded' }}</div>
        <div style="margin-bottom:5px;">This invoice remains the record of the original payment. The refund below was returned to the customer afterwards.</div>
        @foreach($i->refunds as $r)
          <div>{{ $r['date'] ? $r['date']->format('d M Y') : '—' }} · <b>{{ $i->money($r['amount']) }}</b>@if($r['reference']) · {{ $r['reference'] }}@endif</div>
        @endforeach
      </div>
    @endif

    <div class="note">This is a payment invoice confirming the amount paid for the booking above. It is not a tax invoice. All amounts are in {{ $i->currency }} and match the order exactly.</div>
  </div>

  <div class="foot">
    <table><tr>
      <td><b>TKT House</b> · Thank you for your booking</td>
      <td class="r">{{ $i->number }} · Order #{{ $i->orderNumber }}</td>
    </tr></table>
  </div>
</body>
</html>
