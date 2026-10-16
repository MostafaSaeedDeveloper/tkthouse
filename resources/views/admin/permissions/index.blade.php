@extends('admin.master')
@section('title', 'Permissions')
@section('content')
<x-admin.page-header title="Permissions" subtitle="Individual abilities that can be assigned to roles or users." :crumbs="['Dashboard' => route('admin.dashboard'), 'Permissions']">
    <a class="a-btn a-btn--primary" href="{{ route('admin.permissions.create') }}"><i class="fa fa-plus"></i> Create Permission</a>
</x-admin.page-header>
<x-admin.flash />
<x-admin.card flush title="All permissions" :subtitle="$permissions->total().' total'">
    @if($permissions->isEmpty())
        <x-admin.empty icon="fa-key" title="No permissions yet" text="Create the first permission.">
            <a class="a-btn a-btn--primary" href="{{ route('admin.permissions.create') }}">Create Permission</a>
        </x-admin.empty>
    @else
        <x-admin.table stack caption="Permissions">
            <thead><tr><th>Permission</th><th class="a-num"><span class="a-sr-only">Actions</span></th></tr></thead>
            <tbody>
            @foreach($permissions as $permission)
                <tr>
                    <td data-label="Permission"><code>{{ $permission->name }}</code></td>
                    <td data-label=""><div class="a-table__actions">
                        <a class="a-btn a-btn--outline a-btn--sm" href="{{ route('admin.permissions.edit',$permission) }}">Edit</a>
                        <x-admin.dropdown label="" aria-label="More actions">
                            <form method="POST" action="{{ route('admin.permissions.destroy',$permission) }}" data-a-confirm="Delete permission {{ $permission->name }}?" data-a-confirm-title="Delete permission" data-a-confirm-ok="Delete">@csrf @method('DELETE')<button class="a-menu__item a-menu__item--danger" type="submit"><i class="fa fa-trash"></i> Delete</button></form>
                        </x-admin.dropdown>
                    </div></td>
                </tr>
            @endforeach
            </tbody>
        </x-admin.table>
        <x-admin.pager :paginator="$permissions" />
    @endif
</x-admin.card>
@endsection
