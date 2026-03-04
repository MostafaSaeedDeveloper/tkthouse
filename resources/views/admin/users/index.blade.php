@extends('admin.master')

@section('content')
<div class="content">
    @include('admin.partials.flash')
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h2 class="h4 mb-0">Users</h2>
        @can('users.create')
            <a href="{{ route('admin.users.create') }}" class="btn btn-primary">Create User</a>
        @endcan
    </div>

    <div class="block block-rounded mb-3">
        <div class="block-content">
            <form method="GET" action="{{ route('admin.users.index') }}" class="row g-3 align-items-end">
                <div class="col-md-5">
                    <label class="form-label" for="search">Search</label>
                    <input type="text" id="search" name="search" class="form-control" value="{{ $search }}" placeholder="Name, username or email">
                </div>
                <div class="col-md-3">
                    <label class="form-label" for="role">Role</label>
                    <select id="role" name="role" class="form-select">
                        <option value="">All Roles</option>
                        @foreach($roles as $roleName)
                            <option value="{{ $roleName }}" @selected($roleName === $role)>{{ $roleName }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label" for="permission">Permission</label>
                    <select id="permission" name="permission" class="form-select">
                        <option value="">All Permissions</option>
                        @foreach($permissions as $permissionName)
                            <option value="{{ $permissionName }}" @selected($permissionName === $permission)>{{ $permissionName }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-1 d-grid">
                    <button class="btn btn-primary">Filter</button>
                </div>
                <div class="col-12">
                    <a href="{{ route('admin.users.index') }}" class="btn btn-sm btn-alt-secondary">Reset</a>
                </div>
            </form>
        </div>
    </div>

    <div class="block block-rounded">
        <div class="table-responsive">
            <table class="table table-striped mb-0">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Username</th>
                        <th>Email</th>
                        <th>Role</th>
                        <th>Custom Permissions</th>
                        <th>Last Login</th>
                        <th>IP</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                @foreach($users as $user)
                    <tr>
                        <td>{{ $user->name }}</td>
                        <td>{{ $user->username }}</td>
                        <td>{{ $user->email }}</td>
                        <td>{{ $user->roles->first()?->name ?: '-' }}</td>
                        <td>
                            @forelse($user->permissions as $userPermission)
                                <span class="badge bg-secondary me-1 mb-1">{{ $userPermission->name }}</span>
                            @empty
                                -
                            @endforelse
                        </td>
                        <td>{{ $user->last_login_at?->format('Y-m-d H:i') ?: '-' }}</td>
                        <td>{{ $user->last_login_ip ?: '-' }}</td>
                        <td class="text-end">
                            @can('users.update')
                                <a href="{{ route('admin.users.edit',$user) }}" class="btn btn-sm btn-alt-primary">Edit</a>
                            @endcan
                            @can('users.delete')
                                <form method="POST" action="{{ route('admin.users.destroy',$user) }}" class="d-inline">@csrf @method('DELETE')<button class="btn btn-sm btn-danger">Delete</button></form>
                            @endcan
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </div>
    {{ $users->links() }}
</div>
@endsection
