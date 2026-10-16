@extends('admin.master')
@section('title', 'Add Affiliate Link')

@push('styles')<link rel="stylesheet" href="{{ asset('admin-v2/orders-customers.css') }}">@endpush

@section('content')
<x-admin.page-header title="Add Affiliate Link" subtitle="Pick a portal user and the page their link opens." :crumbs="['Dashboard' => route('admin.dashboard'), 'Affiliates' => route('admin.affiliates.index'), 'Add link']">
    <a href="{{ route('admin.affiliates.index') }}" class="a-btn a-btn--ghost"><i class="fa fa-arrow-left"></i> Back</a>
</x-admin.page-header>

<x-admin.flash />

<form method="POST" action="{{ route('admin.affiliates.store') }}" data-a-loading>
    @csrf
    <x-admin.card title="Generate New Link">
        <div class="a-form-grid">
            <x-admin.field label="User" name="user_id" for="user_id" hint="Portal user accounts, not checkout customer records.">
                <select class="form-select @error('user_id') is-invalid @enderror" id="user_id" name="user_id" required>
                    <option value="">Choose user</option>
                    @foreach($customers as $customer)
                        <option value="{{ $customer->id }}" @selected((int) old('user_id') === (int) $customer->id)>
                            {{ $customer->name }} ({{ $customer->email }}){{ $customer->affiliate_code ? ' - has link' : '' }}
                        </option>
                    @endforeach
                </select>
            </x-admin.field>
            <x-admin.field label="Target Link (from your site)" name="target_url" for="target_url" hint="Example: /events or /events/summer-festival. This is the page where the affiliate link will open.">
                <input type="text" class="form-control @error('target_url') is-invalid @enderror" id="target_url" name="target_url" value="{{ old('target_url', '/account/register') }}" placeholder="/events/my-event">
            </x-admin.field>
        </div>
        <div class="a-form-actions">
            <a href="{{ route('admin.affiliates.index') }}" class="a-btn a-btn--outline">Cancel</a>
            <button type="submit" class="a-btn a-btn--primary">Generate Link</button>
        </div>
    </x-admin.card>
</form>
@endsection
