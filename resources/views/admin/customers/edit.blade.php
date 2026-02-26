@extends('admin.master')

@section('content')
<div class="content">
    @include('admin.partials.flash')

    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h3 mb-0">Edit Customer</h1>
        <a href="{{ route('admin.customers.index') }}" class="btn btn-alt-secondary btn-sm">Back</a>
    </div>

    <form method="POST" action="{{ route('admin.customers.update', $customer) }}" class="block block-rounded">
        @csrf
        @method('PUT')
        <div class="block-content">
            <div class="row g-3">
                <div class="col-md-6"><label class="form-label">First Name</label><input class="form-control" name="first_name" value="{{ old('first_name', $customer->first_name) }}" required></div>
                <div class="col-md-6"><label class="form-label">Last Name</label><input class="form-control" name="last_name" value="{{ old('last_name', $customer->last_name) }}" required></div>
                <div class="col-md-6"><label class="form-label">Email</label><input type="email" class="form-control" name="email" value="{{ old('email', $customer->email) }}" required></div>
                <div class="col-md-6"><label class="form-label">Phone</label><input class="form-control" name="phone" value="{{ old('phone', $customer->phone) }}"></div>
                <div class="col-12"><label class="form-label">Address</label><input class="form-control" name="address" value="{{ old('address', $customer->address) }}"></div>
            </div>
        </div>
        <div class="block-content block-content-full bg-body-light d-flex justify-content-end gap-2">
            <a href="{{ route('admin.customers.index') }}" class="btn btn-alt-secondary">Cancel</a>
            <button type="submit" class="btn btn-primary">Update Customer</button>
        </div>
    </form>
</div>
@endsection
