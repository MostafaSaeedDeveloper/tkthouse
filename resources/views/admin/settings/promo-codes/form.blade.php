@extends('admin.master')

@section('title', $promoCode->exists ? 'Edit Promo Code' : 'Create Promo Code')

@push('styles')<link rel="stylesheet" href="{{ asset('admin-v2/orders-customers.css') }}">@endpush

@section('content')
<x-admin.page-header :title="$promoCode->exists ? 'Edit Promo Code' : 'Create Promo Code'" :crumbs="['Dashboard' => route('admin.dashboard'), 'Promo Codes' => route('admin.promo-codes.index'), $promoCode->exists ? 'Edit' : 'Create']">
    <a href="{{ route('admin.promo-codes.index') }}" class="a-btn a-btn--ghost"><i class="fa fa-arrow-left"></i> Back to Promo Codes</a>
</x-admin.page-header>

<x-admin.flash />

<form method="POST" action="{{ $promoCode->exists ? route('admin.promo-codes.update', $promoCode) : route('admin.promo-codes.store') }}" data-a-loading>
    @csrf
    @if($promoCode->exists)
        @method('PUT')
    @endif

    <x-admin.card>
        <x-admin.form-section title="Discount" description="The code customers type, and what it takes off." :cols="3">
            <x-admin.field label="Code" name="code" required>
                <input type="text" class="form-control" id="code" name="code" value="{{ old('code', $promoCode->code) }}" required>
            </x-admin.field>
            <x-admin.field label="Discount Type" name="discount_type" required>
                <select class="form-select" id="discount_type" name="discount_type" required>
                    <option value="percent" @selected(old('discount_type', $promoCode->discount_type ?: 'percent') === 'percent')>Percent (%)</option>
                    <option value="fixed" @selected(old('discount_type', $promoCode->discount_type) === 'fixed')>Fixed Amount (EGP)</option>
                </select>
            </x-admin.field>
            <x-admin.field label="Discount Value" name="discount_value" required>
                <input type="number" step="0.01" min="0.01" class="form-control" id="discount_value" name="discount_value" value="{{ old('discount_value', $promoCode->discount_value) }}" required>
            </x-admin.field>
        </x-admin.form-section>

        <x-admin.form-section title="Limits and schedule" description="Leave empty for no limit or no date restriction." :cols="3">
            <x-admin.field label="Usage Limit" name="usage_limit">
                <input type="number" min="1" class="form-control" id="usage_limit" name="usage_limit" value="{{ old('usage_limit', $promoCode->usage_limit) }}" placeholder="Leave empty for unlimited">
            </x-admin.field>
            <x-admin.field label="Starts At" name="starts_at">
                <input type="datetime-local" class="form-control" id="starts_at" name="starts_at" value="{{ old('starts_at', $promoCode->starts_at?->format('Y-m-d\TH:i')) }}">
            </x-admin.field>
            <x-admin.field label="Ends At" name="ends_at">
                <input type="datetime-local" class="form-control" id="ends_at" name="ends_at" value="{{ old('ends_at', $promoCode->ends_at?->format('Y-m-d\TH:i')) }}">
            </x-admin.field>
        </x-admin.form-section>

        <div class="form-check form-switch">
            <input class="form-check-input" type="checkbox" name="is_active" value="1" id="is-active" @checked(old('is_active', $promoCode->exists ? $promoCode->is_active : true))>
            <label class="form-check-label" for="is-active">Active</label>
        </div>

        <div class="a-form-actions">
            <a href="{{ route('admin.promo-codes.index') }}" class="a-btn a-btn--outline">Cancel</a>
            <button class="a-btn a-btn--primary" type="submit">{{ $promoCode->exists ? 'Update Promo Code' : 'Create Promo Code' }}</button>
        </div>
    </x-admin.card>
</form>
@endsection
