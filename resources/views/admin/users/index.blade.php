@extends('admin.master')
@section('title', 'Users')
@push('styles')<link rel="stylesheet" href="{{ asset('admin-v2/misc.css') }}?v={{ filemtime(public_path('admin-v2/misc.css')) }}">@endpush
@section('content')
<x-admin.page-header title="Users" subtitle="Admin users, their roles, status and access." :crumbs="['Dashboard' => route('admin.dashboard'), 'Users']">
    @can('users.create')
        <a href="{{ route('admin.users.create') }}" class="a-btn a-btn--primary"><i class="fa fa-plus"></i> Create User</a>
    @endcan
</x-admin.page-header>

<x-admin.flash />

<div class="a-grid a-grid--4" style="margin-bottom:12px">
    <x-admin.kpi label="Users" :value="$stats['total']" icon="fa-users" hint="Accounts you can manage" />
    <x-admin.kpi label="Active" :value="$stats['active']" icon="fa-circle-check" hint="Can sign in" />
    <x-admin.kpi label="Inactive" :value="$stats['inactive']" icon="fa-user-slash" hint="Sign-in blocked" />
    <x-admin.kpi label="Receiving orders" :value="$stats['receiving']" icon="fa-inbox" hint="Active and in the assignment pool" />
</div>

<x-admin.filters :action="route('admin.users.index')" :reset="route('admin.users.index')">
    <div class="a-field"><label class="form-label" for="search">Search</label>
        <input type="text" id="search" name="search" class="form-control" value="{{ $search }}" placeholder="Name, username or email"></div>
    <div class="a-field"><label class="form-label" for="role">Role</label>
        <select id="role" name="role" class="form-select">
            <option value="">All Roles</option>
            @foreach($roles as $roleName)
                <option value="{{ $roleName }}" @selected($roleName === $role)>{{ $roleName }}</option>
            @endforeach
        </select></div>
    <div class="a-field"><label class="form-label" for="permission">Permission</label>
        <select id="permission" name="permission" class="form-select">
            <option value="">All Permissions</option>
            @foreach($permissions as $permissionName)
                <option value="{{ $permissionName }}" @selected($permissionName === $permission)>{{ $permissionName }}</option>
            @endforeach
        </select></div>
    <div class="a-field"><label class="form-label" for="status">Status</label>
        <select id="status" name="status" class="form-select">
            <option value="">All</option>
            <option value="active" @selected($status === 'active')>Active</option>
            <option value="inactive" @selected($status === 'inactive')>Inactive</option>
        </select></div>
    <div class="a-field"><label class="form-label" for="sort">Sort by</label>
        <select id="sort" name="sort" class="form-select">
            <option value="newest" @selected($sort === 'newest')>Newest</option>
            <option value="name" @selected($sort === 'name')>Name (A–Z)</option>
            <option value="last_login" @selected($sort === 'last_login')>Last login</option>
        </select></div>
    <div class="a-field"><span class="form-label">Assignment</span>
        <label class="form-check" style="display:flex;gap:8px;align-items:center;min-height:44px"><input class="form-check-input" type="checkbox" name="receives_orders" value="1" @checked($orders)> Receives orders</label></div>
</x-admin.filters>

<x-admin.card flush>
    @if($users->isEmpty())
        <x-admin.empty icon="fa-users" title="No users found" text="Try a different search or filter." />
    @else
        <x-admin.table stack caption="Users">
            <thead>
                <tr><th>User</th><th>Role</th><th>Status</th><th>Access</th><th>Last login</th><th><span class="a-sr-only">Actions</span></th></tr>
            </thead>
            <tbody>
            @foreach($users as $user)
                @php
                    $initials = mb_strtoupper(mb_substr(trim($user->name) ?: $user->username, 0, 1));
                    $extra = $user->permissions->count();
                    $isMe = auth()->id() === $user->id;
                @endphp
                <tr>
                    <td data-label="User">
                        <div style="display:flex;gap:12px;align-items:center;min-width:0">
                            <span class="a-userbtn__avatar" aria-hidden="true">@if($user->profileImageUrl())<img src="{{ $user->profileImageUrl() }}" alt="">@else{{ $initials }}@endif</span>
                            <span class="a-user-id">
                                <span class="a-table__main">{{ $user->name }}@if($isMe) <x-admin.badge variant="info">You</x-admin.badge>@endif</span>
                                <span class="a-table__sub a-user-sub" title="{{ $user->email }}">{{ $user->email }} · &#64;{{ $user->username }}</span>
                            </span>
                        </div>
                    </td>
                    <td data-label="Role">@if($user->roles->first()?->name)<x-admin.badge variant="dark">{{ $user->roles->first()->name }}</x-admin.badge>@else <span class="a-table__sub">No role</span>@endif</td>
                    <td data-label="Status" class="a-wrap">
                        <div class="a-m-chips">
                            @if($user->is_active)<x-admin.badge variant="success" dot>Active</x-admin.badge>@else<x-admin.badge variant="danger" dot>Inactive</x-admin.badge>@endif
                            @if($user->can_receive_orders)<x-admin.badge variant="info">Receives orders</x-admin.badge>@endif
                        </div>
                    </td>
                    <td data-label="Access" class="a-wrap">
                        <span class="a-table__main" style="font-weight:600">{{ $user->managedEvents->isEmpty() ? 'All events' : $user->managedEvents->count().' '.\Illuminate\Support\Str::plural('event', $user->managedEvents->count()) }}</span>
                        <span class="a-table__sub" @if($extra) title="{{ $user->permissions->pluck('name')->implode(', ') }}" @endif>{{ $extra ? '+ '.$extra.' custom '.\Illuminate\Support\Str::plural('permission', $extra) : 'Role permissions only' }}</span>
                    </td>
                    <td data-label="Last login">
                        @if($user->last_login_at)
                            <span class="a-table__main" style="font-weight:600" title="{{ $user->last_login_at->format('Y-m-d H:i') }}">{{ $user->last_login_at->diffForHumans() }}</span>
                            <span class="a-table__sub">{{ $user->last_login_ip ?: '' }}</span>
                        @else
                            <span class="a-table__sub">Never</span>
                        @endif
                    </td>
                    <td data-label="Actions">
                        <div class="a-table__actions">
                        @can('users.update')
                            <a href="{{ route('admin.users.edit',$user) }}" class="a-btn a-btn--outline a-btn--sm">Edit</a>
                        @endcan
                        @can('users.delete')
                            @unless($isMe)
                            <form method="POST" action="{{ route('admin.users.destroy',$user) }}" data-a-confirm="Delete user {{ $user->name }}? This cannot be undone." data-a-confirm-title="Delete user" data-a-confirm-ok="Delete">@csrf @method('DELETE')<button class="a-btn a-btn--danger a-btn--sm" type="submit">Delete</button></form>
                            @endunless
                        @endcan
                        </div>
                    </td>
                </tr>
            @endforeach
            </tbody>
        </x-admin.table>
        <x-admin.pager :paginator="$users" />
    @endif
</x-admin.card>
@endsection
