@extends('admin.master')

@section('content')
<div class="content">
    <div class="block block-rounded">
        <div class="block-header block-header-default">
            <h3 class="block-title">Affiliate</h3>
        </div>
        <div class="block-content">
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
                            @php
                                $affiliateLink = $affiliate->affiliate_code
                                    ? route('front.customer.register', ['ref' => $affiliate->affiliate_code])
                                    : null;
                                $paidRevenue = (float) ($affiliate->affiliate_paid_revenue ?? 0);
                            @endphp
                            <tr>
                                <td>{{ $affiliate->id }}</td>
                                <td>
                                    <strong>{{ $affiliate->name }}</strong>
                                    <div class="text-muted small">{{ $affiliate->email }}</div>
                                </td>
                                <td>
                                    @if($affiliateLink)
                                        <a href="{{ $affiliateLink }}" target="_blank" class="small">{{ $affiliateLink }}</a>
                                    @else
                                        <span class="text-muted">Not generated yet</span>
                                    @endif
                                </td>
                                <td>{{ $affiliate->referred_users_count }}</td>
                                <td>{{ $affiliate->affiliate_orders_count }}</td>
                                <td>{{ number_format($paidRevenue, 2) }} EGP</td>
                                <td class="text-end">
                                    <form method="POST" action="{{ route('admin.affiliates.generate-link', $affiliate) }}" class="d-inline">
                                        @csrf
                                        <button type="submit" class="btn btn-sm btn-alt-success">
                                            {{ $affiliate->affiliate_code ? 'Regenerate Link' : 'Generate Link' }}
                                        </button>
                                    </form>
                                    <a href="{{ route('admin.affiliates.show', $affiliate) }}" class="btn btn-sm btn-alt-primary">
                                        <i class="fa fa-eye"></i>
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="text-center py-4 text-muted">No users found.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="mt-3">{{ $affiliates->links() }}</div>
        </div>
    </div>
</div>
@endsection
