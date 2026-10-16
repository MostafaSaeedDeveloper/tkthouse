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
      @if($latestAttempt)<x-admin.status :status="$latestAttempt->status" kind="payment" />
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
          <div><dt>Latest payment attempt</dt><dd><x-admin.status :status="$latestAttempt->status" kind="payment" /></dd></div>
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
        @endphp
        <div class="a-ord-item">
          <div style="min-width:0">
            <div class="a-ord-item__name">{{ $item->holder_name ?: '—' }}</div>
            <div class="a-ord-item__meta">{{ $item->ticket_name }}@if((int) $item->quantity > 1) × {{ $item->quantity }}@endif</div>
            <div class="a-ord-item__meta">{{ $item->holder_email ?: '-' }} — {{ $item->holder_phone ?: '-' }}@if($item->holder_gender) — {{ ucwords(str_replace('_', ' ', (string) $item->holder_gender)) }}@endif</div>
            @if($socialProfileLink)
              <a href="{{ $socialProfileLink }}" target="_blank" rel="noopener noreferrer" class="a-ord-item__meta"><i class="fa fa-globe"></i> {{ $socialProfileRaw }}</a>
            @endif
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
              <div class="a-ord-item__meta" style="margin-top:8px">No generated ticket numbers yet.</div>
            @endif
          </div>
          <div class="a-ord-item__price">{{ $money($item->line_total) }}<small>Qty × {{ $item->quantity }}</small></div>
        </div>
      @empty
        <p class="a-muted">No attendee details.</p>
      @endforelse
    </x-admin.card>

    <x-admin.card flush title="Payment Attempts" :subtitle="$attempts->count().' attempt(s)'">
      @if($attempts->isEmpty())
        <x-admin.empty icon="fa-credit-card" title="No payment attempts" text="Attempts appear when the customer starts paying online." />
      @else
        <x-admin.table stack compact caption="Payment attempts">
          <thead><tr><th>Gateway</th><th>Reference</th><th class="a-num">Amount</th><th>Status</th><th>Date</th></tr></thead>
          <tbody>
          @foreach($attempts as $a)
            <tr>
              <td data-label="Gateway">{{ ucfirst((string) $a->provider) }}<span class="a-table__sub">{{ $a->method_code }}</span></td>
              <td data-label="Reference" class="a-wrap"><span class="a-table__sub" style="color:var(--a-ink)">{{ $a->transaction_id ?: ($a->reference ?: '—') }}</span>@if($a->failure_reason)<span class="a-table__sub">{{ $a->failure_reason }}</span>@endif</td>
              <td data-label="Amount" class="a-num">{{ $money($a->amount) }}</td>
              <td data-label="Status"><x-admin.status :status="$a->status" kind="payment" /></td>
              <td data-label="Date">{{ $a->created_at?->format('d M Y, H:i') }}</td>
            </tr>
          @endforeach
          </tbody>
        </x-admin.table>
      @endif
    </x-admin.card>

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
    @if($assignmentOn)
    <x-admin.card title="Assignment" subtitle="Who is responsible for this order.">
        <dl class="a-kv">
            <div><dt>Assigned to</dt><dd>@if($order->assignedUser)<strong>{{ $order->assignedUser->name }}</strong>@else<x-admin.badge variant="warning">Unassigned</x-admin.badge>@endif</dd></div>
            @if($order->assigned_at)<div><dt>Assigned by</dt><dd>{{ $order->assignedBy?->name ?? 'Automatic' }} · {{ $order->assigned_at->format('d M Y, H:i') }}</dd></div>@endif
            @if($order->sold_at)<div><dt>Sale attributed to</dt><dd>{{ $order->soldBy?->name ?? 'Unattributed' }} · {{ $order->sold_at->format('d M Y, H:i') }}</dd></div>@endif
        </dl>
        @if($canAssign)
        <form method="POST" action="{{ route('admin.orders.assign', $order) }}" data-a-loading class="a-form-grid">
            @csrf
            <x-admin.field label="Change assignee" name="assignee_id" for="oa-assignee">
                <select id="oa-assignee" class="form-select" name="assignee_id"><option value="">Unassigned</option>@foreach($assignees as $a)<option value="{{ $a->id }}" @selected($order->assigned_user_id === $a->id)>{{ $a->name }}</option>@endforeach</select>
            </x-admin.field>
            <x-admin.field label="Reason (optional)" name="reason" for="oa-reason"><input id="oa-reason" class="form-control" name="reason" maxlength="300"></x-admin.field>
            <p class="a-hint is-full">Reassigning never changes the payment account or any payment attempt of this order.</p>
            <div class="a-form-actions is-full"><button class="a-btn a-btn--primary" type="submit">Save assignee</button></div>
        </form>
        @endif
        @if($assignmentHistory->isNotEmpty())
            <h3 class="a-m-section__title" style="margin-top:16px">History</h3>
            <ul class="a-timeline">
                @foreach($assignmentHistory as $h)
                    <li><strong>{{ $h->meta['to'] ?? 'Unassigned' }}</strong> <span class="a-muted">from {{ $h->meta['from'] ?? 'Unassigned' }}</span><br>
                        <span class="a-table__sub">{{ $h->created_at?->format('d M Y, H:i') }} · by {{ $h->meta['by'] ?? 'system' }} · {{ str_replace('_', ' ', $h->source) }}@if($h->reason) · “{{ $h->reason }}”@endif</span></li>
                @endforeach
            </ul>
        @endif
    </x-admin.card>
    @endif

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
