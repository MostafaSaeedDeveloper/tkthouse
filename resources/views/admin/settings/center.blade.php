@extends('admin.master')
@section('title', $group['label'].' Settings')
@push('styles')<link rel="stylesheet" href="{{ asset('admin-v2/misc.css') }}?v={{ filemtime(public_path('admin-v2/misc.css')) }}">@endpush
@section('content')
<x-admin.page-header :title="$group['label']" subtitle="Business settings are stored in the database, validated and audited. Infrastructure secrets stay in the server environment." :crumbs="['Dashboard' => route('admin.dashboard'), 'Settings', $group['label']]" />
<x-admin.flash />
@include('admin.settings._nav')
@if($section === 'payments')@include('admin.settings._payments-nav')@endif

<form method="POST" action="{{ $section === 'general' ? route('admin.settings.update') : route('admin.settings.section.update', $section) }}" enctype="multipart/form-data" data-a-loading>
    @csrf
    @method('PUT')
    <x-admin.card class="{{ $section === 'payments' ? '' : 'a-m-narrow' }}">
        <x-admin.form-section :title="$group['label']" cols="2">
            @foreach($definitions as $key => $def)
                @php $val = old($key, $values[$key] ?? $def['default']); @endphp
                @if($def['type'] === 'bool')
                    <div class="a-m-switch form-check form-switch is-full a-form-grid__full" style="grid-column:1/-1">
                        <input class="form-check-input" type="checkbox" id="s-{{ $key }}" name="{{ $key }}" value="1" @checked($val) @disabled(! auth()->user()->can('settings.update'))>
                        <label class="form-check-label" for="s-{{ $key }}"><strong>{{ $def['label'] }}</strong>@isset($def['help'])<span class="a-hint d-block">{{ $def['help'] }}</span>@endisset</label>
                    </div>
                @elseif($def['type'] === 'file')
                    <x-admin.field :label="$def['label']" :name="$key" :for="'s-'.$key">
                        @if($val)<div class="mb-2"><img src="{{ asset('storage/'.$val) }}" alt="" style="max-height:48px;max-width:100%"></div>@endif
                        <input id="s-{{ $key }}" type="file" class="form-control" name="{{ $key }}" accept="image/*">
                    </x-admin.field>
                @elseif($def['type'] === 'color')
                    <x-admin.field :label="$def['label']" :name="$key" :for="'s-'.$key"><input id="s-{{ $key }}" type="color" class="form-control form-control-color a-m-color" name="{{ $key }}" value="{{ $val }}"></x-admin.field>
                @elseif($def['type'] === 'user')
                    <x-admin.field :label="$def['label']" :name="$key" :for="'s-'.$key" hint="Only active users who are allowed to receive orders are listed.">
                        <select id="s-{{ $key }}" class="form-select" name="{{ $key }}"><option value="">— none —</option>@foreach(($orderTools['eligible'] ?? collect()) as $eu)<option value="{{ $eu->id }}" @selected((int) $val === $eu->id)>{{ $eu->name }}</option>@endforeach</select>
                    </x-admin.field>
                @elseif($def['type'] === 'account')
                    <x-admin.field :label="$def['label']" :name="$key" :for="'s-'.$key" :hint="$def['help'] ?? null">
                        <select id="s-{{ $key }}" class="form-select" name="{{ $key }}"><option value="">— none (legacy payment-method configuration) —</option>
                            @foreach(($paymentAccounts ?? collect())->filter(fn ($r) => $r['usable']) as $r)<option value="{{ $r['account']->id }}" @selected((int) $val === $r['account']->id)>{{ $r['account']->name }} · {{ ucfirst($r['account']->provider) }} · {{ $r['account']->environment }}</option>@endforeach
                        </select>
                    </x-admin.field>
                @elseif($def['type'] === 'select')
                    <x-admin.field :label="$def['label']" :name="$key" :for="'s-'.$key" :hint="$def['help'] ?? null">
                        <select id="s-{{ $key }}" class="form-select" name="{{ $key }}">@foreach($def['options'] as $v => $l)<option value="{{ $v }}" @selected($val == $v)>{{ $l }}</option>@endforeach</select>
                    </x-admin.field>
                @elseif($def['type'] === 'text')
                    <x-admin.field class="is-full" :label="$def['label']" :name="$key" :for="'s-'.$key"><textarea id="s-{{ $key }}" class="form-control" rows="3" name="{{ $key }}">{{ $val }}</textarea></x-admin.field>
                @else
                    <x-admin.field :label="$def['label']" :name="$key" :for="'s-'.$key" :hint="$def['help'] ?? null">
                        <input id="s-{{ $key }}" type="{{ $def['type'] === 'int' ? 'number' : 'text' }}" class="form-control" name="{{ $key }}" value="{{ $val }}">
                    </x-admin.field>
                @endif
            @endforeach
        </x-admin.form-section>

        @if($section === 'payments')
            <x-admin.form-section title="Payment account status" description="Read-only overview. Manage credentials and tests in Settings → Integrations. Turning routing off never changes existing payment attempts." cols="1">
                <x-admin.table stack compact caption="Merchant accounts">
                    <thead><tr><th scope="col">Account</th><th scope="col">Provider</th><th scope="col">Environment</th><th scope="col">Status</th><th scope="col" class="a-num">Agents</th><th scope="col" class="a-num">Attempts</th><th scope="col" class="a-num">Open</th></tr></thead>
                    <tbody>
                    @forelse($paymentAccounts as $r)
                        @php $a = $r['account']; @endphp
                        <tr>
                            <td data-label="Account"><span class="a-table__main">{{ $a->name }}</span></td>
                            <td data-label="Provider">{{ ucfirst($a->provider) }}</td>
                            <td data-label="Environment">{{ ucfirst($a->environment) }}</td>
                            <td data-label="Status"><x-admin.badge :variant="$a->archived_at ? 'dark' : ($r['usable'] ? 'success' : 'warning')">{{ $a->archived_at ? 'Archived' : ($r['usable'] ? 'Ready' : ($a->enabled ? 'Not usable here' : 'Disabled')) }}</x-admin.badge> <x-admin.badge :variant="match($a->status){'verified'=>'success','error'=>'danger',default=>'warning'}">{{ ucfirst($a->status) }}</x-admin.badge></td>
                            <td data-label="Agents" class="a-num">{{ $r['users'] }}</td>
                            <td data-label="Attempts" class="a-num">{{ $r['attempts'] }}</td>
                            <td data-label="Open" class="a-num">{{ $r['open'] }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="a-muted">No merchant accounts yet. Create one in Integrations.</td></tr>
                    @endforelse
                    </tbody>
                </x-admin.table>
            </x-admin.form-section>
        @endif

        @if($section === 'orders')
            <x-admin.form-section title="Rollout safety" description="Assignment is off until you enable it here. Turning it off later never removes assignees or history." cols="1">
                @error('order_assignment_enabled')<div class="a-error" role="alert">{{ $message }}</div>@enderror
                @if(($orderTools['lockedOut'] ?? collect())->isNotEmpty())
                    <div class="a-alert a-alert--warning" role="status">
                        <strong>{{ $orderTools['lockedOut']->count() }} staff member(s)</strong> can open Orders today but would see nothing when assignment is on:
                        {{ $orderTools['lockedOut']->pluck('name')->take(8)->implode(', ') }}.
                    </div>
                    <div class="a-m-switch form-check form-switch"><input class="form-check-input" type="checkbox" id="s-ack" name="acknowledge_lockout" value="1"><label class="form-check-label" for="s-ack"><strong>I understand, enable anyway</strong></label></div>
                    <button type="submit" class="a-btn a-btn--outline" form="grant-view-all-form">Grant orders.view_all to these users</button>
                @else
                    <p class="a-hint">Every current order viewer keeps access when assignment is enabled.</p>
                @endif
                <p class="a-hint">Eligible for automatic assignment now: <strong>{{ ($orderTools['eligible'] ?? collect())->count() }}</strong> user(s). With none eligible, new orders wait in the Unassigned queue.</p>
            </x-admin.form-section>
        @endif

        @if($section === 'security')
            <x-admin.form-section title="Access control" cols="1">
                <p class="a-hint">Manage who can do what from the team area. Settings that could bypass sign-in or payment verification are intentionally not editable here.</p>
                <div class="a-btn-group">
                    @can('roles.view')<a class="a-btn a-btn--outline" href="{{ route('admin.roles.index') }}">Roles</a>@endcan
                    @can('permissions.view')<a class="a-btn a-btn--outline" href="{{ route('admin.permissions.index') }}">Permissions</a>@endcan
                    @can('activity-logs.view')<a class="a-btn a-btn--outline" href="{{ route('admin.activity-logs.index') }}">Activity log</a>@endcan
                </div>
            </x-admin.form-section>
        @endif

        @if($section === 'notifications')
            <x-admin.form-section title="Test notification" description="Sends a test e-mail to your own address only (never to customers)." cols="1">
                <button class="a-btn a-btn--outline" type="submit" form="test-notification-form"><i class="fa fa-paper-plane"></i> Send test e-mail to me</button>
            </x-admin.form-section>
        @endif

        @if($section === 'general')
            <x-admin.form-section title="Platform status (read-only)" description="Controlled by the server environment, not editable here. Timezone changes would shift how existing timestamps are read." cols="2">
                @foreach($platform as $label => $value)
                    <div class="a-kv"><span class="a-muted">{{ $label }}</span> <strong>{{ $value }}</strong></div>
                @endforeach
            </x-admin.form-section>
        @endif

        @if(count(array_filter($definitions, fn($d) => true)) && $section !== 'security')
            <div class="a-form-actions">@can('settings.update')<button class="a-btn a-btn--primary" type="submit">Save settings</button>@endcan</div>
        @endif
    </x-admin.card>
</form>
@if($section === 'orders')<form id="grant-view-all-form" method="POST" action="{{ route('admin.settings.orders.grant-view-all') }}">@csrf</form>@endif
@if($section === 'notifications')<form id="test-notification-form" method="POST" action="{{ route('admin.settings.notifications.test') }}">@csrf</form>@endif
@endsection
