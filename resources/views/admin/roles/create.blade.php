@extends('admin.master')
@section('title', 'Create Role')
@push('styles')<link rel="stylesheet" href="{{ asset('admin-v2/misc.css') }}?v={{ filemtime(public_path('admin-v2/misc.css')) }}">@endpush
@section('content')
<x-admin.page-header title="Create Role" :crumbs="['Dashboard' => route('admin.dashboard'), 'Roles' => route('admin.roles.index'), 'Create']" />
<x-admin.flash />
<form method="POST" action="{{ route('admin.roles.store') }}" data-a-loading>
    @csrf 
    <x-admin.card>
        @include('admin.roles.form')
        <div class="a-form-actions"><a class="a-btn a-btn--outline" href="{{ route('admin.roles.index') }}">Cancel</a><button class="a-btn a-btn--primary" type="submit">Save</button></div>
    </x-admin.card>
</form>
@endsection
