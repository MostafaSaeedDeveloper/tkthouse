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
                @elseif($def['type'] === 'users')
                    <div class="a-field is-full">
                        <label class="form-label" for="s-{{ $key }}">{{ $def['label'] }}</label>
                        <input type="hidden" name="{{ $key }}[]" value=""> {{-- keeps the key in the request so clearing the list is saved --}}
                        <x-admin.multiselect :name="$key" :id="'s-'.$key" :label="$def['label']" :options="($orderTools['eligible'] ?? collect())->pluck('name', 'id')->all()" :selected="empty($val) ? array_filter([\App\Support\SystemSettings::get('order_default_assignee_id')]) : (array) $val" placeholder="Search agents…" empty-text="— none —" />
                        <p class="a-hint">{{ $def['help'] ?? '' }} Only active users who are allowed to receive orders are listed.</p>
                    </div>
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
                @elseif($def['type'] === 'code')
                    <x-admin.field class="is-full" :label="$def['label']" :name="$key" :for="'s-'.$key" :hint="$def['help'] ?? null"><textarea id="s-{{ $key }}" class="form-control" rows="9" name="{{ $key }}" spellcheck="false" autocomplete="off" dir="ltr" style="font-family:ui-monospace,SFMono-Regular,Menlo,monospace;font-size:13px" placeholder="<script>...</script>">{{ $val }}</textarea></x-admin.field>
                @elseif($def['type'] === 'text')
                    <x-admin.field class="is-full" :label="$def['label']" :name="$key" :for="'s-'.$key"><textarea id="s-{{ $key }}" class="form-control" rows="3" name="{{ $key }}">{{ $val }}</textarea></x-admin.field>
                @else
                    <x-admin.field :label="$def['label']" :name="$key" :for="'s-'.$key" :hint="$def['help'] ?? null">
                        <input id="s-{{ $key }}" type="{{ $def['type'] === 'int' ? 'number' : 'text' }}" class="form-control" name="{{ $key }}" value="{{ $val }}">
                    </x-admin.field>
                @endif
            @endforeach
        </x-admin.form-section>

        @if($section === 'wallet')
            @php
                $walletReady = \App\Services\Wallet\Wallet::configured();
                $hasCert = \App\Services\Wallet\WalletSettings::secrets();
            @endphp
            <x-admin.form-section title="WalletWallet service" description="Used when “How passes are made” is set to WalletWallet. Your API key is stored encrypted and never shown again." cols="2">
                <x-admin.field label="WalletWallet API key" name="wallet_ww_api_key" for="s-wallet_ww_api_key" :hint="$hasCert['ww_api_key'] ? 'A key is saved. Paste a new one only to replace it.' : 'Starts with ww_live_ — from your WalletWallet dashboard.'">
                    <input id="s-wallet_ww_api_key" type="text" class="form-control" name="wallet_ww_api_key" autocomplete="off" spellcheck="false" placeholder="{{ $hasCert['ww_api_key'] ? '••••••••••••••••' : 'ww_live_…' }}">
                    @error('wallet_ww_api_key')<div class="a-error" role="alert">{{ $message }}</div>@enderror
                </x-admin.field>
            </x-admin.form-section>
            <x-admin.form-section title="Apple certificates" description="Uploaded files are validated and stored encrypted. They are never shown again." cols="2">
                <div class="a-field is-full">
                    <x-admin.badge :variant="$walletReady ? 'success' : 'warning'">{{ $walletReady ? 'Active — customers see “Add to Apple Wallet”' : 'Not active — the button stays hidden until everything below is set' }}</x-admin.badge>
                    @error('wallet_certificate')<div class="a-error" role="alert">{{ $message }}</div>@enderror
                </div>
                <x-admin.field label="Pass Type ID certificate (.p12)" name="wallet_certificate" for="s-wallet_certificate" :hint="$hasCert['p12'] ? 'A certificate is saved. Upload a new file only to replace it.' : 'Export it from Keychain Access as .p12.'">
                    <input id="s-wallet_certificate" type="file" class="form-control" name="wallet_certificate" accept=".p12,.pfx">
                </x-admin.field>
                <x-admin.field label="Certificate password" name="wallet_certificate_password" for="s-wallet_certificate_password" hint="The password you set when exporting the .p12. Leave empty to keep the saved one.">
                    <input id="s-wallet_certificate_password" type="password" class="form-control" name="wallet_certificate_password" autocomplete="new-password">
                </x-admin.field>
                <x-admin.field label="Apple WWDR certificate (.cer / .pem)" name="wallet_wwdr" for="s-wallet_wwdr" :hint="$hasCert['wwdr'] ? 'A WWDR certificate is saved. Upload a new file only to replace it.' : 'Download “Worldwide Developer Relations” from apple.com/certificateauthority.'">
                    <input id="s-wallet_wwdr" type="file" class="form-control" name="wallet_wwdr" accept=".cer,.pem,.crt">
                </x-admin.field>
                @if($hasCert['p12'] || $hasCert['wwdr'] || $hasCert['ww_api_key'])
                    <div class="a-m-switch form-check form-switch is-full" style="grid-column:1/-1">
                        <input class="form-check-input" type="checkbox" id="s-wallet_remove" name="wallet_remove" value="1">
                        <label class="form-check-label" for="s-wallet_remove"><strong>Remove all saved credentials (certificates and API key)</strong><span class="a-hint d-block">Turns “Add to Apple Wallet” off. Passes already in customers’ wallets keep working.</span></label>
                    </div>
                @endif
            </x-admin.form-section>
        @endif

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
