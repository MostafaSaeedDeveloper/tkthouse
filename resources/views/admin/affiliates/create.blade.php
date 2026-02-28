@extends('admin.master')

@section('content')
<div class="content">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h2 class="h4 mb-0">Add Affiliate Link</h2>
        <a href="{{ route('admin.affiliates.index') }}" class="btn btn-alt-secondary">Back</a>
    </div>

    <div class="block block-rounded">
        <div class="block-header block-header-default">
            <h3 class="block-title">Generate New Link</h3>
        </div>
        <div class="block-content">
            <form method="POST" action="{{ route('admin.affiliates.store') }}">
                @csrf
                <div class="mb-4">
                    <label class="form-label" for="user_id">Customer / User</label>
                    <select class="form-select @error('user_id') is-invalid @enderror" id="user_id" name="user_id" required>
                        <option value="">Choose customer</option>
                        @foreach($customers as $customer)
                            <option value="{{ $customer->id }}" @selected((int) old('user_id') === (int) $customer->id)>
                                {{ $customer->name }} ({{ $customer->email }}){{ $customer->affiliate_code ? ' - has link' : '' }}
                            </option>
                        @endforeach
                    </select>
                    @error('user_id')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <button type="submit" class="btn btn-alt-success">Generate Link</button>
            </form>
        </div>
    </div>
</div>
@endsection
