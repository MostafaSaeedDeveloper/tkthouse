@extends('admin.master')

@section('content')
<div class="content">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h2 class="h4 mb-1">Payment Methods</h2>
            <a href="{{ route('admin.settings.edit') }}" class="fs-sm">← Back to General Settings</a>
        </div>
        <a href="{{ route('admin.payment-methods.create') }}" class="btn btn-primary">Add Payment Method</a>
    </div>

    @include('admin.partials.flash')

    <div class="block block-rounded">
        <div class="block-content table-responsive">
            <table class="table table-striped align-middle">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Code</th>
                        <th>Checkout</th>
                        <th>Provider</th>
                        <th>Status</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($methods as $method)
                        <tr>
                            <td>{{ $method->name }}</td>
                            <td><code>{{ $method->code }}</code></td>
                            <td>@if($method->checkout_icon && str_contains($method->checkout_icon, '/'))<img src="{{ asset('storage/'.$method->checkout_icon) }}" alt="icon" style="height:20px;width:20px;object-fit:contain;vertical-align:middle">@else<span>💰</span>@endif <span class="ms-1">{{ $method->checkout_label ?: $method->name }}</span>@if($method->checkout_description)<div class="fs-xs text-muted">{{ $method->checkout_description }}</div>@endif</td>
                            <td>{{ strtoupper($method->provider) }}</td>
                            <td>
                                <span class="badge {{ $method->is_active ? 'bg-success' : 'bg-secondary' }}">
                                    {{ $method->is_active ? 'Active' : 'Disabled' }}
                                </span>
                            </td>
                            <td class="text-end">
                                <a href="{{ route('admin.payment-methods.edit', $method) }}" class="btn btn-sm btn-alt-primary">Edit</a>
                                <form method="POST" action="{{ route('admin.payment-methods.destroy', $method) }}" class="d-inline" onsubmit="return confirm('Delete this payment method?')">
                                    @csrf
                                    @method('DELETE')
                                    <button class="btn btn-sm btn-alt-danger">Delete</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="text-center text-muted">No payment methods found.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
