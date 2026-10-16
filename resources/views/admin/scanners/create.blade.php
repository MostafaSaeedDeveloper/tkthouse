@extends('admin.master')
@section('title', 'Add Scanner User')
@push('styles')<link rel="stylesheet" href="{{ asset('admin-v2/misc.css') }}?v={{ filemtime(public_path('admin-v2/misc.css')) }}">@endpush
@section('content')
<x-admin.page-header title="Add Scanner User" subtitle="Gate staff sign in with these details." :crumbs="['Dashboard' => route('admin.dashboard'), 'Scanners' => route('admin.scanners.index'), 'Add']">
    <a href="{{ route('admin.scanners.index') }}" class="a-btn a-btn--outline">Back</a>
</x-admin.page-header>
<x-admin.flash />
<form method="POST" action="{{ route('admin.scanners.store') }}" data-a-loading>
    @csrf
    <x-admin.card class="a-m-narrow">
        <x-admin.form-section title="Scanner account">
            <x-admin.field label="Name" name="name" for="sc-name" required><input id="sc-name" type="text" class="form-control @error('name') is-invalid @enderror" name="name" value="{{ old('name') }}" required></x-admin.field>
            <x-admin.field label="Username" name="username" for="sc-username" required><input id="sc-username" type="text" class="form-control @error('username') is-invalid @enderror" name="username" value="{{ old('username') }}" required></x-admin.field>
            <x-admin.field label="Email" name="email" for="sc-email" required><input id="sc-email" type="email" class="form-control @error('email') is-invalid @enderror" name="email" value="{{ old('email') }}" required></x-admin.field>
            <x-admin.field label="Password" name="password" for="sc-password" required><input id="sc-password" type="password" class="form-control @error('password') is-invalid @enderror" name="password" required></x-admin.field>
        </x-admin.form-section>
        <div class="a-form-actions"><a class="a-btn a-btn--outline" href="{{ route('admin.scanners.index') }}">Cancel</a><button type="submit" class="a-btn a-btn--primary">Create Scanner</button></div>
    </x-admin.card>
</form>
@endsection
