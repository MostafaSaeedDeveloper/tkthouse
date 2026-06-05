@extends('admin.master')

@section('content')
<div class="content">
    @include('admin.partials.flash')

    <div class="d-md-flex justify-content-md-between align-items-md-center mb-3">
        <div>
            <h1 class="h3 mb-1">Customers</h1>
            <p class="text-muted mb-0">Customers list.</p>
        </div>
        @if(preg_replace('/[^a-z0-9]/i', '', strtolower(auth()->user()?->username ?? '')) === 'superadmin' || auth()->user()?->roles->contains(fn($r) => strtolower(preg_replace('/[^a-z0-9]/i', '', $r->name)) === 'superadmin'))
        <div>
            <a class="btn btn-sm btn-alt-success"
               href="{{ route('admin.customers.export', request()->only(['search', 'event_id'])) }}">
                <i class="fa fa-download me-1"></i> Export CSV
            </a>
        </div>
        @endif
    </div>

    <div class="block block-rounded mb-3">
        <div class="block-content py-3">
            <form method="GET" class="row g-2 align-items-end">
                <div class="col-md-5">
                    <label class="form-label">Search</label>
                    <input type="text" name="search" value="{{ request('search') }}" class="form-control" placeholder="Name / phone">
                </div>
                <div class="col-md-3 d-flex gap-2">
                    <button class="btn btn-primary" type="submit">Filter</button>
                    <a class="btn btn-alt-secondary" href="{{ route('admin.customers.index') }}">Reset</a>
                </div>
            </form>
        </div>
    </div>

    <div class="block block-rounded">
        <div class="block-header block-header-default">
            <h3 class="block-title">All Customers</h3>
            <div class="block-options">
                <span class="badge bg-primary">{{ $customers->total() }} Total</span>
            </div>
        </div>
        <div class="block-content p-0">
            <div class="table-responsive">
                <table class="table table-hover table-vcenter mb-0">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Phone</th>
                            <th>Orders</th>
                            <th class="text-end">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($customers as $customer)
                        <tr>
                            <td>
                                <a href="{{ route('admin.customers.show', $customer) }}" class="fw-semibold text-body-emphasis text-decoration-none">
                                    {{ $customer->full_name }}
                                </a>
                            </td>
                            <td>{{ $customer->email }}</td>
                            <td>{{ $customer->phone }}</td>
                            <td>{{ $customer->orders_count }}</td>
                            <td class="text-end">
                                <a class="btn btn-sm btn-alt-primary" href="{{ route('admin.customers.show', $customer) }}">
                                    <i class="fa fa-eye"></i>
                                </a>
                            </td>
                        </tr>
                        @empty
                        <tr><td colspan="5" class="text-center py-4 text-muted">No customers found.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <div class="mt-3">{{ $customers->links() }}</div>
</div>
@endsection
