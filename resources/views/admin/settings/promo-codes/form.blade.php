@extends('admin.master')

@section('title', $promoCode->exists ? 'Edit Promo Code' : 'Create Promo Code')


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

    <x-admin.card class="a-m-narrow">
        <x-admin.form-section title="Discount" description="The code customers type, and what it takes off." :cols="3">
            <x-admin.input label="Code" name="code" class="a-m-code" :value="$promoCode->code" required />
            <x-admin.select label="Discount Type" name="discount_type" class="no-select2" :options="['percent' => 'Percent (%)', 'fixed' => 'Fixed Amount (EGP)']" :value="$promoCode->discount_type ?: 'percent'" required />
            <x-admin.input label="Discount Value" name="discount_value" type="number" step="0.01" min="0.01" :value="$promoCode->discount_value" required />
        </x-admin.form-section>

        <x-admin.form-section title="Limits and schedule" description="Leave empty for no limit or no date restriction." :cols="3">
            <x-admin.input label="Usage Limit" name="usage_limit" type="number" min="1" :value="$promoCode->usage_limit" placeholder="Unlimited" />
            <x-admin.input label="Starts At" name="starts_at" type="datetime-local" :value="$promoCode->starts_at?->format('Y-m-d\TH:i')" />
            <x-admin.input label="Ends At" name="ends_at" type="datetime-local" :value="$promoCode->ends_at?->format('Y-m-d\TH:i')" />
        </x-admin.form-section>

        <x-admin.form-section title="Status" cols="1">
            <div class="a-m-switch form-check form-switch">
                <input class="form-check-input" type="checkbox" name="is_active" value="1" id="is-active" @checked(old('is_active', $promoCode->exists ? $promoCode->is_active : true))>
                <label class="form-check-label" for="is-active"><strong>Active</strong><span class="a-hint d-block">Inactive codes are rejected at checkout.</span></label>
            </div>
        </x-admin.form-section>

        <div class="a-form-actions">
            <a href="{{ route('admin.promo-codes.index') }}" class="a-btn a-btn--outline">Cancel</a>
            <button class="a-btn a-btn--primary" type="submit">{{ $promoCode->exists ? 'Update Promo Code' : 'Create Promo Code' }}</button>
        </div>
    </x-admin.card>
</form>
@endsection
