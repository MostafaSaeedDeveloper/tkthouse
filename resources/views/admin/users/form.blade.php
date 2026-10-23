<x-admin.form-section title="Account" description="Sign-in details for this admin user.">
    <x-admin.field label="Name" name="name" for="u-name" required><input id="u-name" name="name" class="form-control" value="{{ old('name',$user->name ?? '') }}" required></x-admin.field>
    <x-admin.field label="Username" name="username" for="u-username" required><input id="u-username" name="username" class="form-control" value="{{ old('username',$user->username ?? '') }}" required></x-admin.field>
    <x-admin.field label="Email" name="email" for="u-email" required><input id="u-email" type="email" name="email" class="form-control" value="{{ old('email',$user->email ?? '') }}" required></x-admin.field>
    <x-admin.field label="Password" name="password" for="u-password" :required="!isset($user)" :hint="isset($user) ? 'Leave empty to keep the current password.' : null"><div style="display:flex;gap:8px"><input id="u-password" type="password" name="password" class="form-control" autocomplete="new-password" minlength="8" @required(!isset($user))><button type="button" class="a-btn a-btn--outline" data-pw-toggle aria-controls="u-password" aria-pressed="false">Show</button></div></x-admin.field>
</x-admin.form-section>

@php
    // The boxes show the EFFECTIVE permissions (role + direct), so picking a role ticks what it grants and everything stays editable.
    $selectedPermissions = session()->hasOldInput() ? (array) old('permissions', []) : (isset($user) ? $user->getAllPermissions()->pluck('name')->toArray() : []);
    $selectedEventIds = old('managed_event_ids', isset($user) ? $user->managedEvents->pluck('id')->map(fn($id) => (string) $id)->all() : []);
    $permGroups = $permissions->groupBy(fn ($p) => str($p->name)->before('.')->before(' ')->toString());
@endphp
<x-admin.form-section title="Role" description="Choosing a role ticks the permissions it grants below. You can still add or remove permissions for this user." cols="1">
    <x-admin.field label="Role" name="role" for="u-role">
        <select id="u-role" name="role" class="form-select" data-role-perms='@json($rolePermissions ?? [])'>
            <option value="">-- No Role --</option>
            @foreach($roles as $role)
                <option value="{{ $role->name }}" @selected(old('role', isset($user) ? $user->roles->first()?->name : '') === $role->name)>{{ \App\Support\RoleLabel::of($role) }}</option>
            @endforeach
        </select>
    </x-admin.field>
</x-admin.form-section>

<x-admin.form-section title="Permissions" description="Ticked = this user can do it. Ones the chosen role grants are marked “from role”. They stay on while the role is set; tick extra ones to add permissions for this user only." cols="1">
    <input type="hidden" name="permissions_effective" value="1">
    <div id="u-perms">
        <div class="a-m-perm-toolbar">
            <input type="search" class="form-control a-user-search" placeholder="Filter permissions…" aria-label="Filter permissions" data-perm-filter>
            <button type="button" class="a-btn a-btn--ghost a-btn--sm" data-perm-reset>Reset to role</button>
            <span class="a-table__sub" data-perm-count></span>
        </div>
        <p class="a-hint a-user-perm-warn" data-perm-warn hidden role="status"></p>
        <div class="a-m-perm-groups">
            @foreach($permGroups as $group => $items)
                <fieldset class="a-m-perm-group" data-perm-group-box>
                    <div class="a-m-perm-group__head"><h4>{{ str($group)->replace(['-', '_'], ' ') }}</h4></div>
                    <div class="a-m-perm-group__body">
                        @foreach($items as $permission)
                            <label class="a-m-perm" data-perm-row="{{ $permission->name }}"><input type="checkbox" name="permissions[]" value="{{ $permission->name }}" @checked(in_array($permission->name, $selectedPermissions))><span>{{ $permission->name }}</span><em class="a-user-inherited" hidden>from role</em></label>
                        @endforeach
                    </div>
                </fieldset>
            @endforeach
        </div>
    </div>
</x-admin.form-section>

<x-admin.form-section title="Event scope" description="Limit which events this user can manage. Tick none for no restriction (all events)." cols="1">
    @if(($events ?? collect())->isEmpty())
        <span class="a-table__sub">No events yet.</span>
    @else
        <x-admin.multiselect name="managed_event_ids" label="Managed events" :options="$events->pluck('name', 'id')->all()" :selected="$selectedEventIds" placeholder="Search events…" empty-text="All events (no restriction)" />
    @endif
</x-admin.form-section>

<x-admin.form-section title="Status & orders" description="Inactive users cannot sign in or receive orders; their history is kept." cols="1">
    <input type="hidden" name="account_flags" value="1">
    <div class="a-m-switch form-check form-switch">
        <input class="form-check-input" type="checkbox" id="u-active" name="is_active" value="1" @checked(old('is_active', isset($user) ? $user->is_active : true))>
        <label class="form-check-label" for="u-active"><strong>Active</strong><span class="a-hint d-block">Turn off to block sign-in without deleting the account.</span></label>
    </div>
    <div class="a-m-switch form-check form-switch">
        <input class="form-check-input" type="checkbox" id="u-orders" name="can_receive_orders" value="1" @checked(old('can_receive_orders', isset($user) ? $user->can_receive_orders : false))>
        <label class="form-check-label" for="u-orders"><strong>Can receive assigned orders</strong><span class="a-hint d-block">The user also needs to be able to open Orders (orders.view). They then see only the orders assigned to them, unless they have orders.view_all.</span></label>
    </div>
</x-admin.form-section>

@if(auth()->user()->can('settings.payment-accounts.manage') || \App\Orders\OrderAccess::isSuper(auth()->user()))
@php
    $pMethods = \App\Models\PaymentMethod::query()->where('is_active', true)->orderBy('id')->get(['code', 'name']);
    $pAllowed = old('allowed_payment_methods', isset($user) ? ($user->allowed_payment_methods ?? []) : []);
@endphp
<x-admin.form-section title="Payment methods" description="Which payment methods the customers of this agent's orders can choose when they pay. Every agent shares the same merchant account; this only controls which methods are shown. Leave empty to show every active method." cols="1">
    <input type="hidden" name="payment_flags" value="1">
    <x-admin.multiselect name="allowed_payment_methods" label="Payment methods" :options="$pMethods->pluck('name', 'code')->all()" :selected="$pAllowed" placeholder="Search payment methods…" empty-text="All payment methods" />
    @unless(\App\Services\Payments\Routing\PaymentAccountResolver::routingEnabled())
        <p class="a-hint" style="margin-top:10px"><i class="fa fa-circle-info" aria-hidden="true"></i> Not applied yet: turn on <strong>Show each agent's payment methods</strong> in Settings → Payments, then every customer of this agent sees only the methods chosen here.</p>
    @endunless
</x-admin.form-section>
@endif

@push('scripts')
<script>
(function () {
    function $$(sel, r) { return Array.prototype.slice.call((r || document).querySelectorAll(sel)); }
    var role = document.getElementById('u-role'), root = document.getElementById('u-perms');
    if (role && root) {
        var map = {}; try { map = JSON.parse(role.getAttribute('data-role-perms') || '{}'); } catch (e) {}
        var rows = $$('[data-perm-row]', root), count = root.querySelector('[data-perm-count]'), warn = root.querySelector('[data-perm-warn]');
        var prev = role.value;
        function granted(name) { return map[name] || []; }
        function box(r) { return r.querySelector('input'); }
        function setTicks(names) { var on = {}; names.forEach(function (n) { on[n] = true; }); rows.forEach(function (r) { box(r).checked = !!on[r.getAttribute('data-perm-row')]; }); }
        function ticked() { return rows.filter(function (r) { return box(r).checked; }).map(function (r) { return r.getAttribute('data-perm-row'); }); }
        function refresh() {
            var base = granted(role.value), t, extra = 0;
            rows.forEach(function (r) {
                var name = r.getAttribute('data-perm-row'), fromRole = base.indexOf(name) !== -1;
                r.querySelector('.a-user-inherited').hidden = !fromRole;
                if (box(r).checked && !fromRole) extra++;
                if (fromRole) box(r).checked = true;
            });
            t = ticked();
            if (count) count.textContent = t.length + ' ticked · ' + base.length + ' from role · ' + extra + ' extra';
            if (warn) warn.hidden = true;
        }
        // Picking another role swaps the role's permissions but keeps extras that were ticked by hand.
        role.addEventListener('change', function () {
            var keep = ticked().filter(function (n) { return granted(prev).indexOf(n) === -1; });
            setTicks(granted(role.value).concat(keep));
            prev = role.value;
            // A role that works assigned orders (Agent, Order Agent) means this person should receive them: on by default, never turned off here.
            var receive = document.getElementById('u-orders');
            if (receive && granted(role.value).indexOf('orders.view_assigned') !== -1) receive.checked = true;
            refresh();
        });
        root.addEventListener('change', refresh);
        var reset = root.querySelector('[data-perm-reset]');
        if (reset) reset.addEventListener('click', function () { setTicks(granted(role.value)); refresh(); });
        refresh();
        var f = root.querySelector('[data-perm-filter]');
        f.addEventListener('input', function () {
            var q = f.value.trim().toLowerCase();
            rows.forEach(function (r) { r.style.display = !q || r.getAttribute('data-perm-row').toLowerCase().indexOf(q) !== -1 ? '' : 'none'; });
            root.querySelectorAll('[data-perm-group-box]').forEach(function (g) { g.style.display = g.querySelector('[data-perm-row]:not([style*="none"])') ? '' : 'none'; });
        });
    }
    var pw = document.querySelector('[data-pw-toggle]');
    if (pw) pw.addEventListener('click', function () {
        var i = document.getElementById('u-password'), show = i.type === 'password';
        i.type = show ? 'text' : 'password'; pw.textContent = show ? 'Hide' : 'Show'; pw.setAttribute('aria-pressed', show ? 'true' : 'false');
    });
})();
</script>
@endpush
