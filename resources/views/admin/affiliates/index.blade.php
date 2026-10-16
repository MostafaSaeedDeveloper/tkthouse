@extends('admin.master')
@section('title', 'Affiliates')

@push('styles')<link rel="stylesheet" href="{{ asset('admin-v2/orders-customers.css') }}">@endpush

@section('content')
<x-admin.page-header title="Affiliates" subtitle="Portal users with a referral link, and the orders they bring in." :crumbs="['Dashboard' => route('admin.dashboard'), 'Affiliates']">
    <a href="{{ route('admin.promo-codes.index') }}" class="a-btn a-btn--outline"><i class="fa fa-tags"></i> Promo codes</a>
    <a href="{{ route('admin.affiliates.create') }}" class="a-btn a-btn--primary"><i class="fa fa-plus"></i> Add New Link</a>
</x-admin.page-header>

<x-admin.flash />

<x-admin.filters :action="route('admin.affiliates.index')" :reset="route('admin.affiliates.index')">
    <div class="a-field"><label class="form-label" for="afEvent">Event</label>
        <select name="event_id" id="afEvent" class="form-select no-select2">
            <option value="">All Events</option>
            @foreach($events as $event)
                <option value="{{ $event->id }}" @selected($filters['event_id'] == $event->id)>{{ $event->name }}</option>
            @endforeach
        </select></div>
    <div class="a-field"><label class="form-label" for="afFrom">From</label>
        <input type="date" id="afFrom" name="date_from" class="form-control" value="{{ $filters['date_from'] }}"></div>
    <div class="a-field"><label class="form-label" for="afTo">To</label>
        <input type="date" id="afTo" name="date_to" class="form-control" value="{{ $filters['date_to'] }}"></div>
</x-admin.filters>

<x-admin.card flush title="Affiliate links" :subtitle="$affiliates->total().' total'">
    @if($affiliates->isEmpty())
        <x-admin.empty icon="fa-link" title="No affiliate links yet" text="Click Add New Link to create one.">
            <a href="{{ route('admin.affiliates.create') }}" class="a-btn a-btn--primary a-btn--sm">Add New Link</a>
        </x-admin.empty>
    @else
    <x-admin.table stack caption="Affiliates">
        <thead><tr><th>#</th><th>User</th><th>Affiliate Link</th><th class="a-num">Referred Users</th><th class="a-num">Orders</th><th class="a-num">Paid Revenue</th><th class="a-num">Actions</th></tr></thead>
        <tbody>
        @foreach($affiliates as $affiliate)
            @php $paidRevenue = (float) ($affiliate->affiliate_paid_revenue ?? 0); @endphp
            <tr>
                <td data-label="#">{{ $affiliate->id }}</td>
                <td data-label="User" class="a-wrap"><span class="a-table__main">{{ $affiliate->name }}</span><span class="a-table__sub">{{ $affiliate->email }}</span></td>
                <td data-label="Affiliate Link" class="a-wrap"><div class="a-cus-link">
                    <a href="{{ $affiliate->generated_short_affiliate_link }}" target="_blank" rel="noopener">{{ $affiliate->generated_short_affiliate_link }}</a>
                    <button type="button" class="a-btn a-btn--outline a-btn--sm js-copy-link" data-copy-text="{{ $affiliate->generated_short_affiliate_link }}">Copy</button>
                </div></td>
                <td data-label="Referred Users" class="a-num">{{ $affiliate->referred_users_count }}</td>
                <td data-label="Orders" class="a-num">{{ $affiliate->affiliate_orders_count }}</td>
                <td data-label="Paid Revenue" class="a-num">{{ number_format($paidRevenue, 2) }} EGP</td>
                <td data-label="Actions"><div class="a-table__actions">
                    <a href="{{ route('admin.affiliates.show', $affiliate) }}" class="a-btn a-btn--outline a-btn--sm" aria-label="View affiliate"><i class="fa fa-eye"></i></a>
                </div></td>
            </tr>
        @endforeach
        </tbody>
    </x-admin.table>
    <x-admin.pager :paginator="$affiliates" />
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
