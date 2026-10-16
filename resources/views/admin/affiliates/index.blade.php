@extends('admin.master')
@section('title', 'Affiliates')


@section('content')
<x-admin.page-header title="Affiliates" subtitle="Portal users with a referral link, and the orders they bring in." :crumbs="['Dashboard' => route('admin.dashboard'), 'Affiliates']">
    <a href="{{ route('admin.promo-codes.index') }}" class="a-btn a-btn--outline"><i class="fa fa-tags"></i> Promo codes</a>
    <a href="{{ route('admin.affiliates.create') }}" class="a-btn a-btn--primary"><i class="fa fa-plus"></i> Add New Link</a>
</x-admin.page-header>

<x-admin.flash />

<x-admin.filters :action="route('admin.affiliates.index')" :reset="route('admin.affiliates.index')">
    <x-admin.select label="Event" name="event_id" id="afEvent" :options="$events->pluck('name', 'id')->all()" :value="$filters['event_id']" placeholder="All Events" class="no-select2" />
    <x-admin.input label="From" name="date_from" id="afFrom" type="date" :value="$filters['date_from']" />
    <x-admin.input label="To" name="date_to" id="afTo" type="date" :value="$filters['date_to']" />
</x-admin.filters>

<x-admin.card flush title="Affiliate links" :subtitle="$affiliates->total().' total'">
    @if($affiliates->isEmpty())
        <x-admin.empty icon="fa-link" title="No affiliate links yet" text="Click Add New Link to create one.">
            <a href="{{ route('admin.affiliates.create') }}" class="a-btn a-btn--primary a-btn--sm">Add New Link</a>
        </x-admin.empty>
    @else
    <x-admin.table stack caption="Affiliates">
        <thead><tr><th>User</th><th>Affiliate link</th><th class="a-num">Referred</th><th class="a-num">Orders</th><th class="a-num">Paid revenue</th><th class="a-num"><span class="a-sr-only">Actions</span></th></tr></thead>
        <tbody>
        @foreach($affiliates as $affiliate)
            @php $paidRevenue = (float) ($affiliate->affiliate_paid_revenue ?? 0); @endphp
            <tr>
                <td data-label="User"><a class="a-table__main a-trunc" href="{{ route('admin.affiliates.show', $affiliate) }}">{{ $affiliate->name }}</a><span class="a-table__sub a-trunc" title="{{ $affiliate->email }}">{{ $affiliate->email }}</span></td>
                <td data-label="Affiliate link"><div class="a-cus-link">
                    <a class="a-trunc a-m-trunc-lg" href="{{ $affiliate->generated_short_affiliate_link }}" target="_blank" rel="noopener" title="{{ $affiliate->generated_short_affiliate_link }}">{{ $affiliate->generated_short_affiliate_link }}</a>
                    <button type="button" class="a-btn a-btn--outline a-btn--sm js-copy-link" data-copy-text="{{ $affiliate->generated_short_affiliate_link }}">Copy</button>
                </div></td>
                <td data-label="Referred" class="a-num">{{ number_format($affiliate->referred_users_count) }}</td>
                <td data-label="Orders" class="a-num">{{ number_format($affiliate->affiliate_orders_count) }}</td>
                <td data-label="Paid revenue" class="a-num"><b>{{ number_format($paidRevenue, 2) }}</b> <span class="a-muted">EGP</span></td>
                <td data-label=""><div class="a-table__actions">
                    <a href="{{ route('admin.affiliates.show', $affiliate) }}" class="a-btn a-btn--outline a-btn--sm">View</a>
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
