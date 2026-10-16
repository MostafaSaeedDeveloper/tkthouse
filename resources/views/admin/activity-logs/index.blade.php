@extends('admin.master')
@section('title', 'Activity Logs')
@section('content')
<x-admin.page-header title="Activity Logs" subtitle="Who changed what, newest first." :crumbs="['Dashboard' => route('admin.dashboard'), 'Activity Logs']" />
<x-admin.flash />
<x-admin.card flush>
    @if($activities->isEmpty())
        <x-admin.empty icon="fa-clock-rotate-left" title="No activity yet" text="Actions taken by admins appear here." />
    @else
        <x-admin.table stack caption="Activity logs">
            <thead><tr><th>Date</th><th>Actor</th><th>Log</th><th>Description</th><th>Subject</th></tr></thead>
            <tbody>
            @foreach($activities as $activity)
                <tr>
                    <td data-label="Date">{{ $activity->created_at->format('Y-m-d H:i:s') }}</td>
                    <td data-label="Actor">{{ $activity->causer?->name ?? 'System' }}</td>
                    <td data-label="Log"><x-admin.badge>{{ $activity->log_name }}</x-admin.badge></td>
                    <td data-label="Description" class="a-wrap">{{ $activity->description }}</td>
                    <td data-label="Subject">{{ class_basename($activity->subject_type) }} #{{ $activity->subject_id }}</td>
                </tr>
            @endforeach
            </tbody>
        </x-admin.table>
        <x-admin.pager :paginator="$activities" />
    @endif
</x-admin.card>
@endsection
