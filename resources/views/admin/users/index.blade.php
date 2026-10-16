@extends('admin.master')
@section('title', 'Users')
@section('content')
<x-admin.page-header title="Users" subtitle="Admin users, their roles and event scope." :crumbs="['Dashboard' => route('admin.dashboard'), 'Users']">
    @can('users.create')
        <a href="{{ route('admin.users.create') }}" class="a-btn a-btn--primary"><i class="fa fa-plus"></i> Create User</a>
    @endcan
</x-admin.page-header>

<x-admin.flash />

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
</x-admin.filters>

<x-admin.card flush>
    @if($users->isEmpty())
        <x-admin.empty icon="fa-users" title="No users found" text="Try a different search or filter." />
    @else
        <x-admin.table stack caption="Users">
            <thead>
                <tr><th>Name</th><th>Username</th><th>Email</th><th>Role</th><th>Custom Permissions</th><th>Event Scope</th><th>Last Login</th><th>IP</th><th><span class="a-sr-only">Actions</span></th></tr>
            </thead>
            <tbody>
            @foreach($users as $user)
                <tr>
                    <td data-label="Name"><span class="a-table__main">{{ $user->name }}</span></td>
                    <td data-label="Username">{{ $user->username }}</td>
                    <td data-label="Email">{{ $user->email }}</td>
                    <td data-label="Role">@if($user->roles->first()?->name)<x-admin.badge variant="dark">{{ $user->roles->first()->name }}</x-admin.badge>@else - @endif</td>
                    <td data-label="Custom Permissions" class="a-wrap">
                        <div class="a-m-chips">
                        @forelse($user->permissions as $userPermission)
                            <x-admin.badge>{{ $userPermission->name }}</x-admin.badge>
                        @empty
                            -
                        @endforelse
                        </div>
                    </td>
                    <td data-label="Event Scope" class="a-wrap">
                        <div class="a-m-chips">
                        @forelse($user->managedEvents as $me)
                            <x-admin.badge>{{ $me->name }}</x-admin.badge>
                        @empty
                            All Events
                        @endforelse
                        </div>
                    </td>
                    <td data-label="Last Login">{{ $user->last_login_at?->format('Y-m-d H:i') ?: '-' }}</td>
                    <td data-label="IP">{{ $user->last_login_ip ?: '-' }}</td>
                    <td data-label="Actions">
                        <div class="a-table__actions">
                        @can('users.update')
                            <a href="{{ route('admin.users.edit',$user) }}" class="a-btn a-btn--outline a-btn--sm">Edit</a>
                        @endcan
                        @can('users.delete')
                            <form method="POST" action="{{ route('admin.users.destroy',$user) }}" data-a-confirm="Delete user {{ $user->name }}? This cannot be undone." data-a-confirm-title="Delete user" data-a-confirm-ok="Delete">@csrf @method('DELETE')<button class="a-btn a-btn--danger a-btn--sm" type="submit">Delete</button></form>
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
