@extends('admin.master')
@section('title', 'Affiliate: '.$affiliate->name)


@section('content')
<x-admin.page-header :title="'Affiliate: '.$affiliate->name" :subtitle="$affiliate->email" :crumbs="['Dashboard' => route('admin.dashboard'), 'Affiliates' => route('admin.affiliates.index'), $affiliate->name]">
    <a href="{{ route('admin.affiliates.index') }}" class="a-btn a-btn--ghost"><i class="fa fa-arrow-left"></i> Back</a>
</x-admin.page-header>

<x-admin.flash />

<div class="a-grid a-grid--4" style="margin-bottom:20px">
    <x-admin.kpi label="Referred Users" :value="$stats['referred_users']" icon="fa-user-plus" />
    <x-admin.kpi label="Orders" :value="$stats['orders_total']" icon="fa-receipt" />
    <x-admin.kpi label="Paid Orders" :value="$stats['orders_paid']" icon="fa-circle-check" />
    <x-admin.kpi label="Paid Revenue" :value="number_format($stats['revenue_paid'], 2).' EGP'" icon="fa-wallet" />
</div>

<x-admin.card title="Affiliate Link" style="margin-bottom:20px">
    @if($affiliateLink)
        <dl class="a-kv">
            <div><dt>Full Affiliate Link</dt><dd class="a-cus-link" style="justify-content:flex-end"><a href="{{ $affiliateLink }}" target="_blank" rel="noopener">{{ $affiliateLink }}</a>
                <button type="button" class="a-btn a-btn--outline a-btn--sm js-copy-link" data-copy-text="{{ $affiliateLink }}">Copy</button></dd></div>
            <div><dt>Short Affiliate Link</dt><dd class="a-cus-link" style="justify-content:flex-end"><a href="{{ $shortAffiliateLink }}" target="_blank" rel="noopener">{{ $shortAffiliateLink }}</a>
                <button type="button" class="a-btn a-btn--outline a-btn--sm js-copy-link" data-copy-text="{{ $shortAffiliateLink }}">Copy</button></dd></div>
            <div><dt>Target</dt><dd><code>{{ $affiliate->affiliate_target_url ?: '/account/register' }}</code></dd></div>
        </dl>
    @else
        <p class="a-muted">No affiliate link generated yet.</p>
    @endif
    <form method="POST" action="{{ route('admin.affiliates.store') }}" style="margin-top:16px" data-a-loading>
        @csrf
        <input type="hidden" name="user_id" value="{{ $affiliate->id }}">
        <div class="a-field">
            <label class="form-label" for="target_url">Target Link</label>
            <input id="target_url" name="target_url" class="form-control" value="{{ old('target_url', $affiliate->affiliate_target_url ?: '/account/register') }}" placeholder="/events/my-event">
        </div>
        <button class="a-btn a-btn--dark" type="submit" style="margin-top:12px">Update Link</button>
    </form>
</x-admin.card>

<x-admin.card flush title="Referred Users" style="margin-bottom:20px">
    @if($referredUsers->isEmpty())
        <x-admin.empty icon="fa-user-plus" title="No referred users yet" />
    @else
    <x-admin.table stack caption="Referred users">
        <thead><tr><th>Name</th><th>Email</th><th>Joined At</th></tr></thead>
        <tbody>
        @foreach($referredUsers as $user)
            <tr>
                <td data-label="Name">{{ $user->name }}</td>
                <td data-label="Email" class="a-wrap">{{ $user->email }}</td>
                <td data-label="Joined At">{{ $user->created_at?->format('Y-m-d H:i') }}</td>
            </tr>
        @endforeach
        </tbody>
    </x-admin.table>
    <x-admin.pager :paginator="$referredUsers" />
    @endif
</x-admin.card>

<x-admin.filters :action="route('admin.affiliates.show', $affiliate)" :reset="route('admin.affiliates.show', $affiliate)">
    <div class="a-field"><label class="form-label" for="afoEvent">Event</label>
        <select name="event_id" id="afoEvent" class="form-select no-select2">
            <option value="">All Events</option>
            @foreach($events as $event)
                <option value="{{ $event->id }}" @selected($filters['event_id'] == $event->id)>{{ $event->name }}</option>
            @endforeach
        </select></div>
    <div class="a-field"><label class="form-label" for="afoFrom">From</label>
        <input type="date" id="afoFrom" name="date_from" class="form-control" value="{{ $filters['date_from'] }}"></div>
    <div class="a-field"><label class="form-label" for="afoTo">To</label>
        <input type="date" id="afoTo" name="date_to" class="form-control" value="{{ $filters['date_to'] }}"></div>
</x-admin.filters>

@if($filters['event_id'] || $filters['date_from'] || $filters['date_to'])
    <x-admin.alert type="info">
        Showing filtered results &mdash;
        <strong>{{ $stats['orders_total'] }}</strong> orders,
        <strong>{{ $stats['orders_paid'] }}</strong> paid,
        <strong>{{ number_format($stats['revenue_paid'], 2) }} EGP</strong> revenue.
    </x-admin.alert>
@endif

<x-admin.card flush title="Orders via Affiliate">
    @if($orders->isEmpty())
        <x-admin.empty icon="fa-receipt" title="No orders found" />
    @else
    <x-admin.table stack caption="Orders via affiliate">
        <thead><tr><th>Order #</th><th>Buyer</th><th>Status</th><th>Payment Method</th><th class="a-num">Total</th><th class="a-num">View</th></tr></thead>
        <tbody>
        @foreach($orders as $order)
            <tr>
                <td data-label="Order #"><a class="a-table__main" href="{{ route('admin.orders.show', $order) }}">{{ $order->order_number }}</a></td>
                <td data-label="Buyer">{{ $order->user?->name ?? '-' }}</td>
                <td data-label="Status"><x-admin.status :status="$order->status" kind="order" /></td>
                <td data-label="Payment Method">{{ ucfirst(str_replace('_', ' ', $order->payment_method)) }}</td>
                <td data-label="Total" class="a-num">{{ number_format((float) $order->total_amount, 2) }} EGP</td>
                <td data-label="View"><div class="a-table__actions"><a href="{{ route('admin.orders.show', $order) }}" class="a-btn a-btn--outline a-btn--sm" aria-label="View order"><i class="fa fa-eye"></i></a></div></td>
            </tr>
        @endforeach
        </tbody>
    </x-admin.table>
    <x-admin.pager :paginator="$orders" />
    @endif
</x-admin.card>
@endsection


@push('scripts')
<script>
(() => {
  const copyButtons = document.querySelectorAll('.js-copy-link');

  copyButtons.forEach((button) => {
    button.addEventListener('click', async () => {
      const originalText = button.textContent;
      const textToCopy = button.dataset.copyText || '';

      try {
        await navigator.clipboard.writeText(textToCopy);
        button.textContent = 'Copied!';
      } catch (error) {
        const textArea = document.createElement('textarea');
        textArea.value = textToCopy;
        document.body.appendChild(textArea);
        textArea.select();
        document.execCommand('copy');
        document.body.removeChild(textArea);
        button.textContent = 'Copied!';
      }

      setTimeout(() => {
        button.textContent = originalText;
      }, 1200);
    });
  });
})();
</script>
@endpush
