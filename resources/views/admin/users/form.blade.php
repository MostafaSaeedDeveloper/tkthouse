<div class="mb-3"><label class="form-label">Name</label><input name="name" class="form-control" value="{{ old('name',$user->name ?? '') }}" required></div>
<div class="mb-3"><label class="form-label">Username</label><input name="username" class="form-control" value="{{ old('username',$user->username ?? '') }}" required></div>
<div class="mb-3"><label class="form-label">Email</label><input type="email" name="email" class="form-control" value="{{ old('email',$user->email ?? '') }}" required></div>
<div class="mb-3"><label class="form-label">Password {{ isset($user) ? '(optional)' : '' }}</label><input type="password" name="password" class="form-control" {{ isset($user) ? '' : 'required' }}></div>

<div class="mb-3">
    <label class="form-label">Role (Single Role)</label>
    <select name="role" class="form-select">
        <option value="">-- No Role --</option>
        @foreach($roles as $role)
            <option value="{{ $role->name }}" @selected(old('role', isset($user) ? $user->roles->first()?->name : '') === $role->name)>{{ $role->name }}</option>
        @endforeach
    </select>
</div>

<div class="mb-3">
    <label class="form-label">Custom Permissions (User-specific)</label>
    <select name="permissions[]" class="form-select" multiple size="8">
        @php
            $selectedPermissions = old('permissions', isset($user) ? $user->permissions->pluck('name')->toArray() : []);
        @endphp
        @foreach($permissions as $permission)
            <option value="{{ $permission->name }}" @selected(in_array($permission->name, $selectedPermissions))>{{ $permission->name }}</option>
        @endforeach
    </select>
    <div class="form-text">دي صلاحيات مخصصة لليوزر نفسه، بالإضافة لصلاحيات الـ Role.</div>
</div>
