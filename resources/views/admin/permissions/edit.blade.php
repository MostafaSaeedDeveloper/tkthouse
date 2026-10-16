@extends('admin.master')
@section('title', 'Edit Permission')
@section('content')
<x-admin.page-header title="Edit Permission" :crumbs="['Dashboard' => route('admin.dashboard'), 'Permissions' => route('admin.permissions.index'), 'Edit']" />
<x-admin.flash />
<form method="POST" action="{{ route('admin.permissions.update',$permission) }}" data-a-loading>
    @csrf @method('PUT')
    <x-admin.card class="a-m-narrow a-m-narrow--sm">
        <x-admin.form-section title="Permission" cols="1">
            <x-admin.field label="Permission Name" name="name" for="p-name" required><input id="p-name" name="name" class="form-control" value="{{ old('name',$permission->name) }}" required></x-admin.field>
        </x-admin.form-section>
        <div class="a-form-actions"><a class="a-btn a-btn--outline" href="{{ route('admin.permissions.index') }}">Cancel</a><button class="a-btn a-btn--primary" type="submit">Update</button></div>
    </x-admin.card>
</form>
@endsection
