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
    <x-admin.search name="search" :value="$search" placeholder="Name, username or email" />
    <x-admin.select label="Role" name="role" id="role" :options="collect($roles)->mapWithKeys(fn ($r) => [$r => $r])->all()" :value="$role" placeholder="All Roles" class="no-select2" />
    <x-admin.select label="Permission" name="permission" id="permission" :options="collect($permissions)->mapWithKeys(fn ($p) => [$p => $p])->all()" :value="$permission" placeholder="All Permissions" class="no-select2" />
</x-admin.filters>

<x-admin.card flush title="Admin users" :subtitle="$users->total().' total'">
    @if($users->isEmpty())
        <x-admin.empty icon="fa-users" title="No users found" text="Try a different search or filter." />
    @else
        <x-admin.table stack caption="Users">
            <thead>
                <tr><th>User</th><th>Email</th><th>Role</th><th>Access</th><th>Last login</th><th class="a-num"><span class="a-sr-only">Actions</span></th></tr>
            </thead>
            <tbody>
            @foreach($users as $user)
                <tr>
                    <td data-label="User"><span class="a-table__main a-trunc">{{ $user->name }}</span><span class="a-table__sub a-trunc">{{ $user->username }}</span></td>
                    <td data-label="Email"><span class="a-trunc" title="{{ $user->email }}">{{ $user->email }}</span></td>
                    <td data-label="Role">@if($user->roles->first()?->name)<x-admin.badge variant="dark">{{ $user->roles->first()->name }}</x-admin.badge>@else<span class="a-muted">—</span>@endif</td>
                    <td data-label="Access">
                        <span class="a-table__sub">Events: @if($user->managedEvents->isEmpty()) All @else <x-admin.more :items="$user->managedEvents->pluck('name')" /> @endif</span>
                        <span class="a-table__sub">Extra permissions: @if($user->permissions->isEmpty()) none @else <x-admin.more :items="$user->permissions->pluck('name')" /> @endif</span>
                    </td>
                    <td data-label="Last login">{{ $user->last_login_at?->format('d M Y, H:i') ?: '—' }}@if($user->last_login_ip)<span class="a-table__sub">{{ $user->last_login_ip }}</span>@endif</td>
                    <td data-label="">
                        <div class="a-table__actions">
                        @can('users.update')
                            <a href="{{ route('admin.users.edit',$user) }}" class="a-btn a-btn--outline a-btn--sm">Edit</a>
                        @endcan
                        @can('users.delete')
                            <x-admin.dropdown label="" aria-label="More actions">
                                <form method="POST" action="{{ route('admin.users.destroy',$user) }}" data-a-confirm="Delete user {{ $user->name }}? This cannot be undone." data-a-confirm-title="Delete user" data-a-confirm-ok="Delete">@csrf @method('DELETE')<button class="a-menu__item a-menu__item--danger" type="submit"><i class="fa fa-trash"></i> Delete</button></form>
                            </x-admin.dropdown>
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
