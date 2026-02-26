@extends('admin.master')

@section('content')
<div class="content">
    <div class="block block-rounded">
        <div class="block-header block-header-default">
            <h3 class="block-title">Customers</h3>
        </div>
        <div class="block-content p-0">
            <div class="table-responsive">
                <table class="table table-hover table-vcenter mb-0">
                    <thead><tr><th>Name</th><th>Email</th><th>Phone</th><th>Orders</th><th class="text-end">Action</th></tr></thead>
                    <tbody>
                        @forelse($customers as $customer)
                        <tr>
                            <td>{{ $customer->full_name }}</td>
                            <td>{{ $customer->email }}</td>
                            <td>{{ $customer->phone }}</td>
                            <td>{{ $customer->orders_count }}</td>
                            <td class="text-end"><a class="btn btn-sm btn-alt-primary" href="{{ route('admin.customers.show', $customer) }}"><i class="fa fa-eye"></i></a></td>
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
