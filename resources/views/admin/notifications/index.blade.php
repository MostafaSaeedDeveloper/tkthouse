@extends('admin.master')
@section('title', 'Notifications')
@section('content')
<x-admin.page-header title="Notifications" :subtitle="$unread ? $unread.' unread' : 'Everything is read'" :crumbs="['Dashboard' => route('admin.dashboard'), 'Notifications']">
    @if($unread)
        <form method="POST" action="{{ route('admin.notifications.read-all') }}">@csrf<button class="a-btn a-btn--outline" type="submit"><i class="fa fa-check-double"></i> Mark all read</button></form>
    @endif
</x-admin.page-header>
<x-admin.flash />
<nav class="a-tabs" aria-label="Filter notifications" style="margin-bottom:14px">
    <a href="{{ route('admin.notifications.index') }}" class="{{ $unreadOnly ? '' : 'is-active' }}">All</a>
    <a href="{{ route('admin.notifications.index', ['unread' => 1]) }}" class="{{ $unreadOnly ? 'is-active' : '' }}">Unread</a>
</nav>
<x-admin.card flush>
    <div class="a-notif__list a-notif__list--page">@include('admin.notifications._items', ['items' => $notifications])</div>
    <x-admin.pager :paginator="$notifications" />
</x-admin.card>
@endsection
