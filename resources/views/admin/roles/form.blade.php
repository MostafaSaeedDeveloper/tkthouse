@php
    $selectedPerms = old('permissions', isset($role) ? $role->permissions->pluck('name')->toArray() : []);
    $groups = $permissions->groupBy(fn ($p) => str($p->name)->before('.')->before(' ')->toString());
@endphp
<x-admin.form-section title="Role" cols="1">
    <x-admin.field label="Role Name" name="name" for="r-name" required><input id="r-name" name="name" class="form-control" value="{{ old('name', $role->name ?? '') }}" required></x-admin.field>
</x-admin.form-section>

<x-admin.form-section title="Permissions" description="Tick what this role is allowed to do. Permissions are grouped by area." cols="1">
    <div>
        <div class="a-m-perm-toolbar">
            <button type="button" class="a-btn a-btn--outline a-btn--sm" data-perm-all="1">Select all</button>
            <button type="button" class="a-btn a-btn--ghost a-btn--sm" data-perm-all="0">Clear all</button>
        </div>
        @if($permissions->isEmpty())
            <x-admin.empty icon="fa-key" title="No permissions yet" text="Create permissions first, then assign them to roles." />
        @else
        <div class="a-m-perm-groups" id="perm-groups">
            @foreach($groups as $group => $items)
                <fieldset class="a-m-perm-group">
                    <div class="a-m-perm-group__head">
                        <h4>{{ str($group)->replace(['-', '_'], ' ') }}</h4>
                        <button type="button" class="a-btn a-btn--ghost a-btn--sm" data-perm-group="1" aria-label="Toggle all {{ $group }} permissions">Toggle</button>
                    </div>
                    <div class="a-m-perm-group__body">
                        @foreach($items as $permission)
                            <label class="a-m-perm"><input type="checkbox" name="permissions[]" value="{{ $permission->name }}" @checked(in_array($permission->name, $selectedPerms))><span>{{ $permission->name }}</span></label>
                        @endforeach
                    </div>
                </fieldset>
            @endforeach
        </div>
        @endif
    </div>
</x-admin.form-section>

@once
@push('scripts')
<script>
(function () {
    var root = document.getElementById('perm-groups');
    if (!root) return;
    document.querySelectorAll('[data-perm-all]').forEach(function (b) {
        b.addEventListener('click', function () {
            var on = b.getAttribute('data-perm-all') === '1';
            root.querySelectorAll('input[type=checkbox]').forEach(function (c) { c.checked = on; });
        });
    });
    root.querySelectorAll('[data-perm-group]').forEach(function (b) {
        b.addEventListener('click', function () {
            var boxes = b.closest('.a-m-perm-group').querySelectorAll('input[type=checkbox]');
            var all = Array.prototype.every.call(boxes, function (c) { return c.checked; });
            boxes.forEach(function (c) { c.checked = !all; });
        });
    });
})();
</script>
@endpush
@endonce
