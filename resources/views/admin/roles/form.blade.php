@php
    $selectedPerms = old('permissions', isset($role) ? $role->permissions->pluck('name')->toArray() : []);
    $groups = $permissions->groupBy(fn ($p) => str($p->name)->before('.')->before(' ')->toString());
@endphp
<x-admin.form-section title="Role">
    <x-admin.input label="Role Name" name="name" id="r-name" :value="$role->name ?? ''" required />
</x-admin.form-section>

<x-admin.form-section title="Permissions" description="Tick what this role is allowed to do. Permissions are grouped by area." cols="1">
    <div>
        <div class="a-m-perm-toolbar">
            <button type="button" class="a-btn a-btn--outline a-btn--sm" data-perm-all="1">Select all</button>
            <button type="button" class="a-btn a-btn--ghost a-btn--sm" data-perm-all="0">Clear all</button>
            <span class="a-muted a-m-perm-count" id="perm-count" aria-live="polite"></span>
        </div>
        @if($permissions->isEmpty())
            <x-admin.empty icon="fa-key" title="No permissions yet" text="Create permissions first, then assign them to roles." />
        @else
        <div class="a-m-perm-groups" id="perm-groups">
            @foreach($groups as $group => $items)
                <fieldset class="a-m-perm-group">
                    <legend class="a-sr-only">{{ str($group)->replace(['-', '_'], ' ') }} permissions</legend>
                    <div class="a-m-perm-group__head">
                        <span class="a-m-perm-group__title" aria-hidden="true">{{ str($group)->replace(['-', '_'], ' ') }} <span class="a-muted">{{ $items->count() }}</span></span>
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
    var counter = document.getElementById('perm-count');
    function count() {
        var all = root.querySelectorAll('input[type=checkbox]');
        var on = root.querySelectorAll('input[type=checkbox]:checked');
        if (counter) counter.textContent = on.length + ' of ' + all.length + ' selected';
    }
    root.addEventListener('change', count);
    count();
    document.querySelectorAll('[data-perm-all]').forEach(function (b) {
        b.addEventListener('click', function () {
            var on = b.getAttribute('data-perm-all') === '1';
            root.querySelectorAll('input[type=checkbox]').forEach(function (c) { c.checked = on; });
            count();
        });
    });
    root.querySelectorAll('[data-perm-group]').forEach(function (b) {
        b.addEventListener('click', function () {
            var boxes = b.closest('.a-m-perm-group').querySelectorAll('input[type=checkbox]');
            var all = Array.prototype.every.call(boxes, function (c) { return c.checked; });
            boxes.forEach(function (c) { c.checked = !all; });
            count();
        });
    });
})();
</script>
@endpush
@endonce
