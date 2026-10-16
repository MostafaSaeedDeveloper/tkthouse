@extends('admin.master')
@section('title', 'Roles')
@section('content')
<x-admin.page-header title="Roles" subtitle="Bundles of permissions you can assign to users." :crumbs="['Dashboard' => route('admin.dashboard'), 'Roles']">
    <a class="a-btn a-btn--primary" href="{{ route('admin.roles.create') }}"><i class="fa fa-plus"></i> Create Role</a>
</x-admin.page-header>
<x-admin.flash />
<x-admin.card flush title="All roles" :subtitle="$roles->total().' total'">
    @if($roles->isEmpty())
        <x-admin.empty icon="fa-user-shield" title="No roles yet" text="Create a role to group permissions.">
            <a class="a-btn a-btn--primary" href="{{ route('admin.roles.create') }}">Create Role</a>
        </x-admin.empty>
    @else
        <x-admin.table stack caption="Roles">
            <thead><tr><th>Role</th><th>Permissions</th><th class="a-num"><span class="a-sr-only">Actions</span></th></tr></thead>
            <tbody>
            @foreach($roles as $role)
                <tr>
                    <td data-label="Role"><span class="a-table__main">{{ $role->name }}</span><span class="a-table__sub">{{ $role->permissions->count() }} {{ \Illuminate\Support\Str::plural('permission', $role->permissions->count()) }}</span></td>
                    <td data-label="Permissions"><x-admin.more :items="$role->permissions->pluck('name')" /></td>
                    <td data-label=""><div class="a-table__actions">
                        <a href="{{ route('admin.roles.edit',$role) }}" class="a-btn a-btn--outline a-btn--sm">Edit</a>
                        <x-admin.dropdown label="" aria-label="More actions">
                            <form method="POST" action="{{ route('admin.roles.destroy',$role) }}" data-a-confirm="Delete role {{ $role->name }}?" data-a-confirm-title="Delete role" data-a-confirm-ok="Delete">@csrf @method('DELETE')<button class="a-menu__item a-menu__item--danger" type="submit"><i class="fa fa-trash"></i> Delete</button></form>
                        </x-admin.dropdown>
                    </div></td>
                </tr>
            @endforeach
            </tbody>
        </x-admin.table>
        <x-admin.pager :paginator="$roles" />
    @endif
</x-admin.card>
@endsection
