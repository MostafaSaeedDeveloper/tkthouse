<div class="mb-3"><label class="form-label">Name</label><input name="name" class="form-control" value="{{ old('name',$user->name ?? '') }}" required></div>
<div class="mb-3"><label class="form-label">Username</label><input name="username" class="form-control" value="{{ old('username',$user->username ?? '') }}" required></div>
<div class="mb-3"><label class="form-label">Email</label><input type="email" name="email" class="form-control" value="{{ old('email',$user->email ?? '') }}" required></div>
<div class="mb-3"><label class="form-label">Password</label><input type="password" name="password" class="form-control" @required(!isset($user))></div>

<div class="mb-3">
    <label class="form-label">Role</label>
    <select name="role" class="form-select">
        <option value="">-- No Role --</option>
        @foreach($roles as $role)
            <option value="{{ $role->name }}" @selected(old('role', isset($user) ? $user->roles->first()?->name : '') === $role->name)>{{ $role->name }}</option>
        @endforeach
    </select>
</div>

<div class="mb-3">
    <label class="form-label">Custom Permissions</label>
    <select name="permissions[]" class="form-select" multiple size="8">
        @php
            $selectedPermissions = old('permissions', isset($user) ? $user->permissions->pluck('name')->toArray() : []);
        @endphp
        @foreach($permissions as $permission)
            <option value="{{ $permission->name }}" @selected(in_array($permission->name, $selectedPermissions))>{{ $permission->name }}</option>
        @endforeach
    </select>
</div>

<div class="mb-3">
    <label class="form-label">Managed Event Scope</label>
    <select name="managed_event_id" class="form-select">
        <option value="">-- All Events (No Restriction) --</option>
        @foreach(($events ?? collect()) as $event)
            <option value="{{ $event->id }}" @selected((string) old('managed_event_id', $user->managed_event_id ?? '') === (string) $event->id)>{{ $event->name }}</option>
        @endforeach
    </select>
    <div class="form-text">If set, this user will only see dashboard/reports/orders for the selected event.</div>
</div>
