@extends('admin.master')
@section('title', 'Create Permission')
@push('styles')<link rel="stylesheet" href="{{ asset('admin-v2/misc.css') }}?v={{ filemtime(public_path('admin-v2/misc.css')) }}">@endpush
@section('content')
<x-admin.page-header title="Create Permission" :crumbs="['Dashboard' => route('admin.dashboard'), 'Permissions' => route('admin.permissions.index'), 'Create']" />
<x-admin.flash />
<form method="POST" action="{{ route('admin.permissions.store') }}" data-a-loading>
    @csrf 
    <x-admin.card class="a-m-narrow">
        <x-admin.form-section title="Permission" cols="1">
            <x-admin.field label="Permission Name" name="name" for="p-name" required><input id="p-name" name="name" class="form-control" value="{{ old('name') }}" required></x-admin.field>
        </x-admin.form-section>
        <div class="a-form-actions"><a class="a-btn a-btn--outline" href="{{ route('admin.permissions.index') }}">Cancel</a><button class="a-btn a-btn--primary" type="submit">Save</button></div>
    </x-admin.card>
</form>
@endsection
