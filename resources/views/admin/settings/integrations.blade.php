@extends('admin.master')
@section('title', 'Integrations')
@push('styles')<link rel="stylesheet" href="{{ asset('admin-v2/misc.css') }}?v={{ filemtime(public_path('admin-v2/misc.css')) }}">@endpush
@section('content')
<x-admin.page-header title="Integrations" subtitle="Credentials are encrypted at rest and never shown again. Saving never enables an account." :crumbs="['Dashboard' => route('admin.dashboard'), 'Settings', 'Integrations']" />
<x-admin.flash />
@include('admin.settings._nav')

@foreach($definitions as $provider => $def)
    <x-admin.card :title="$def['label']" class="a-m-form-card" :id="'int-'.$provider">
        @php $list = $accounts->get($provider, collect()); @endphp
        @forelse($list as $account)
            <details class="a-m-account" @if($errors->any() && old('_account') == $account->id) open @endif>
                <summary class="a-btn-group" style="cursor:pointer;gap:10px;flex-wrap:wrap">
                    <strong>{{ $account->name }}</strong>
                    <x-admin.badge :variant="$account->environment === 'production' ? 'dark' : 'info'">{{ ucfirst($account->environment) }}</x-admin.badge>
                    <x-admin.badge :variant="$account->enabled ? 'success' : 'warning'">{{ $account->enabled ? 'Enabled' : 'Disabled' }}</x-admin.badge>
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
                    @include('admin.settings._integration-fields', ['def' => $def, 'account' => $account])
                    <div class="a-form-actions"><button class="a-btn a-btn--primary" type="submit">Save account</button></div>
                </form>
                @endif
                <div class="a-btn-group" style="margin-top:12px;flex-wrap:wrap">
                    @if($canTest)
                        <form method="POST" action="{{ route('admin.integrations.test', $account) }}" data-a-loading>@csrf<button class="a-btn a-btn--outline" type="submit"><i class="fa fa-plug"></i> Test connection</button></form>
                    @endif
                    @if($canManage)
                        @if($account->enabled)
                            <form method="POST" action="{{ route('admin.integrations.disable', $account) }}" data-a-confirm="Disable this account?">@csrf<button class="a-btn a-btn--outline" type="submit">Disable</button></form>
                        @else
                            <form method="POST" action="{{ route('admin.integrations.enable', $account) }}" @if($account->environment === 'production') data-a-confirm="Enable this account in PRODUCTION?" data-a-confirm-ok="Enable" @endif>
                                @csrf <input type="hidden" name="confirm_production" value="1">
                                <button class="a-btn a-btn--success" type="submit" @disabled($account->status !== 'verified')>Enable</button>
                            </form>
                            <form method="POST" action="{{ route('admin.integrations.destroy', $account) }}">@csrf @method('DELETE')<button class="a-btn a-btn--danger" type="submit">Delete</button></form>
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
                    @include('admin.settings._integration-fields', ['def' => $def, 'account' => null])
                    <div class="a-form-actions"><button class="a-btn a-btn--primary" type="submit">Create account</button></div>
                </form>
            </details>
        @endif
    </x-admin.card>
@endforeach
@endsection
