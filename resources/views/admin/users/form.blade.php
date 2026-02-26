<div class="mb-3"><label class="form-label">Name</label><input name="name" class="form-control" value="{{ old('name',$user->name ?? '') }}" required></div>
<div class="mb-3"><label class="form-label">Username</label><input name="username" class="form-control" value="{{ old('username',$user->username ?? '') }}" required></div>
<div class="mb-3"><label class="form-label">Email</label><input type="email" name="email" class="form-control" value="{{ old('email',$user->email ?? '') }}" required></div>
<div class="mb-3"><label class="form-label">Password {{ isset($user) ? '(optional)' : '' }}</label><input type="password" name="password" class="form-control" {{ isset($user) ? '' : 'required' }}></div>
<div class="mb-3">
    <label class="form-label">Roles</label>
    <select name="roles[]" class="form-select" multiple>
        @foreach($roles as $role)
            <option value="{{ $role->name }}" @selected(in_array($role->name, old('roles', isset($user) ? $user->roles->pluck('name')->toArray() : [])))>{{ $role->name }}</option>
        @endforeach
    </select>
</div>
