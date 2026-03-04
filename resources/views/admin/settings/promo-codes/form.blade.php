@extends('layouts.backend')

@section('title', $promoCode->exists ? 'Edit Promo Code' : 'Create Promo Code')

@section('content')
<div class="content">
    <div class="mb-3">
        <h1 class="h3 mb-1">{{ $promoCode->exists ? 'Edit Promo Code' : 'Create Promo Code' }}</h1>
        <a href="{{ route('admin.promo-codes.index') }}" class="fs-sm">← Back to Promo Codes</a>
    </div>

    <form method="POST" action="{{ $promoCode->exists ? route('admin.promo-codes.update', $promoCode) : route('admin.promo-codes.store') }}" class="block block-rounded">
        @csrf
        @if($promoCode->exists)
            @method('PUT')
        @endif

        <div class="block-content block-content-full">
            <div class="row g-4">
                <div class="col-md-4">
                    <label class="form-label">Code</label>
                    <input type="text" class="form-control" name="code" value="{{ old('code', $promoCode->code) }}" required>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Discount Type</label>
                    <select class="form-select" name="discount_type" required>
                        <option value="percent" @selected(old('discount_type', $promoCode->discount_type ?: 'percent') === 'percent')>Percent (%)</option>
                        <option value="fixed" @selected(old('discount_type', $promoCode->discount_type) === 'fixed')>Fixed Amount (EGP)</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Discount Value</label>
                    <input type="number" step="0.01" min="0.01" class="form-control" name="discount_value" value="{{ old('discount_value', $promoCode->discount_value) }}" required>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Usage Limit</label>
                    <input type="number" min="1" class="form-control" name="usage_limit" value="{{ old('usage_limit', $promoCode->usage_limit) }}" placeholder="Leave empty for unlimited">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Starts At</label>
                    <input type="datetime-local" class="form-control" name="starts_at" value="{{ old('starts_at', $promoCode->starts_at?->format('Y-m-d\\TH:i')) }}">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Ends At</label>
                    <input type="datetime-local" class="form-control" name="ends_at" value="{{ old('ends_at', $promoCode->ends_at?->format('Y-m-d\\TH:i')) }}">
                </div>
                <div class="col-12">
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" name="is_active" value="1" id="is-active" @checked(old('is_active', $promoCode->exists ? $promoCode->is_active : true))>
                        <label class="form-check-label" for="is-active">Active</label>
                    </div>
                </div>
            </div>
        </div>

        <div class="block-content block-content-full block-content-sm bg-body-light text-end">
            <button class="btn btn-primary" type="submit">{{ $promoCode->exists ? 'Update Promo Code' : 'Create Promo Code' }}</button>
        </div>
    </form>
</div>
@endsection
