@extends('admin.master')

@section('content')
<div class="content">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h3 mb-0">Customer Details</h1>
        <a href="{{ route('admin.customers.index') }}" class="btn btn-alt-secondary btn-sm">Back</a>
    </div>

    <div class="block block-rounded">
        <div class="block-content">
            <p><strong>Name:</strong> {{ $customer->full_name }}</p>
            <p><strong>Email:</strong> {{ $customer->email }}</p>
            <p><strong>Phone:</strong> {{ $customer->phone ?: '-' }}</p>
            <p><strong>Address:</strong> {{ $customer->address ?: '-' }}</p>
        </div>
    </div>
</div>
@endsection
