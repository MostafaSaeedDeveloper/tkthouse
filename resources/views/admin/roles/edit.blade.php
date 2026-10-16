@extends('admin.master')
@section('title', 'Edit Role')
@section('content')
<x-admin.page-header title="Edit Role" :subtitle="$role->name" :crumbs="['Dashboard' => route('admin.dashboard'), 'Roles' => route('admin.roles.index'), 'Edit']" />
<x-admin.flash />
<form method="POST" action="{{ route('admin.roles.update',$role) }}" data-a-loading>
    @csrf @method('PUT')
    <x-admin.card>
        @include('admin.roles.form')
        <div class="a-form-actions"><a class="a-btn a-btn--outline" href="{{ route('admin.roles.index') }}">Cancel</a><button class="a-btn a-btn--primary" type="submit">Update</button></div>
    </x-admin.card>
</form>
@endsection
