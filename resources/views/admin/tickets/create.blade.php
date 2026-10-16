@extends('admin.master')
@section('title', 'Create Ticket')

@section('content')
<x-admin.page-header title="Create Ticket" :crumbs="['Dashboard' => route('admin.dashboard'), 'Tickets' => route('admin.tickets.index'), 'Create']" />
<x-admin.flash />
<form method="POST" action="{{ route('admin.tickets.store') }}" data-a-loading>
    <x-admin.card>
        @include('admin.tickets.form')
    </x-admin.card>
</form>
@endsection
