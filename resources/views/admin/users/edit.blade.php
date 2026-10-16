@extends('admin.master')
@section('title', 'Edit User')
@section('content')
<x-admin.page-header title="Edit User" :subtitle="$user->name" :crumbs="['Dashboard' => route('admin.dashboard'), 'Users' => route('admin.users.index'), 'Edit']" />
<x-admin.flash />
<form method="POST" action="{{ route('admin.users.update',$user) }}" data-a-loading>
    @csrf @method('PUT')
    <x-admin.card class="a-m-narrow">
        @include('admin.users.form')
        <div class="a-form-actions"><a class="a-btn a-btn--outline" href="{{ route('admin.users.index') }}">Cancel</a><button class="a-btn a-btn--primary" type="submit">Update</button></div>
    </x-admin.card>
</form>
@endsection
