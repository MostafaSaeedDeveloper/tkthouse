@extends('admin.master')
@section('title', 'Edit User')
@push('styles')<link rel="stylesheet" href="{{ asset('admin-v2/misc.css') }}?v={{ filemtime(public_path('admin-v2/misc.css')) }}">@endpush
@section('content')
<x-admin.page-header title="Edit user" :subtitle="$user->name.' · @'.$user->username" :crumbs="['Dashboard' => route('admin.dashboard'), 'Users' => route('admin.users.index'), $user->name => route('admin.users.show', $user), 'Edit']">
    @can('users.view')<a class="a-btn a-btn--outline" href="{{ route('admin.users.show', $user) }}"><i class="fa fa-eye"></i> View profile</a>@endcan
</x-admin.page-header>
<x-admin.flash />
<form method="POST" action="{{ route('admin.users.update',$user) }}" data-a-loading>
    @csrf @method('PUT')
    <x-admin.card>
        @include('admin.users.form')
        <div class="a-form-actions a-user-actionbar"><a class="a-btn a-btn--outline" href="{{ route('admin.users.show', $user) }}">Cancel</a><button class="a-btn a-btn--primary" type="submit">Save changes</button></div>
    </x-admin.card>
</form>
@endsection
