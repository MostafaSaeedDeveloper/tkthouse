@extends('admin.master')
@section('title', 'Order #'.(preg_replace('/\D+/', '', (string) $order->order_number) ?: $order->order_number))


@section('content')
@php
  $displayOrderNumber = preg_replace('/\D+/', '', (string) $order->order_number) ?: $order->order_number;
  $customerName = $order->customer?->full_name ?: 'N/A';
  $customerInitials = collect(explode(' ', $customerName))->filter()->map(fn($p)=>mb_substr($p,0,1))->take(2)->implode('');
  $paymentLink = $order->payment_link_token ? route('front.orders.payment', ['order' => $order, 'token' => $order->payment_link_token]) : null;
  $money = fn ($n) => number_format((float) $n, 2).' EGP';
  $attempts = $order->paymentAttempts()->latest()->get();
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

<div class="a-ord-grid">
  <div class="a-ord-col">
    <x-admin.card title="Order Summary">
      <x-slot:actions><x-admin.status :status="$order->status" kind="order" /></x-slot:actions>
      <dl class="a-kv">
        <div><dt>Order #</dt><dd>{{ $displayOrderNumber }}</dd></div>
        <div><dt>Date</dt><dd>{{ $order->created_at?->format('d M Y, H:i') }}</dd></div>
        <div><dt>Payment Method</dt><dd>{{ $paymentMethodLabel }}</dd></div>
        @can('showing_orders')
          <div><dt>Show in Stats</dt><dd>{{ $order->exclude_from_statistics ? 'No (Excluded)' : 'Yes' }}</dd></div>
        @endcan
        <div><dt>Promo Code</dt><dd>{{ $order->promo_code ?: '-' }}</dd></div>
        <div><dt>Subtotal</dt><dd>{{ $money($order->subtotal_amount ?: $order->total_amount) }}</dd></div>
        <div><dt>Discount</dt><dd>{{ $money($order->discount_amount) }}</dd></div>
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

    <x-admin.card title="Tickets" :subtitle="$order->items->count().' ticket(s)'">
      @forelse($order->items as $item)
        @php
          $ticketName = trim((string) $item->ticket_name);
          $eventName = str_contains($ticketName, ' - ') ? trim((string) str($ticketName)->before(' - ')) : $ticketName;
          $ticketType = trim((string) \Illuminate\Support\Str::afterLast($ticketName, ' - '));
          $normalizedType = \Illuminate\Support\Str::lower($ticketType);
          $ticketTypeColor = ['early bird' => '#6c757d', 'regular' => '#0d6efd', 'phase 1' => '#0d6efd', 'phase 2' => '#6f42c1', 'vip' => '#b58900'][$normalizedType] ?? '#6c757d';
          $socialProfileRaw = trim((string) $item->holder_social_profile);
          $socialProfileLink = $socialProfileRaw !== ''
            ? (\Illuminate\Support\Str::startsWith($socialProfileRaw, ['http://', 'https://']) ? $socialProfileRaw : 'https://' . ltrim($socialProfileRaw, '/'))
            : null;
        @endphp
        <div class="a-ord-item">
          <div style="min-width:0">
            <div class="a-ord-item__name">{{ $eventName }}
              @if($ticketType !== '' && $ticketType !== $eventName)<span class="a-ord-tag" style="background-color: {{ $ticketTypeColor }}">{{ $ticketType }}</span>@endif
            </div>
            <div class="a-ord-item__meta"><strong>{{ $item->holder_name }}</strong><br>{{ $item->holder_email }} — {{ $item->holder_phone ?: '-' }} — {{ ucwords(str_replace('_', ' ', (string) ($item->holder_gender ?: '-'))) }}</div>
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
        <x-admin.empty icon="fa-ticket" title="No tickets found" />
      @endforelse

      <div class="a-ord-totals">
        <div><span>Subtotal</span><strong>{{ $money($order->subtotal_amount ?: $order->total_amount) }}</strong></div>
        @if((float) $order->discount_amount > 0)
          <div><span>Promo Discount{{ $order->promo_code ? ' ('.$order->promo_code.')' : '' }}</span><strong>- {{ $money($order->discount_amount) }}</strong></div>
        @endif
        <div class="is-grand"><span>Total Amount</span><strong>{{ $money($order->total_amount) }}</strong></div>
      </div>
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
        <select id="statusModalSelect" class="form-select no-select2" name="status">
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
