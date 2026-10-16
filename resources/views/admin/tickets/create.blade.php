@extends('admin.master')
@section('title', 'Create Ticket')
@push('styles')<link rel="stylesheet" href="{{ asset('admin-v2/events-tickets.css') }}?v={{ filemtime(public_path('admin-v2/events-tickets.css')) }}">@endpush

@section('content')
<x-admin.page-header title="Create Ticket" :crumbs="['Dashboard' => route('admin.dashboard'), 'Tickets' => route('admin.tickets.index'), 'Create']" />
<x-admin.flash />
<form method="POST" action="{{ route('admin.tickets.store') }}" data-a-loading>
    <x-admin.card>
        @include('admin.tickets.form')
    </x-admin.card>
</form>
@endsection
