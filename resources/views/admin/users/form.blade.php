<x-admin.form-section title="Account" description="Sign-in details for this admin user.">
    <x-admin.field label="Name" name="name" for="u-name" required><input id="u-name" name="name" class="form-control" value="{{ old('name',$user->name ?? '') }}" required></x-admin.field>
    <x-admin.field label="Username" name="username" for="u-username" required><input id="u-username" name="username" class="form-control" value="{{ old('username',$user->username ?? '') }}" required></x-admin.field>
    <x-admin.field label="Email" name="email" for="u-email" required><input id="u-email" type="email" name="email" class="form-control" value="{{ old('email',$user->email ?? '') }}" required></x-admin.field>
    <x-admin.field label="Password" name="password" for="u-password" :required="!isset($user)" :hint="isset($user) ? 'Leave empty to keep the current password.' : null"><div style="display:flex;gap:8px"><input id="u-password" type="password" name="password" class="form-control" autocomplete="new-password" minlength="8" @required(!isset($user))><button type="button" class="a-btn a-btn--outline" data-pw-toggle aria-controls="u-password" aria-pressed="false">Show</button></div></x-admin.field>
</x-admin.form-section>

@php
    $selectedPermissions = old('permissions', isset($user) ? $user->permissions->pluck('name')->toArray() : []);
    $selectedEventIds = old('managed_event_ids', isset($user) ? $user->managedEvents->pluck('id')->map(fn($id) => (string) $id)->all() : []);
    $permGroups = $permissions->groupBy(fn ($p) => str($p->name)->before('.')->before(' ')->toString());
@endphp
<x-admin.form-section title="Role" description="The role grants a base set of permissions." cols="1">
    <x-admin.field label="Role" name="role" for="u-role">
        <select id="u-role" name="role" class="form-select" data-role-perms='@json($rolePermissions ?? [])'>
            <option value="">-- No Role --</option>
            @foreach($roles as $role)
                <option value="{{ $role->name }}" @selected(old('role', isset($user) ? $user->roles->first()?->name : '') === $role->name)>{{ $role->name }}</option>
            @endforeach
        </select>
    </x-admin.field>
</x-admin.form-section>

<x-admin.form-section title="Custom permissions" description="Extra permissions on top of the role. Ones the role already grants are marked “from role”." cols="1">
    <div id="u-perms">
        <div class="a-m-perm-toolbar">
            <input type="search" class="form-control a-user-search" placeholder="Filter permissions…" aria-label="Filter permissions" data-perm-filter>
            <button type="button" class="a-btn a-btn--ghost a-btn--sm" data-perm-clear>Clear custom</button>
            <span class="a-table__sub" data-perm-count></span>
        </div>
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
        <label class="form-check-label" for="u-orders"><strong>Can receive assigned orders</strong><span class="a-hint d-block">Also needs the orders.view_assigned permission (e.g. the "Order Agent" role). Used by automatic assignment.</span></label>
    </div>
</x-admin.form-section>

@if(auth()->user()->can('settings.payment-accounts.manage') || \App\Orders\OrderAccess::isSuper(auth()->user()))
@php
    $pAccounts = \App\Models\IntegrationAccount::query()->whereIn('provider', ['paymob', 'fawaterak', 'fake'])->whereNull('archived_at')->orderBy('provider')->orderBy('name')->get();
    $pMethods = \App\Models\PaymentMethod::query()->where('is_active', true)->orderBy('id')->get(['code', 'name']);
    $pMode = old('payment_routing_mode', isset($user) ? $user->payment_routing_mode : 'default');
    $pAllowed = old('allowed_payment_methods', isset($user) ? ($user->allowed_payment_methods ?? []) : []);
@endphp
<x-admin.form-section title="Payment configuration" description="Which merchant account collects the payments of this agent's orders. Credentials are never shown here; past payments are never affected." cols="1">
    <input type="hidden" name="payment_flags" value="1">
    <x-admin.field label="Payment account" name="payment_routing_mode" for="u-pmode">
        <select id="u-pmode" name="payment_routing_mode" class="form-select">
            <option value="default" @selected($pMode === 'default')>Use default payment account</option>
            <option value="custom" @selected($pMode === 'custom')>Use custom payment account</option>
        </select>
    </x-admin.field>
    <x-admin.field label="Merchant account" name="payment_account_id" for="u-paccount" hint="Only used with a custom account. Must be enabled and verified to take payments.">
        <select id="u-paccount" name="payment_account_id" class="form-select">
            <option value="">— none —</option>
            @foreach($pAccounts as $a)<option value="{{ $a->id }}" @selected((int) old('payment_account_id', isset($user) ? $user->payment_account_id : 0) === $a->id)>{{ $a->name }} · {{ ucfirst($a->provider) }} · {{ $a->environment }}@unless($a->enabled) (disabled)@endunless</option>@endforeach
        </select>
    </x-admin.field>
    <div class="a-m-switch form-check form-switch">
        <input class="form-check-input" type="checkbox" id="u-prouting" name="payment_routing_enabled" value="1" @checked(old('payment_routing_enabled', isset($user) ? $user->payment_routing_enabled : false))>
        <label class="form-check-label" for="u-prouting"><strong>Routing enabled for this user</strong><span class="a-hint d-block">Also requires "Enable per-user payment routing" in Settings → Payment Routing.</span></label>
    </div>
    <fieldset><legend class="form-label">Allowed payment methods</legend>
        <p class="a-hint">Leave all unchecked to allow every method the account supports.</p>
        <div class="a-chips">@foreach($pMethods as $m)<label class="a-m-perm"><input type="checkbox" name="allowed_payment_methods[]" value="{{ $m->code }}" @checked(in_array($m->code, $pAllowed))><span>{{ $m->name }}</span></label>@endforeach</div>
    </fieldset>
</x-admin.form-section>
@endif

@push('scripts')
<script>
(function () {
    var role = document.getElementById('u-role'), root = document.getElementById('u-perms');
    if (role && root) {
        var map = {}; try { map = JSON.parse(role.getAttribute('data-role-perms') || '{}'); } catch (e) {}
        var rows = root.querySelectorAll('[data-perm-row]'), count = root.querySelector('[data-perm-count]');
        function refresh() {
            var inherited = map[role.value] || [], n = 0;
            rows.forEach(function (r) {
                var from = inherited.indexOf(r.getAttribute('data-perm-row')) !== -1;
                r.querySelector('.a-user-inherited').hidden = !from;
                if (r.querySelector('input').checked) n++;
            });
            if (count) count.textContent = n + ' custom · ' + inherited.length + ' from role';
        }
        role.addEventListener('change', refresh);
        root.addEventListener('change', refresh);
        refresh();
        var f = root.querySelector('[data-perm-filter]');
        f.addEventListener('input', function () {
            var q = f.value.trim().toLowerCase();
            rows.forEach(function (r) { r.style.display = !q || r.getAttribute('data-perm-row').toLowerCase().indexOf(q) !== -1 ? '' : 'none'; });
            root.querySelectorAll('[data-perm-group-box]').forEach(function (g) { g.style.display = g.querySelector('[data-perm-row]:not([style*="none"])') ? '' : 'none'; });
        });
        root.querySelector('[data-perm-clear]').addEventListener('click', function () { rows.forEach(function (r) { r.querySelector('input').checked = false; }); refresh(); });
    }
    var pw = document.querySelector('[data-pw-toggle]');
    if (pw) pw.addEventListener('click', function () {
        var i = document.getElementById('u-password'), show = i.type === 'password';
        i.type = show ? 'text' : 'password'; pw.textContent = show ? 'Hide' : 'Show'; pw.setAttribute('aria-pressed', show ? 'true' : 'false');
    });
})();
</script>
@endpush
