@extends('admin.master')

@section('content')
<div class="content">
    <div class="block block-rounded">
        <div class="block-header block-header-default">
            <h3 class="block-title">Affiliate</h3>
            <div class="block-options">
                <a href="{{ route('admin.affiliates.create') }}" class="btn btn-sm btn-alt-success">
                    <i class="fa fa-plus me-1"></i> Add New Link
                </a>
            </div>
        </div>
        <div class="block-content">
            <form method="GET" action="{{ route('admin.affiliates.index') }}" class="row g-2 mb-3 align-items-end">
                <div class="col-md-4">
                    <label class="form-label mb-1 small">Event</label>
                    <select name="event_id" class="form-select form-select-sm">
                        <option value="">All Events</option>
                        @foreach($events as $event)
                            <option value="{{ $event->id }}" @selected($filters['event_id'] == $event->id)>{{ $event->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label mb-1 small">From</label>
                    <input type="date" name="date_from" class="form-control form-control-sm" value="{{ $filters['date_from'] }}">
                </div>
                <div class="col-md-3">
                    <label class="form-label mb-1 small">To</label>
                    <input type="date" name="date_to" class="form-control form-control-sm" value="{{ $filters['date_to'] }}">
                </div>
                <div class="col-md-2 d-flex gap-1">
                    <button type="submit" class="btn btn-sm btn-alt-primary">Filter</button>
                    <a href="{{ route('admin.affiliates.index') }}" class="btn btn-sm btn-alt-secondary">Reset</a>
                </div>
            </form>

            <div class="table-responsive">
                <table class="table table-bordered table-striped align-middle">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>User</th>
                            <th>Affiliate Link</th>
                            <th>Referred Users</th>
                            <th>Orders</th>
                            <th>Paid Revenue</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($affiliates as $affiliate)
                            @php $paidRevenue = (float) ($affiliate->affiliate_paid_revenue ?? 0); @endphp
                            <tr>
                                <td>{{ $affiliate->id }}</td>
                                <td>
                                    <strong>{{ $affiliate->name }}</strong>
                                    <div class="text-muted small">{{ $affiliate->email }}</div>
                                </td>
                                <td>
                                    <div class="d-flex align-items-center gap-2">
                                        <a href="{{ $affiliate->generated_short_affiliate_link }}" target="_blank" class="small">{{ $affiliate->generated_short_affiliate_link }}</a>
                                        <button type="button" class="btn btn-sm btn-alt-secondary js-copy-link" data-copy-text="{{ $affiliate->generated_short_affiliate_link }}">Copy</button>
                                    </div>
                                </td>
                                <td>{{ $affiliate->referred_users_count }}</td>
                                <td>{{ $affiliate->affiliate_orders_count }}</td>
                                <td>{{ number_format($paidRevenue, 2) }} EGP</td>
                                <td class="text-end">
                                    <a href="{{ route('admin.affiliates.show', $affiliate) }}" class="btn btn-sm btn-alt-primary">
                                        <i class="fa fa-eye"></i>
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="text-center py-4 text-muted">No affiliate links yet. Click Add New Link to create one.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="mt-3">{{ $affiliates->links() }}</div>
        </div>
    </div>
</div>
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
