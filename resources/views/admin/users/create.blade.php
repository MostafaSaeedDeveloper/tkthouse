@extends('admin.master')
@section('title', 'Create User')
@push('styles')<link rel="stylesheet" href="{{ asset('admin-v2/misc.css') }}?v={{ filemtime(public_path('admin-v2/misc.css')) }}">@endpush
@section('content')
<x-admin.page-header title="Create User" subtitle="Add an admin user and choose what they can access." :crumbs="['Dashboard' => route('admin.dashboard'), 'Users' => route('admin.users.index'), 'Create']" />
<x-admin.flash />
<form method="POST" action="{{ route('admin.users.store') }}" data-a-loading>
    @csrf
    <x-admin.card>
        @include('admin.users.form')
        <div class="a-form-actions"><a class="a-btn a-btn--outline" href="{{ route('admin.users.index') }}">Cancel</a><button class="a-btn a-btn--primary" type="submit">Save</button></div>
    </x-admin.card>
</form>
@endsection
