@extends('admin.master')
@section('title', 'Webhooks')
@push('styles')<link rel="stylesheet" href="{{ asset('admin-v2/misc.css') }}?v={{ filemtime(public_path('admin-v2/misc.css')) }}">@endpush
@section('content')
@php $ago = fn ($d) => $d ? \Illuminate\Support\Carbon::parse($d)->diffForHumans() : 'never'; @endphp
<x-admin.page-header title="Webhooks" subtitle="Provider notifications. A URL being present does not mean it is connected: Configured = an operator confirmed it in the provider portal, Verified = an authentic event was received." :crumbs="['Dashboard' => route('admin.dashboard'), 'Settings', 'Payments', 'Webhooks']" />
<x-admin.flash />
@include('admin.settings._nav')
@include('admin.settings._payments-nav')

@foreach($providers as $key => $p)
<x-admin.card :title="$p['label']" class="a-m-form-card">
    <div class="a-field"><label class="form-label" for="wh-{{ $key }}">Webhook URL</label>
        <div class="input-group"><input id="wh-{{ $key }}" class="form-control" readonly value="{{ $p['url'] }}"><button type="button" class="a-btn a-btn--outline" data-copy="#wh-{{ $key }}">Copy</button></div>
        <div class="a-hint">{{ $p['note'] }}</div></div>
    <ul class="a-hint" style="margin:8px 0 12px 18px">@foreach($p['events'] as $e)<li>{{ $e }}</li>@endforeach</ul>
    <p class="a-hint">Last received: <strong>{{ $ago($providerStats[$key]['last']) }}</strong> · last successful: <strong>{{ $ago($providerStats[$key]['ok']) }}</strong> · last failed: <strong>{{ $ago($providerStats[$key]['fail']) }}</strong></p>
    <x-admin.table stack compact caption="{{ $p['label'] }} merchant accounts">
        <thead><tr><th>Account</th><th>Environment</th><th>Configuration</th><th>Verification</th><th>Last event</th><th><span class="a-sr-only">Actions</span></th></tr></thead>
        <tbody>
        @forelse($accounts->where('provider', $key) as $a)
            @php $s = $stats[$a->id]; @endphp
            <tr>
                <td data-label="Account"><span class="a-table__main">{{ $a->name }}</span></td>
                <td data-label="Environment">{{ ucfirst($a->environment) }}</td>
                <td data-label="Configuration">@if($a->webhook_configured_at)<x-admin.badge variant="info">Configured</x-admin.badge><span class="a-table__sub">{{ $a->webhook_configured_at->format('d M Y') }}</span>@else<x-admin.badge variant="warning">Not confirmed</x-admin.badge>@endif
                    @if($key === 'paymob' && ! $a->secret('hmac_secret'))<span class="a-table__sub">No HMAC secret stored</span>@endif</td>
                <td data-label="Verification">@if($s['verified'])<x-admin.badge variant="success">Verified</x-admin.badge>@else<x-admin.badge variant="default">Not verified yet</x-admin.badge>@endif</td>
                <td data-label="Last event">{{ $ago($s['last']) }}</td>
                <td data-label="Actions">@if($canConfigure && ! $a->webhook_configured_at)<form method="POST" action="{{ route('admin.payments.webhooks.configured', $a) }}">@csrf<button class="a-btn a-btn--outline a-btn--sm" type="submit">Mark as configured</button></form>@endif</td>
            </tr>
        @empty
            <tr><td colspan="6" class="a-muted">No {{ $p['label'] }} merchant accounts yet.</td></tr>
        @endforelse
        </tbody>
    </x-admin.table>
</x-admin.card>
@endforeach

<x-admin.card flush title="Webhook events" subtitle="Whitelisted fields only; secrets, card data and raw bodies are not shown.">
    <x-admin.filters :action="route('admin.payments.webhooks')" :reset="route('admin.payments.webhooks')">
        <div class="a-field"><label class="form-label" for="wp">Provider</label><select id="wp" name="provider" class="form-select"><option value="">All</option>@foreach($providers as $k => $p)<option value="{{ $k }}" @selected(request('provider') === $k)>{{ $p['label'] }}</option>@endforeach</select></div>
        <div class="a-field"><label class="form-label" for="wst">Processing</label><select id="wst" name="status" class="form-select"><option value="">All</option>@foreach(['received','processed','ignored','failed','needs_review'] as $s)<option value="{{ $s }}" @selected(request('status') === $s)>{{ str_replace('_', ' ', $s) }}</option>@endforeach</select></div>
        <div class="a-field"><label class="form-label" for="wq">Transaction</label><input id="wq" name="q" class="form-control" value="{{ request('q') }}"></div>
    </x-admin.filters>
    @if($events->isEmpty())
        <x-admin.empty icon="fa-satellite-dish" title="No events yet" text="Events appear when a provider calls the URLs above." />
    @else
    <x-admin.table stack compact caption="Webhook events">
        <thead><tr><th>Received</th><th>Provider</th><th>Account</th><th>Event</th><th>Transaction</th><th>Signature</th><th>Processing</th><th><span class="a-sr-only">Actions</span></th></tr></thead>
        <tbody>
        @foreach($events as $e)
            <tr>
                <td data-label="Received">{{ $e->received_at->format('d M, H:i:s') }}</td>
                <td data-label="Provider">{{ ucfirst($e->provider) }}</td>
                <td data-label="Account">{{ $e->account?->name ?? '—' }}</td>
                <td data-label="Event">{{ $e->event_type }}@if($e->deliveries > 1)<span class="a-table__sub">{{ $e->deliveries }} deliveries</span>@endif</td>
                <td data-label="Transaction"><span class="a-table__sub" style="color:var(--a-ink)">{{ $e->transaction_reference ?: '—' }}</span>@if($e->attempt)<a class="a-table__sub" href="{{ route('admin.payments.transactions.show', $e->attempt) }}">{{ $e->attempt->internal_reference ?: '#'.$e->attempt->id }}</a>@endif</td>
                <td data-label="Signature"><x-admin.badge :variant="$e->signatureVariant()">{{ $e->signatureLabel() }}</x-admin.badge></td>
                <td data-label="Processing">{{ str_replace('_', ' ', $e->processing_status) }}@if($e->error)<span class="a-table__sub">{{ $e->error }}</span>@endif</td>
                <td data-label="Actions">@if($canRetry && $e->signature_status !== 'invalid' && in_array($e->processing_status, ['failed', 'needs_review', 'received'], true))<form method="POST" action="{{ route('admin.payments.webhooks.retry', $e) }}">@csrf<button class="a-btn a-btn--outline a-btn--sm" type="submit">Retry</button></form>@endif</td>
            </tr>
        @endforeach
        </tbody>
    </x-admin.table>
    <x-admin.pager :paginator="$events" />
    @endif
</x-admin.card>
@endsection
@push('scripts')<script>document.querySelectorAll('[data-copy]').forEach(function (b) { b.addEventListener('click', function () { var i = document.querySelector(b.dataset.copy); navigator.clipboard.writeText(i.value).then(function () { b.textContent = 'Copied'; setTimeout(function () { b.textContent = 'Copy'; }, 1200); }); }); });</script>@endpush
