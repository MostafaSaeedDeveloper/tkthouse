<x-admin.form-section title="Account" description="Sign-in details for this admin user.">
    <x-admin.input label="Name" name="name" id="u-name" :value="$user->name ?? ''" required />
    <x-admin.input label="Username" name="username" id="u-username" :value="$user->username ?? ''" required />
    <x-admin.input label="Email" name="email" id="u-email" type="email" :value="$user->email ?? ''" required />
    <x-admin.field label="Password" name="password" for="u-password" :required="!isset($user)" :hint="isset($user) ? 'Leave empty to keep the current password.' : null"><input id="u-password" type="password" name="password" class="form-control @error('password') is-invalid @enderror" autocomplete="new-password" @required(!isset($user))></x-admin.field>
</x-admin.form-section>

<x-admin.form-section title="Access" description="Role, extra permissions on top of it, and which events this user can manage.">
    <x-admin.field class="is-full" label="Role" name="role" for="u-role">
        <select id="u-role" name="role" class="form-select no-select2">
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
        <select id="u-permissions" name="permissions[]" class="form-select no-select2 a-m-multi" multiple size="8">
            @foreach($permissions as $permission)
                <option value="{{ $permission->name }}" @selected(in_array($permission->name, $selectedPermissions))>{{ $permission->name }}</option>
            @endforeach
        </select>
    </x-admin.field>
    @php
        $selectedEventIds = old('managed_event_ids', isset($user) ? $user->managedEvents->pluck('id')->map(fn($id) => (string) $id)->all() : []);
    @endphp
    <x-admin.field label="Managed Event Scope" name="managed_event_ids" for="u-events" hint="Hold Ctrl/Cmd to select several. Leave empty for no restriction (all events).">
        <select id="u-events" name="managed_event_ids[]" class="form-select no-select2 a-m-multi" multiple size="8">
            @foreach(($events ?? collect()) as $event)
                <option value="{{ $event->id }}" @selected(in_array((string) $event->id, $selectedEventIds))>{{ $event->name }}</option>
            @endforeach
        </select>
    </x-admin.field>
</x-admin.form-section>
