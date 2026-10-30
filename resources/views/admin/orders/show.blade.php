@extends('admin.master')
@section('title', 'Order #'.\App\Support\OrderPresenter::number($order))

@push('styles')<link rel="stylesheet" href="{{ asset('admin-v2/orders-customers.css') }}?v={{ filemtime(public_path('admin-v2/orders-customers.css')) }}">@endpush

@section('content')
@php
  $displayOrderNumber = \App\Support\OrderPresenter::number($order);
  $customerName = $order->customer?->full_name ?: 'N/A';
  $customerInitials = collect(explode(' ', $customerName))->filter()->map(fn($p)=>mb_substr($p,0,1))->take(2)->implode('');
  $paymentLink = $order->payment_link_token ? route('front.orders.payment', ['order' => $order, 'token' => $order->payment_link_token]) : null;
  $money = fn ($n) => number_format((float) $n, 2).' EGP';
  $attempts = $paymentAttempts;
  $latestAttempt = $attempts->first();
  $ticketCount = \App\Support\OrderPresenter::ticketCount($order);
  $events = $order->items->pluck('event')->filter()->unique('id')->values();
  // One row per distinct ticket name + unit price; ticket names are free text, so nothing is inferred from them.
  $ticketLines = $order->items->groupBy(fn ($i) => trim((string) $i->ticket_name).'|'.(float) $i->ticket_price)->map(fn ($rows) => [
      'name' => trim((string) $rows->first()->ticket_name) ?: 'Ticket',
      'unit' => (float) $rows->first()->ticket_price,
      'qty' => (int) $rows->sum(fn ($i) => max(1, (int) $i->quantity)),
      'total' => (float) $rows->sum('line_total'),
  ])->values();
  $subtotal = (float) ($order->subtotal_amount ?: $order->total_amount);
  $discount = (float) $order->discount_amount;
  $extraFees = max(0, round((float) $order->total_amount - max(0, $subtotal - $discount), 2));
@endphp

<x-admin.page-header :title="'Order #'.$displayOrderNumber" :subtitle="'Placed '.$order->created_at?->format('d M Y, H:i')" :crumbs="['Dashboard' => route('admin.dashboard'), 'Orders' => route('admin.orders.index'), '#'.$displayOrderNumber]">
  @can('orders.update')
    @if($order->status === 'pending_approval')
      <form method="POST" action="{{ route('admin.orders.approve', $order) }}" data-a-confirm="Approve this order and send the payment link?" data-a-confirm-title="Approve order" data-a-confirm-ok="Approve">@csrf
        <button class="a-btn a-btn--primary" type="submit"><i class="fa fa-check"></i> Approve & Send Payment Link</button>
      </form>
      <form method="POST" action="{{ route('admin.orders.reject', $order) }}" data-a-confirm="Reject this order?" data-a-confirm-title="Reject order" data-a-confirm-ok="Reject">@csrf
        <button class="a-btn a-btn--danger" type="submit"><i class="fa fa-times"></i> Reject</button>
      </form>
    @endif
    <a href="{{ route('admin.orders.edit', $order) }}" class="a-btn a-btn--outline"><i class="fa fa-pen"></i> Edit</a>
  @endcan
  @can('update_status')
    <button type="button" class="a-btn a-btn--outline" id="openStatusModalBtn"><i class="fa fa-refresh"></i> Change Status</button>
  @endcan
  <a href="{{ route('admin.orders.index') }}" class="a-btn a-btn--ghost"><i class="fa fa-arrow-left"></i> Back</a>
</x-admin.page-header>

<x-admin.flash />

{{-- Order header: the facts people look for first --}}
<section class="a-ord-summary a-ord-head" aria-label="Order overview">
  <dl class="a-ord-summary__grid">
    <div><dt>Order status</dt><dd class="a-ord-head__badge"><x-admin.status :status="$order->status" kind="order" /></dd></div>
    <div><dt>Payment</dt><dd class="a-ord-head__badge">
      @if($latestAttempt)<x-admin.status :status="$latestAttempt->displayStatus()" kind="payment" />
      @elseif($order->status === 'paid')<x-admin.status status="paid" kind="payment" />
      @else<span class="a-muted a-ord-head__none">No payment recorded</span>@endif
    </dd></div>
    <div><dt>Total</dt><dd>{{ $money($order->total_amount) }}</dd></div>
    <div><dt>Tickets</dt><dd>{{ $ticketCount }}</dd><small>{{ $order->items->count() }} attendee{{ $order->items->count() === 1 ? '' : 's' }}</small></div>
  </dl>
</section>

<div class="a-ord-grid">
  <div class="a-ord-col">
    <x-admin.card title="Order & Payment" subtitle="Order status and payment are tracked separately.">
      <dl class="a-kv">
        <div><dt>Order #</dt><dd>{{ $displayOrderNumber }}</dd></div>
        <div><dt>Placed</dt><dd>{{ $order->created_at?->format('d M Y, H:i') }}</dd></div>
        <div><dt>Order status</dt><dd><x-admin.status :status="$order->status" kind="order" /></dd></div>
        <div><dt>Payment method</dt><dd>{{ $paymentMethodLabel }}</dd></div>
        @if($latestAttempt)
          <div><dt>Latest payment attempt</dt><dd><x-admin.status :status="$latestAttempt->displayStatus()" kind="payment" /></dd></div>
        @endif
        @if($order->paid_at)<div><dt>Paid at</dt><dd>{{ $order->paid_at->format('d M Y, H:i') }}</dd></div>@endif
        @if($order->requires_approval)<div><dt>Booking approval</dt><dd>{{ $order->approved_at ? 'Approved '.$order->approved_at->format('d M Y, H:i') : 'Required' }}</dd></div>@endif
        @if($order->status === 'pending_payment' && $order->paymentDeadlineAt())<div><dt>Payment deadline</dt><dd>{{ $order->paymentDeadlineAt()->format('d M Y, H:i') }}</dd></div>@endif
        @can('showing_orders')
          <div><dt>Show in Stats</dt><dd>{{ $order->exclude_from_statistics ? 'No (Excluded)' : 'Yes' }}</dd></div>
        @endcan
      </dl>
      @if($order->status === 'pending_payment' && $paymentLink)
        <div class="a-field" style="margin-top:16px">
          <label class="form-label" for="paymentLinkInput">Payment Link</label>
          <div class="a-ord-linkbox">
            <input type="text" readonly value="{{ $paymentLink }}" class="form-control" id="paymentLinkInput" onclick="this.select();">
            <button type="button" class="a-btn a-btn--outline" id="copyPaymentLinkBtn" data-payment-link="{{ $paymentLink }}">Copy</button>
          </div>
        </div>
      @endif
    </x-admin.card>

    @if($events->isNotEmpty())
    <x-admin.card title="Event Details" :subtitle="$events->count() > 1 ? $events->count().' events in this order' : null">
      @foreach($events as $event)
        <div class="a-ord-event">
          <div class="a-ord-event__name">{{ $event->name }}</div>
          <div class="a-ord-item__meta">
            @if($event->event_date)<span><i class="fa fa-calendar" aria-hidden="true"></i> {{ $event->formatted_date_range }}@if($event->event_time) · {{ \Illuminate\Support\Carbon::parse($event->event_time)->format('H:i') }}@endif</span>@endif
            @if($event->location)<span><i class="fa fa-location-dot" aria-hidden="true"></i> {{ $event->location }}</span>@endif
          </div>
        </div>
      @endforeach
    </x-admin.card>
    @endif

    <x-admin.card flush title="Purchased Tickets" :subtitle="$ticketCount.' ticket'.($ticketCount === 1 ? '' : 's').' · '.$ticketLines->count().' ticket '.($ticketLines->count() === 1 ? 'type' : 'types')">
      @if($ticketLines->isEmpty())
        <x-admin.empty icon="fa-ticket" title="No tickets found" />
      @else
        <x-admin.table stack compact caption="Purchased tickets">
          <thead><tr><th scope="col" class="a-wrap">Ticket</th><th scope="col" class="a-num">Qty</th><th scope="col" class="a-num">Unit price</th><th scope="col" class="a-num">Total</th></tr></thead>
          <tbody>
          @foreach($ticketLines as $line)
            <tr>
              <td data-label="Ticket" class="a-wrap"><span class="a-table__main">{{ $line['name'] }}</span></td>
              <td data-label="Qty" class="a-num">{{ $line['qty'] }}</td>
              <td data-label="Unit price" class="a-num">{{ $money($line['unit']) }}</td>
              <td data-label="Total" class="a-num"><strong>{{ $money($line['total']) }}</strong></td>
            </tr>
          @endforeach
          </tbody>
        </x-admin.table>
      @endif
    </x-admin.card>

    <x-admin.card title="Attendee Information" :subtitle="$order->items->count().' attendee'.($order->items->count() === 1 ? '' : 's')">
      @forelse($order->items as $item)
        @php
          $socialProfileRaw = trim((string) $item->holder_social_profile);
          $socialProfileLink = $socialProfileRaw !== ''
            ? (\Illuminate\Support\Str::startsWith($socialProfileRaw, ['http://', 'https://']) ? $socialProfileRaw : 'https://' . ltrim($socialProfileRaw, '/'))
            : null;
          // Show "instagram.com/handle" instead of the full tracking URL; the full link stays on the href/title.
          $socialParts = $socialProfileLink ? parse_url($socialProfileLink) : null;
          $socialProfileShort = $socialParts ? preg_replace('/^www\./', '', (string) ($socialParts['host'] ?? '')).rtrim((string) ($socialParts['path'] ?? ''), '/') : '';
        @endphp
        <div class="a-ord-item">
          <div style="min-width:0">
            <div class="a-ord-item__name">{{ $item->holder_name ?: '—' }}@if($item->holder_gender) <span class="a-ord-chip">{{ ucwords(str_replace('_', ' ', (string) $item->holder_gender)) }}</span>@endif</div>
            <div class="a-ord-item__ticket">{{ $item->ticket_name }}@if((int) $item->quantity > 1) <span class="a-ord-chip">× {{ $item->quantity }}</span>@endif</div>
            <ul class="a-ord-contact">
              @if($item->holder_email)<li><i class="fa fa-envelope" aria-hidden="true"></i><a href="mailto:{{ $item->holder_email }}">{{ $item->holder_email }}</a></li>@endif
              @if($item->holder_phone)<li><i class="fa fa-phone" aria-hidden="true"></i><a href="tel:{{ preg_replace('/[^\d+]/', '', (string) $item->holder_phone) }}">{{ $item->holder_phone }}</a></li>@endif
              @if($socialProfileLink)<li><i class="fa fa-globe" aria-hidden="true"></i><a href="{{ $socialProfileLink }}" target="_blank" rel="noopener noreferrer" title="{{ $socialProfileRaw }}">{{ $socialProfileShort }}</a></li>@endif
              @if(! $item->holder_email && ! $item->holder_phone && ! $socialProfileLink)<li class="a-muted">No contact details.</li>@endif
            </ul>
            @if($item->issuedTickets->isNotEmpty())
              <div class="a-ord-issued">
                @foreach($item->issuedTickets->sortBy('seat_index') as $issuedTicket)
                  @if($issuedTicket->dashboardTicket)
                    <a href="{{ route('admin.tickets.show', $issuedTicket->dashboardTicket) }}" target="_blank" rel="noopener noreferrer"><i class="fa fa-ticket"></i> #{{ $issuedTicket->ticket_number }}</a>
                  @else
                    <span><i class="fa fa-ticket"></i> #{{ $issuedTicket->ticket_number }}</span>
                  @endif
                @endforeach
              </div>
            @else
              <div class="a-ord-item__meta" style="margin-top:10px"><i class="fa fa-ticket" aria-hidden="true"></i> No ticket numbers generated yet</div>
            @endif
          </div>
          <div class="a-ord-item__price">{{ $money($item->line_total) }}<small>Qty × {{ $item->quantity }}</small></div>
        </div>
      @empty
        <p class="a-muted">No attendee details.</p>
      @endforelse
    </x-admin.card>

    @php
        $primary = $attempts->firstWhere('status', 'paid') ?? $attempts->sortByDesc('id')->first();
        $canTx = auth()->user()->can('payments.transactions.view');
        $canInq = auth()->user()->can('payments.transactions.inquire') && \App\Orders\OrderAccess::canManage($order, auth()->user());
    @endphp
    <x-admin.card title="Payment Information" subtitle="Our order reference is separate from every provider reference.">
        @if(! $primary)
            <x-admin.empty icon="fa-credit-card" title="No online payment yet" text="Online payment attempts appear when the customer starts paying." />
        @else
        <dl class="a-kv">
            <div><dt>Payment provider</dt><dd>{{ ucfirst((string) $primary->provider) }}</dd></div>
            <div><dt>Payment method</dt><dd>{{ $paymentMethodLabel ?: ($primary->method_code ?: '—') }}</dd></div>
            <div><dt>Order reference</dt><dd>#{{ $order->order_number }}</dd></div>
            <div><dt>Payment reference</dt><dd>{{ $primary->internal_reference ?: 'Legacy attempt' }}@if($primary->internal_reference) <button type="button" class="a-btn a-btn--ghost a-btn--sm" data-copy-text="{{ $primary->internal_reference }}" aria-label="Copy payment reference"><i class="fa fa-copy"></i></button>@endif</dd></div>
            <div><dt>Provider transaction ID</dt><dd>{{ $primary->transaction_id ?: '—' }}@if($primary->transaction_id) <button type="button" class="a-btn a-btn--ghost a-btn--sm" data-copy-text="{{ $primary->transaction_id }}" aria-label="Copy transaction ID"><i class="fa fa-copy"></i></button>@endif</dd></div>
            @if($primary->provider_reference)<div><dt>Provider reference number</dt><dd>{{ $primary->provider_reference }}</dd></div>@endif
            <div><dt>{{ $primary->status === 'paid' ? 'Paid amount' : 'Amount requested' }}</dt><dd>{{ $money($primary->amount) }}</dd></div>
            <div><dt>Payment date</dt><dd>{{ ($primary->paid_at ?? $primary->settled_at)?->format('d M Y, H:i') ?? '—' }}</dd></div>
            <div><dt>Current status</dt><dd><x-admin.status :status="$primary->displayStatus()" kind="payment" /></dd></div>
            <div><dt>Last verification</dt><dd>{{ $primary->last_checked_at?->diffForHumans() ?? 'never checked' }}</dd></div>
        </dl>
        <div class="a-btn-group" style="margin-top:12px;flex-wrap:wrap">
            @if($canInq)<form method="POST" action="{{ route('admin.payments.transactions.inquire', $primary) }}" data-a-loading>@csrf<button class="a-btn a-btn--outline" type="submit"><i class="fa fa-rotate"></i> Check payment status</button></form>@endif
            @if($canTx)<a class="a-btn a-btn--outline" href="{{ route('admin.payments.transactions.show', $primary) }}">View transaction details</a>@endif
        </div>
        @endif
        @can('payments.refunds.create')
        @php $paidAttempts = $attempts->where('status', 'paid'); @endphp
        <div class="a-btn-group" style="margin-top:12px;flex-wrap:wrap">
            @foreach($paidAttempts as $pa)@include('admin.orders._refund-actions', ['attempt' => $pa])@endforeach
            @if($paidAttempts->isEmpty() && in_array($order->status, ['paid', 'partially_refunded'], true))@include('admin.orders._refund-actions', ['attempt' => null])@endif
        </div>
        @endcan
    </x-admin.card>

    <x-admin.card flush title="Payment Attempts History" :subtitle="$attempts->count().' attempt(s) — a retry never overwrites an earlier attempt'">
      @if($attempts->isEmpty())
        <x-admin.empty icon="fa-credit-card" title="No payment attempts" text="Attempts appear when the customer starts paying online." />
      @else
        <x-admin.table stack compact caption="Payment attempts">
          <thead><tr><th>Reference</th><th>Gateway</th>@if($canSeeAccounts)<th>Account</th>@endif<th>Provider ID</th><th class="a-num">Amount</th><th>Status</th><th>Date</th></tr></thead>
          <tbody>
          @foreach($attempts as $a)
            <tr>
              <td data-label="Reference">@if($canTx)<a class="a-table__main" href="{{ route('admin.payments.transactions.show', $a) }}">{{ $a->internal_reference ?: '#'.$a->id }}</a>@else{{ $a->internal_reference ?: '#'.$a->id }}@endif</td>
              <td data-label="Gateway">{{ ucfirst((string) $a->provider) }}<span class="a-table__sub">{{ $a->method_code }}</span></td>
              @if($canSeeAccounts)<td data-label="Account">{{ $a->integrationAccount?->name ?? 'Legacy' }}</td>@endif
              <td data-label="Provider ID" class="a-wrap"><span class="a-table__sub" style="color:var(--a-ink)">{{ $a->transaction_id ?: ($a->reference ?: '—') }}</span>@if($a->failure_reason)<span class="a-table__sub">{{ $a->failure_reason }}</span>@endif</td>
              <td data-label="Amount" class="a-num">{{ $money($a->amount) }}</td>
              <td data-label="Status"><x-admin.status :status="$a->displayStatus()" kind="payment" /></td>
              <td data-label="Date">{{ $a->created_at?->format('d M Y, H:i') }}</td>
            </tr>
          @endforeach
          </tbody>
        </x-admin.table>
      @endif
    </x-admin.card>

    @can('payments.refunds.view')
    @include('admin.orders._refund-panel')
    @endcan

    <x-admin.card title="Internal Notes" :subtitle="$notes->count().' notes'">
      @forelse($notes as $note)
        <div class="a-ord-log">
          <strong>{{ $note->causer?->name ?? 'System' }}</strong>
          <div>{{ data_get($note->properties, 'body', $note->description) }}</div>
          @if(data_get($note->properties, 'send_to_customer'))<x-admin.badge variant="success"><i class="fa fa-envelope"></i> Sent to customer</x-admin.badge>@endif
          <small>{{ $note->created_at?->format('d M Y, H:i') }}</small>
        </div>
      @empty
        <p class="a-muted">No notes yet.</p>
      @endforelse

      <form class="a-ord-note" method="POST" action="{{ route('admin.orders.notes.store', $order) }}" data-a-loading style="margin-top:16px">
        @csrf
        <label class="form-label" for="orderNoteBody">Add a note</label>
        <textarea id="orderNoteBody" class="form-control" name="body" placeholder="Add an internal note about this order…">{{ old('body') }}</textarea>
        <div class="form-check" style="margin-top:10px">
          <input class="form-check-input" id="orderNoteSend" type="checkbox" name="send_to_customer" value="1" {{ old('send_to_customer') ? 'checked' : '' }}>
          <label class="form-check-label" for="orderNoteSend">Sent to customer by email</label>
        </div>
        <button type="submit" class="a-btn a-btn--dark" style="margin-top:12px"><i class="fa fa-paper-plane"></i> Add Note</button>
      </form>
    </x-admin.card>
  </div>

  <div class="a-ord-col">
    @if($canSeeAccounts)
    <x-admin.card title="Merchant account" subtitle="Where this order's online payments are collected.">
        <dl class="a-kv">
            <div><dt>Pinned account</dt><dd>@if($pinnedAccount)<strong>{{ $pinnedAccount->name }}</strong> <span class="a-muted">{{ ucfirst($pinnedAccount->provider) }} · {{ $pinnedAccount->environment }}</span>@else<span class="a-muted">Legacy configuration</span>@endif</dd></div>
        </dl>
        @if($switchTargets->isNotEmpty())
        <form method="POST" action="{{ route('admin.orders.payment-account.switch', $order) }}" data-a-loading data-a-confirm="Switch the merchant account? Every open payment is checked at its gateway first; the switch is refused if money may be collected." data-a-confirm-ok="Switch">
            @csrf
            <x-admin.field label="Switch to" name="integration_account_id" for="pa-account"><select id="pa-account" class="form-select" name="integration_account_id">@foreach($switchTargets as $t)<option value="{{ $t->id }}">{{ $t->name }} · {{ ucfirst($t->provider) }}</option>@endforeach</select></x-admin.field>
            <x-admin.field label="Reason (required)" name="reason" for="pa-reason"><input id="pa-reason" class="form-control" name="reason" minlength="5" maxlength="300" required></x-admin.field>
            @error('account')<div class="a-error" role="alert">{{ $message }}</div>@enderror
            <div class="a-form-actions"><button class="a-btn a-btn--danger" type="submit">Switch merchant account</button></div>
        </form>
        @endif
    </x-admin.card>
    @endif

    {{-- Assignment is not shown on the order itself: assigning is done from the orders list. --}}

    <x-admin.card title="Customer" subtitle="The buyer record attached to this order.">
      <div class="a-ord-who">
        <div class="a-ord-avatar" aria-hidden="true">{{ $customerInitials ?: 'NA' }}</div>
        <div style="min-width:0">
          <div class="a-table__main">
            @if($order->customer)<a href="{{ route('admin.customers.show', $order->customer) }}">{{ $customerName }}</a>@else{{ $customerName }}@endif
          </div>
          <span class="a-table__sub">{{ $order->customer?->email ?: '-' }}</span>
          <span class="a-table__sub">{{ $order->customer?->phone ?: '-' }}</span>
        </div>
      </div>
    </x-admin.card>

    <x-admin.card title="Price Breakdown">
      <div class="a-ord-totals" style="margin-top:0;padding-top:0;border-top:0">
        <div><span>Subtotal</span><strong>{{ $money($subtotal) }}</strong></div>
        @if($discount > 0)
          <div><span>Promo discount{{ $order->promo_code ? ' ('.$order->promo_code.')' : '' }}</span><strong>- {{ $money($discount) }}</strong></div>
        @elseif($order->promo_code)
          <div><span>Promo code</span><strong>{{ $order->promo_code }}</strong></div>
        @endif
        @if($extraFees > 0)
          <div><span>Fees</span><strong>{{ $money($extraFees) }}</strong></div>
        @endif
        <div class="is-grand"><span>Total</span><strong>{{ $money($order->total_amount) }}</strong></div>
      </div>
    </x-admin.card>

    <x-admin.card title="Activity">
      <ol class="a-ord-timeline">
        @foreach($activityTimeline as $activity)
          <li class="{{ $activity['done'] ? 'is-done' : '' }}">
            <b>{{ $activity['label'] }}</b>
            <span>{{ $activity['at'] ? $activity['at']->format('d M Y, H:i') : '—' }}</span>
          </li>
        @endforeach
      </ol>
    </x-admin.card>

    <x-admin.card title="Order History" :subtitle="$history->count().' events'">
      @forelse($history as $log)
        <div class="a-ord-log">
          {{ $log->description }} @if($log->causer)<strong>by {{ $log->causer->name }}</strong>@endif
          @if(filled(data_get($log->properties, 'from_status')) || filled(data_get($log->properties, 'to_status')))
            <small>Status: <strong>{{ ucwords(str_replace('_', ' ', (string) data_get($log->properties, 'from_status', 'N/A'))) }}</strong> <i class="fa fa-arrow-right"></i> <strong>{{ ucwords(str_replace('_', ' ', (string) data_get($log->properties, 'to_status', 'N/A'))) }}</strong></small>
          @endif
          @if(filled(data_get($log->properties, 'transaction_id')) || filled(data_get($log->properties, 'transaction_url')) || filled(data_get($log->properties, 'payment_status')))
            <small>
              @if(filled(data_get($log->properties, 'payment_status')))Payment: <strong>{{ strtoupper((string) data_get($log->properties, 'payment_status')) }}</strong> @endif
              @if(filled(data_get($log->properties, 'transaction_id')))Ref: <strong>{{ data_get($log->properties, 'transaction_id') }}</strong> @endif
              @if(filled(data_get($log->properties, 'transaction_url')))<a href="{{ data_get($log->properties, 'transaction_url') }}" target="_blank" rel="noopener noreferrer">Open transaction</a>@endif
            </small>
          @endif
          <small>{{ $log->created_at?->format('d M Y, H:i') }}</small>
        </div>
      @empty
        <p class="a-muted">No history yet.</p>
      @endforelse
    </x-admin.card>
  </div>
</div>

@can('update_status')
  <div class="a-ord-modal" id="statusModal" aria-hidden="true">
    <div class="a-ord-modal__box" role="dialog" aria-modal="true" aria-labelledby="statusModalTitle">
      <h2 id="statusModalTitle">Update Order Status</h2>
      <form method="POST" action="{{ route('admin.orders.status.update', $order) }}">
        @csrf
        <label class="form-label" for="statusModalSelect">Status</label>
        <select id="statusModalSelect" class="form-select" name="status">
          @foreach($statusOptions as $value => $label)
            <option value="{{ $value }}" {{ $order->status === $value ? 'selected' : '' }}>{{ $label }}</option>
          @endforeach
        </select>
        <div class="a-btn-group" style="justify-content:flex-end;margin-top:16px">
          <button type="button" class="a-btn a-btn--outline" id="cancelStatusModalBtn">Cancel</button>
          <button type="submit" class="a-btn a-btn--primary">Save Status</button>
        </div>
        <button type="button" id="closeStatusModalBtn" class="a-sr-only">Close</button>
      </form>
    </div>
  </div>
@endcan

<script>
(function () {
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

  const statusModal = document.getElementById('statusModal');
  const openStatusModalBtn = document.getElementById('openStatusModalBtn');
  const closeStatusModalBtn = document.getElementById('closeStatusModalBtn');
  const cancelStatusModalBtn = document.getElementById('cancelStatusModalBtn');

  if (!statusModal || !openStatusModalBtn) {
    return;
  }

  const closeModal = () => {
    statusModal.classList.remove('show');
    statusModal.setAttribute('aria-hidden', 'true');
  };

  openStatusModalBtn.addEventListener('click', () => {
    statusModal.classList.add('show');
    statusModal.setAttribute('aria-hidden', 'false');
  });

  [closeStatusModalBtn, cancelStatusModalBtn].forEach((btn) => {
    if (!btn) {
      return;
    }

    btn.addEventListener('click', closeModal);
  });

  statusModal.addEventListener('click', (event) => {
    if (event.target === statusModal) {
      closeModal();
    }
  });
})();
</script>
@endsection
@push('scripts')<script>document.querySelectorAll('[data-copy-text]').forEach(function (b) { b.addEventListener('click', function () { navigator.clipboard.writeText(b.dataset.copyText); b.innerHTML = '<i class="fa fa-check"></i>'; setTimeout(function () { b.innerHTML = '<i class="fa fa-copy"></i>'; }, 1200); }); });</script>@endpush
