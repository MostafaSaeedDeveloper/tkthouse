@extends('admin.master')
@section('title', 'Scanner Profile')
@section('content')
<x-admin.page-header title="Scanner Profile" :subtitle="$user->name.' ('.$user->username.')'" :crumbs="['Dashboard' => route('admin.dashboard'), 'Scanners' => route('admin.scanners.index'), $user->username]">
    <a href="{{ route('admin.scanners.index') }}" class="a-btn a-btn--outline">Back</a>
</x-admin.page-header>
<x-admin.flash />

<div class="a-m-profile">
    <x-admin.card title="Last Login">
        <dl class="a-kv">
            <div><dt>Last Login</dt><dd>{{ $user->last_login_at?->format('d M Y, h:i A') ?? '-' }}</dd></div>
            <div><dt>IP</dt><dd>{{ $user->last_login_ip ?? '-' }}</dd></div>
        </dl>
    </x-admin.card>
    <div class="a-grid a-grid--3">
        <x-admin.kpi label="Total Scans" :value="number_format($stats['total_scans'])" icon="fa-qrcode" />
        <x-admin.kpi label="Check-ins" :value="number_format($stats['checkins'])" icon="fa-circle-check" />
        <x-admin.kpi label="Login Count" :value="number_format($stats['logins'])" icon="fa-right-to-bracket" />
    </div>
</div>

<x-admin.card title="Scanner Logs" flush>
    @if($scanLogs->isEmpty())
        <x-admin.empty icon="fa-list" title="No logs found for this scanner." />
    @else
        <x-admin.table stack caption="Scanner logs">
            <thead><tr><th>Time</th><th>Action</th><th>Ticket</th><th>Event</th></tr></thead>
            <tbody>
            @foreach($scanLogs as $log)
                @php $change = ($log->previous_status || $log->new_status) ? ((str($log->previous_status ?? '-')->replace('_',' ')->title()) . ' → ' . (str($log->new_status ?? '-')->replace('_',' ')->title())) : null; @endphp
                <tr>
                    <td data-label="Time">{{ $log->scanned_at?->format('d M Y, h:i A') ?? '-' }}@if($log->ip_address)<span class="a-table__sub">{{ $log->ip_address }}</span>@endif</td>
                    <td data-label="Action"><x-admin.badge variant="dark">{{ str($log->action)->replace('_',' ')->title() }}</x-admin.badge>@if($change)<span class="a-table__sub">{{ $change }}</span>@endif</td>
                    <td data-label="Ticket" class="a-num">{{ $log->ticket_number ?? '-' }}</td>
                    <td data-label="Event"><span class="a-trunc a-m-trunc-lg" title="{{ $log->event_name }}">{{ $log->event_name ?? '-' }}</span></td>
                </tr>
            @endforeach
            </tbody>
        </x-admin.table>
        <x-admin.pager :paginator="$scanLogs" />
    @endif
</x-admin.card>
@endsection
