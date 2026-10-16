@extends('admin.master')
@section('title', 'Roles')
@section('content')
<x-admin.page-header title="Roles" subtitle="Bundles of permissions you can assign to users." :crumbs="['Dashboard' => route('admin.dashboard'), 'Roles']">
    <a class="a-btn a-btn--primary" href="{{ route('admin.roles.create') }}"><i class="fa fa-plus"></i> Create Role</a>
</x-admin.page-header>
<x-admin.flash />
<x-admin.card flush>
    @if($roles->isEmpty())
        <x-admin.empty icon="fa-user-shield" title="No roles yet" text="Create a role to group permissions.">
            <a class="a-btn a-btn--primary" href="{{ route('admin.roles.create') }}">Create Role</a>
        </x-admin.empty>
    @else
        <x-admin.table stack caption="Roles">
            <thead><tr><th>Name</th><th>Permissions</th><th><span class="a-sr-only">Actions</span></th></tr></thead>
            <tbody>
            @foreach($roles as $role)
                <tr>
                    <td data-label="Name"><span class="a-table__main">{{ $role->name }}</span><span class="a-table__sub">{{ $role->permissions->count() }} permissions</span></td>
                    <td data-label="Permissions" class="a-wrap">
                        <div class="a-m-chips">
                        @forelse($role->permissions as $perm)<x-admin.badge>{{ $perm->name }}</x-admin.badge>@empty - @endforelse
                        </div>
                    </td>
                    <td data-label="Actions"><div class="a-table__actions">
                        <a href="{{ route('admin.roles.edit',$role) }}" class="a-btn a-btn--outline a-btn--sm">Edit</a>
                        <form method="POST" action="{{ route('admin.roles.destroy',$role) }}" data-a-confirm="Delete role {{ $role->name }}?" data-a-confirm-title="Delete role" data-a-confirm-ok="Delete">@csrf @method('DELETE')<button class="a-btn a-btn--danger a-btn--sm" type="submit">Delete</button></form>
                    </div></td>
                </tr>
            @endforeach
            </tbody>
        </x-admin.table>
        <x-admin.pager :paginator="$roles" />
    @endif
</x-admin.card>
@endsection
