<x-admin.form-section title="Account" description="Sign-in details for this admin user.">
    <x-admin.field label="Name" name="name" for="u-name" required><input id="u-name" name="name" class="form-control" value="{{ old('name',$user->name ?? '') }}" required></x-admin.field>
    <x-admin.field label="Username" name="username" for="u-username" required><input id="u-username" name="username" class="form-control" value="{{ old('username',$user->username ?? '') }}" required></x-admin.field>
    <x-admin.field label="Email" name="email" for="u-email" required><input id="u-email" type="email" name="email" class="form-control" value="{{ old('email',$user->email ?? '') }}" required></x-admin.field>
    <x-admin.field label="Password" name="password" for="u-password" :required="!isset($user)" :hint="isset($user) ? 'Leave empty to keep the current password.' : null"><input id="u-password" type="password" name="password" class="form-control" autocomplete="new-password" @required(!isset($user))></x-admin.field>
</x-admin.form-section>

<x-admin.form-section title="Access" description="Role and any extra permissions on top of it." cols="1">
    <x-admin.field label="Role" name="role" for="u-role">
        <select id="u-role" name="role" class="form-select">
            <option value="">-- No Role --</option>
            @foreach($roles as $role)
                <option value="{{ $role->name }}" @selected(old('role', isset($user) ? $user->roles->first()?->name : '') === $role->name)>{{ $role->name }}</option>
            @endforeach
        </select>
    </x-admin.field>
    <x-admin.field label="Custom Permissions" name="permissions" for="u-permissions" hint="Hold Ctrl/Cmd to select several.">
        @php
            $selectedPermissions = old('permissions', isset($user) ? $user->permissions->pluck('name')->toArray() : []);
        @endphp
        <select id="u-permissions" name="permissions[]" class="form-select a-m-multi" multiple size="8">
            @foreach($permissions as $permission)
                <option value="{{ $permission->name }}" @selected(in_array($permission->name, $selectedPermissions))>{{ $permission->name }}</option>
            @endforeach
        </select>
    </x-admin.field>
</x-admin.form-section>

<x-admin.form-section title="Event scope" description="Limit which events this user can manage." cols="1">
    @php
        $selectedEventIds = old('managed_event_ids', isset($user) ? $user->managedEvents->pluck('id')->map(fn($id) => (string) $id)->all() : []);
    @endphp
    <x-admin.field label="Managed Event Scope" name="managed_event_ids" for="u-events" hint="Hold Ctrl/Cmd to select multiple events. Leave empty for no restriction (all events).">
        <select id="u-events" name="managed_event_ids[]" class="form-select a-m-multi" multiple size="6">
            @foreach(($events ?? collect()) as $event)
                <option value="{{ $event->id }}" @selected(in_array((string) $event->id, $selectedEventIds))>{{ $event->name }}</option>
            @endforeach
        </select>
    </x-admin.field>
</x-admin.form-section>
