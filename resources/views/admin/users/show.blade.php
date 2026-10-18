@extends('admin.master')
@section('title', $user->name)
@push('styles')<link rel="stylesheet" href="{{ asset('admin-v2/misc.css') }}?v={{ filemtime(public_path('admin-v2/misc.css')) }}">@endpush
@section('content')
@php
    $initials = mb_strtoupper(mb_substr(trim($user->name) ?: $user->username, 0, 1));
    $isMe = auth()->id() === $user->id;
    $role = $user->roles->first()?->name;
    $isStaff = $user->roles->isNotEmpty() || $user->permissions->isNotEmpty();
@endphp
<x-admin.page-header :title="$user->name" :subtitle="'@'.$user->username" :crumbs="['Dashboard' => route('admin.dashboard'), 'Users' => route('admin.users.index'), $user->name]">
    @can('users.update')<a class="a-btn a-btn--primary" href="{{ route('admin.users.edit', $user) }}"><i class="fa fa-pen"></i> Edit user</a>@endcan
    <a class="a-btn a-btn--outline" href="{{ route('admin.users.index') }}">Back to users</a>
</x-admin.page-header>
<x-admin.flash />

<x-admin.card>
    <div style="display:flex;gap:16px;align-items:center;flex-wrap:wrap">
        <span class="a-userbtn__avatar a-user-avatar-lg" aria-hidden="true">@if($user->profileImageUrl())<img src="{{ $user->profileImageUrl() }}" alt="">@else{{ $initials }}@endif</span>
        <div style="min-width:0;flex:1">
            <div class="a-m-chips">
                @if($user->is_active)<x-admin.badge variant="success" dot>Active</x-admin.badge>@else<x-admin.badge variant="danger" dot>Inactive — cannot sign in</x-admin.badge>@endif
                @if($role)<x-admin.badge variant="dark">{{ $role }}</x-admin.badge>@elseif($isStaff)<x-admin.badge>No role · custom permissions</x-admin.badge>@else<x-admin.badge>Customer account</x-admin.badge>@endif
                @if($user->can_receive_orders)<x-admin.badge variant="info">Receives orders</x-admin.badge>@endif
                @if($isMe)<x-admin.badge variant="info">You</x-admin.badge>@endif
            </div>
            <p class="a-table__sub" style="margin-top:8px">{{ $user->email }}@if($user->phone) · {{ $user->phone }}@endif</p>
        </div>
    </div>
</x-admin.card>

<div class="a-grid a-grid--4" style="margin:12px 0">
    <x-admin.kpi label="Assigned orders" :value="$orders['total']" icon="fa-inbox" :hint="$orders['last30'].' in the last 30 days'" />
    <x-admin.kpi label="Sales attributed" :value="$orders['sold']" icon="fa-handshake" hint="Orders credited to this user" />
    <x-admin.kpi label="Permissions" :value="$viaRole->count() + $direct->count()" icon="fa-key" :hint="$viaRole->count().' from role · '.$direct->count().' custom'" />
    <x-admin.kpi label="Last sign-in" :value="$user->last_login_at ? $user->last_login_at->diffForHumans() : 'Never'" icon="fa-clock" :hint="$user->last_login_ip ?: 'No IP recorded'" />
</div>

<div class="a-grid a-grid--main-side">
  <div>
    <x-admin.card title="Access" subtitle="What this user can do. Role permissions change when the role is edited.">
        <dl class="a-kv" style="margin-bottom:16px">
            <div><dt>Role</dt><dd>{{ $role ?? '—' }}</dd></div>
            <div><dt>Event scope</dt><dd>@if($user->managedEvents->isEmpty())All events (no restriction)@else{{ $user->managedEvents->pluck('name')->implode(', ') }}@endif</dd></div>
        </dl>
        <h3 class="a-form-section__title" style="font-size:14px">Custom permissions ({{ $direct->count() }})</h3>
        <div class="a-m-chips" style="margin:8px 0 16px">@forelse($direct as $p)<x-admin.badge>{{ $p }}</x-admin.badge>@empty<span class="a-table__sub">None — role permissions only.</span>@endforelse</div>
        <details>
            <summary style="cursor:pointer;font-weight:700">Permissions from the role ({{ $viaRole->count() }})</summary>
            <div class="a-m-chips" style="margin-top:10px">@forelse($viaRole as $p)<x-admin.badge>{{ $p }}</x-admin.badge>@empty<span class="a-table__sub">No role permissions.</span>@endforelse</div>
        </details>
    </x-admin.card>

    @if($orders['byStatus']->isNotEmpty())
    <x-admin.card title="Assigned orders by status" style="margin-top:12px">
        <div class="a-m-chips">@foreach($orders['byStatus'] as $status => $count)<span><x-admin.status :status="$status" kind="order" /> <strong>{{ $count }}</strong></span>@endforeach</div>
    </x-admin.card>
    @endif

    <x-admin.card title="Recent activity" subtitle="Changes made to this account." style="margin-top:12px">
        @forelse($activity as $a)
            <div style="display:flex;justify-content:space-between;gap:12px;padding:8px 0;border-bottom:1px solid var(--a-line)">
                <span>{{ $a->description }}<span class="a-table__sub"> · by {{ $a->causer?->name ?? 'system' }}</span></span>
                <time class="a-table__sub" datetime="{{ $a->created_at->toIso8601String() }}" title="{{ $a->created_at->format('Y-m-d H:i') }}" style="white-space:nowrap">{{ $a->created_at->diffForHumans() }}</time>
            </div>
        @empty
            <p class="a-table__sub">No changes recorded yet.</p>
        @endforelse
    </x-admin.card>
  </div>
  <div>
    <x-admin.card title="Account">
        <dl class="a-kv">
            <div><dt>Username</dt><dd>{{ $user->username }}</dd></div>
            <div><dt>Email</dt><dd style="overflow-wrap:anywhere">{{ $user->email }}</dd></div>
            <div><dt>Phone</dt><dd>{{ $user->phone ?: '—' }}</dd></div>
            <div><dt>Created</dt><dd>{{ $user->created_at?->format('d M Y') }}</dd></div>
            <div><dt>Last sign-in</dt><dd>{{ $user->last_login_at?->format('d M Y, H:i') ?? 'Never' }}</dd></div>
        </dl>
    </x-admin.card>
    @if($canSeePayment)
    <x-admin.card title="Payment configuration" style="margin-top:12px">
        <dl class="a-kv">
            <div><dt>Account mode</dt><dd>{{ $user->payment_routing_mode === 'custom' ? 'Custom account' : 'Default account' }}</dd></div>
            @if($user->payment_routing_mode === 'custom')
            <div><dt>Merchant account</dt><dd>{{ $paymentAccount ? $paymentAccount->name.' · '.ucfirst($paymentAccount->provider) : '—' }}</dd></div>
            <div><dt>Routing</dt><dd>{{ $user->payment_routing_enabled ? 'Enabled' : 'Off' }}</dd></div>
            <div><dt>Allowed methods</dt><dd>{{ $user->allowed_payment_methods ? implode(', ', $user->allowed_payment_methods) : 'All the account supports' }}</dd></div>
            @endif
        </dl>
    </x-admin.card>
    @endif
  </div>
</div>
@endsection
