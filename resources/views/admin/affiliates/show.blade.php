@extends('admin.master')

@section('content')
<div class="content">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h2 class="h4 mb-1">Affiliate: {{ $affiliate->name }}</h2>
            <div class="text-muted">{{ $affiliate->email }}</div>
        </div>
        <a href="{{ route('admin.affiliates.index') }}" class="btn btn-alt-secondary">Back</a>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-md-3"><div class="block block-rounded"><div class="block-content"><div class="text-muted small">Referred Users</div><div class="fs-3 fw-bold">{{ $stats['referred_users'] }}</div></div></div></div>
        <div class="col-md-3"><div class="block block-rounded"><div class="block-content"><div class="text-muted small">Orders</div><div class="fs-3 fw-bold">{{ $stats['orders_total'] }}</div></div></div></div>
        <div class="col-md-3"><div class="block block-rounded"><div class="block-content"><div class="text-muted small">Paid Orders</div><div class="fs-3 fw-bold">{{ $stats['orders_paid'] }}</div></div></div></div>
        <div class="col-md-3"><div class="block block-rounded"><div class="block-content"><div class="text-muted small">Paid Revenue</div><div class="fs-3 fw-bold">{{ number_format($stats['revenue_paid'], 2) }} EGP</div></div></div></div>
    </div>

    <div class="block block-rounded mb-4">
        <div class="block-header block-header-default">
            <h3 class="block-title">Affiliate Link</h3>
        </div>
        <div class="block-content">
            @if($affiliateLink)
                <a href="{{ $affiliateLink }}" target="_blank">{{ $affiliateLink }}</a>
                <div class="text-muted mt-2">Target: <code>{{ $affiliate->affiliate_target_url ?: '/account/register' }}</code></div>
            @else
                <p class="text-muted mb-0">No affiliate link generated yet.</p>
            @endif
            <form method="POST" action="{{ route('admin.affiliates.store') }}" class="mt-3">
                @csrf
                <input type="hidden" name="user_id" value="{{ $affiliate->id }}">
                <label class="form-label" for="target_url">Target Link</label>
                <input id="target_url" name="target_url" class="form-control mb-2" value="{{ old('target_url', $affiliate->affiliate_target_url ?: '/account/register') }}" placeholder="/events/my-event">
                <button class="btn btn-alt-success" type="submit">Update Link</button>
            </form>
        </div>
    </div>

    <div class="block block-rounded mb-4">
        <div class="block-header block-header-default"><h3 class="block-title">Referred Users</h3></div>
        <div class="block-content">
            <div class="table-responsive">
                <table class="table table-bordered table-striped">
                    <thead><tr><th>Name</th><th>Email</th><th>Joined At</th></tr></thead>
                    <tbody>
                        @forelse($referredUsers as $user)
                            <tr>
                                <td>{{ $user->name }}</td>
                                <td>{{ $user->email }}</td>
                                <td>{{ $user->created_at?->format('Y-m-d H:i') }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="3" class="text-center py-3 text-muted">No referred users yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            {{ $referredUsers->links() }}
        </div>
    </div>

    <div class="block block-rounded">
        <div class="block-header block-header-default"><h3 class="block-title">Orders via Affiliate</h3></div>
        <div class="block-content">
            <div class="table-responsive">
                <table class="table table-bordered table-striped">
                    <thead><tr><th>Order #</th><th>Buyer</th><th>Status</th><th>Payment</th><th>Total</th><th class="text-end">View</th></tr></thead>
                    <tbody>
                        @forelse($orders as $order)
                            <tr>
                                <td>{{ $order->order_number }}</td>
                                <td>{{ $order->user?->name ?? '-' }}</td>
                                <td>{{ ucfirst(str_replace('_', ' ', $order->status)) }}</td>
                                <td>{{ ucfirst($order->payment_status) }}</td>
                                <td>{{ number_format((float) $order->total_amount, 2) }} EGP</td>
                                <td class="text-end"><a href="{{ route('admin.orders.show', $order) }}" class="btn btn-sm btn-alt-primary"><i class="fa fa-eye"></i></a></td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="text-center py-3 text-muted">No orders tracked yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            {{ $orders->links() }}
        </div>
    </div>
</div>
@endsection
