@extends('admin.master')

@section('content')
<div class="content">
    @include('admin.partials.flash')

    <h1 class="h3 mb-3">Customers</h1>

    <div class="block block-rounded">
        <div class="block-content p-0">
            <table class="table table-hover table-vcenter mb-0">
                <thead><tr><th>Name</th><th>Email</th><th>Phone</th><th>Orders</th><th>Tickets</th><th class="text-end">Actions</th></tr></thead>
                <tbody>
                @forelse($customers as $customer)
                    <tr>
                        <td>{{ $customer->full_name }}</td>
                        <td>{{ $customer->email }}</td>
                        <td>{{ $customer->phone ?: '-' }}</td>
                        <td>{{ $customer->orders_count }}</td>
                        <td>{{ $customer->tickets_count }}</td>
                        <td class="text-end">
                            <a class="btn btn-sm btn-alt-info" href="{{ route('admin.customers.show', $customer) }}"><i class="fa fa-eye"></i></a>
                            <a class="btn btn-sm btn-alt-primary" href="{{ route('admin.customers.edit', $customer) }}"><i class="fa fa-pen"></i></a>
                            <form method="POST" action="{{ route('admin.customers.destroy', $customer) }}" class="d-inline">@csrf @method('DELETE')<button class="btn btn-sm btn-alt-danger"><i class="fa fa-trash"></i></button></form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="text-center py-4 text-muted">No customers found.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-3">{{ $customers->links() }}</div>
</div>
@endsection
