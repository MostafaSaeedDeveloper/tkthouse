@extends('admin.master')

@section('content')
<div class="content">
    @include('admin.partials.flash')
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h2 class="h4 mb-0">Users</h2>
        <a href="{{ route('admin.users.create') }}" class="btn btn-primary">Create User</a>
    </div>
    <div class="block block-rounded">
        <div class="table-responsive">
            <table class="table table-striped mb-0">
                <thead><tr><th>Name</th><th>Username</th><th>Email</th><th>Roles</th><th>Last Login</th><th>IP</th><th></th></tr></thead>
                <tbody>
                @foreach($users as $user)
                    <tr>
                        <td>{{ $user->name }}</td>
                        <td>{{ $user->username }}</td>
                        <td>{{ $user->email }}</td>
                        <td>{{ $user->roles->pluck('name')->implode(', ') ?: '-' }}</td>
                        <td>{{ $user->last_login_at?->format('Y-m-d H:i') ?: '-' }}</td>
                        <td>{{ $user->last_login_ip ?: '-' }}</td>
                        <td class="text-end">
                            <a href="{{ route('admin.users.edit',$user) }}" class="btn btn-sm btn-alt-primary">Edit</a>
                            <form method="POST" action="{{ route('admin.users.destroy',$user) }}" class="d-inline">@csrf @method('DELETE')<button class="btn btn-sm btn-danger">Delete</button></form>
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
