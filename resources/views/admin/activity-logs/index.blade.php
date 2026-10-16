@extends('admin.master')
@section('title', 'Activity Logs')
@section('content')
<x-admin.page-header title="Activity Logs" subtitle="Who changed what, newest first." :crumbs="['Dashboard' => route('admin.dashboard'), 'Activity Logs']" />
<x-admin.flash />
<x-admin.card flush title="Recent activity" :subtitle="$activities->total().' entries'">
    @if($activities->isEmpty())
        <x-admin.empty icon="fa-clock-rotate-left" title="No activity yet" text="Actions taken by admins appear here." />
    @else
        <x-admin.table stack caption="Activity logs">
            <thead><tr><th>When</th><th>Actor</th><th>Activity</th><th>Subject</th></tr></thead>
            <tbody>
            @foreach($activities as $activity)
                <tr>
                    <td data-label="When">{{ $activity->created_at->format('d M Y') }}<span class="a-table__sub">{{ $activity->created_at->format('H:i:s') }}</span></td>
                    <td data-label="Actor"><span class="a-table__main a-trunc">{{ $activity->causer?->name ?? 'System' }}</span></td>
                    <td data-label="Activity"><span class="a-trunc a-m-trunc-xl" title="{{ $activity->description }}">{{ $activity->description }}</span>@if($activity->log_name)<span class="a-table__sub"><x-admin.badge>{{ $activity->log_name }}</x-admin.badge></span>@endif</td>
                    <td data-label="Subject">{{ class_basename($activity->subject_type) }}<span class="a-table__sub a-num">#{{ $activity->subject_id }}</span></td>
                </tr>
            @endforeach
            </tbody>
        </x-admin.table>
        <x-admin.pager :paginator="$activities" />
    @endif
</x-admin.card>
@endsection
