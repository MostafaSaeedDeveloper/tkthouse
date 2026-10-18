@extends('admin.master')
@section('title', $kind === 'payment' ? 'Providers & Merchant Accounts' : 'Integrations')
@push('styles')<link rel="stylesheet" href="{{ asset('admin-v2/misc.css') }}?v={{ filemtime(public_path('admin-v2/misc.css')) }}">@endpush
@section('content')
<x-admin.page-header :title="$kind === 'payment' ? 'Providers & Merchant Accounts' : 'Integrations'" :subtitle="$kind === 'payment' ? 'Paymob and Fawaterak merchant accounts. Credentials are encrypted at rest and never shown again; saving never enables an account.' : 'Messaging and other non-financial services. Payment providers are managed under Settings → Payments.'" :crumbs="['Dashboard' => route('admin.dashboard'), 'Settings', $kind === 'payment' ? 'Payments' : 'Integrations']" />
<x-admin.flash />
@include('admin.settings._nav')
@if($kind === 'payment')@include('admin.settings._payments-nav')@else
    @canany(['payments.accounts.view', 'settings.integrations.view'])<p class="a-hint">Looking for Paymob or Fawaterak? They moved to <a href="{{ route('admin.payments.providers') }}">Settings → Payments → Providers &amp; Merchant Accounts</a> (nothing was deleted).</p>@endcanany
@endif

@foreach($definitions as $provider => $def)
    <x-admin.card :title="$def['label']" class="a-m-form-card" :id="'int-'.$provider">
        @php $list = $accounts->get($provider, collect())->filter(fn ($a) => request('archived') || ! $a->archived_at); @endphp
        @forelse($list as $account)
            <details class="a-m-account" @if($errors->any() && old('_account') == $account->id) open @endif>
                <summary class="a-btn-group" style="cursor:pointer;gap:10px;flex-wrap:wrap">
                    <strong>{{ $account->name }}</strong>
                    <x-admin.badge :variant="$account->environment === 'production' ? 'dark' : 'info'">{{ ucfirst($account->environment) }}</x-admin.badge>
                    @if($kind === 'payment' && in_array($provider, ['paymob', 'fake'], true))<x-admin.badge :variant="$account->refund_enabled_at ? 'success' : 'default'">{{ $account->refund_enabled_at ? 'Refunds verified' : 'Refunds: manual only' }}</x-admin.badge>@endif
                    @if($kind === 'payment' && $defaultAccountId === $account->id)<x-admin.badge variant="primary">Default account</x-admin.badge>@endif
                    <x-admin.badge :variant="$account->enabled ? 'success' : 'warning'">{{ $account->archived_at ? 'Archived' : ($account->enabled ? 'Enabled' : 'Disabled') }}</x-admin.badge>
                    <x-admin.badge :variant="match($account->status){'verified'=>'success','error'=>'danger',default=>'warning'}">{{ ucfirst($account->status) }}</x-admin.badge>
                </summary>
                <p class="a-hint">
                    Last test: {{ $account->last_tested_at?->diffForHumans() ?? 'never' }} ·
                    Last success: {{ $account->last_success_at?->diffForHumans() ?? 'never' }}
                    @if($account->last_error)<br><span class="a-error">{{ $account->last_error }}</span>@endif
                </p>
                @if($canManage)
                <form method="POST" action="{{ route('admin.integrations.update', $account) }}" data-a-loading>
                    @csrf @method('PUT')
                    <input type="hidden" name="_account" value="{{ $account->id }}">
                    @include('admin.settings._integration-fields', ['def' => $def, 'account' => $account, 'provider' => $provider])
                    <div class="a-form-actions"><button class="a-btn a-btn--primary" type="submit">Save account</button></div>
                </form>
                @endif
                <div class="a-btn-group" style="margin-top:12px;flex-wrap:wrap">
                    @if($canTest)
                        @if($provider === 'email')
                            <form method="POST" action="{{ route('admin.integrations.test', $account) }}" class="a-btn-group" style="flex-wrap:wrap" data-a-loading>@csrf
                                <input class="form-control" type="email" name="recipient" value="{{ auth()->user()->email }}" placeholder="Send the test to…" required aria-label="Test e-mail recipient" style="min-width:240px">
                                <button class="a-btn a-btn--primary" type="submit"><i class="fa fa-paper-plane"></i> Send test email</button>
                            </form>
                        @endif
                        <form method="POST" action="{{ route('admin.integrations.test', $account) }}" data-a-loading>@csrf<button class="a-btn a-btn--outline" type="submit"><i class="fa fa-plug"></i> Test connection</button></form>
                    @endif
                    @if($canManage)
                    @if($kind === 'payment' && in_array($provider, ['paymob', 'fake'], true))
                        <form method="POST" action="{{ route('admin.integrations.refund-verified', $account) }}" class="a-btn-group">@csrf
                            <input type="hidden" name="on" value="{{ $account->refund_enabled_at ? 0 : 1 }}">
                            <input class="form-control" name="note" placeholder="{{ $account->refund_enabled_at ? 'Why disable?' : 'Sandbox refund run reference' }}" minlength="5" required aria-label="Refund verification note">
                            <button class="a-btn a-btn--outline" type="submit">{{ $account->refund_enabled_at ? 'Disable refunds' : 'Confirm refunds verified' }}</button>
                        </form>
                    @endif
                    @endif
                    @if($canManage && $kind === 'payment' && $account->isUsable() && $defaultAccountId !== $account->id)
                        <form method="POST" action="{{ route('admin.integrations.default', $account) }}">@csrf<button class="a-btn a-btn--outline" type="submit">Make default</button></form>
                    @endif
                    @if($canManage)
                        @if($account->enabled)
                            <form method="POST" action="{{ route('admin.integrations.disable', $account) }}" data-a-confirm="Disable this account?">@csrf<button class="a-btn a-btn--outline" type="submit">Disable</button></form>
                        @else
                            <form method="POST" action="{{ route('admin.integrations.enable', $account) }}" @if($account->environment === 'production') data-a-confirm="Enable this account in PRODUCTION?" data-a-confirm-ok="Enable" @endif>
                                @csrf <input type="hidden" name="confirm_production" value="1">
                                <button class="a-btn a-btn--success" type="submit" @disabled($account->status !== 'verified')>Enable</button>
                            </form>
                            @if($provider === 'fawaterak' && $account->environment === 'sandbox' && $account->status !== 'verified')
                                <form method="POST" action="{{ route('admin.integrations.attest', $account) }}" class="a-btn-group">@csrf
                                    <input class="form-control" name="note" placeholder="Sandbox run reference (required)" minlength="5" required aria-label="Sandbox verification note">
                                    <button class="a-btn a-btn--outline" type="submit">Record sandbox verification</button>
                                </form>
                            @endif
                            @if($account->isReferenced())
                                <form method="POST" action="{{ route('admin.integrations.archive', $account) }}" data-a-confirm="Archive this account? It stays usable for historical payments only.">@csrf<button class="a-btn a-btn--outline" type="submit">Archive</button></form>
                            @else
                                <form method="POST" action="{{ route('admin.integrations.destroy', $account) }}">@csrf @method('DELETE')<button class="a-btn a-btn--danger" type="submit">Delete</button></form>
                            @endif
                        @endif
                    @endif
                </div>
            </details>
            <hr>
        @empty
            <x-admin.empty title="No accounts yet" text="Add the first {{ $def['label'] }} account below." icon="fa-plug" />
        @endforelse

        @if($canManage)
            <details>
                <summary class="a-btn a-btn--outline" style="display:inline-flex"><i class="fa fa-plus"></i> Add {{ $def['label'] }} account</summary>
                <form method="POST" action="{{ route('admin.integrations.store', $provider) }}" data-a-loading style="margin-top:14px">
                    @csrf
                    @include('admin.settings._integration-fields', ['def' => $def, 'account' => null, 'provider' => $provider])
                    <div class="a-form-actions"><button class="a-btn a-btn--primary" type="submit">Create account</button></div>
                </form>
            </details>
        @endif
    </x-admin.card>
@endforeach
@endsection
