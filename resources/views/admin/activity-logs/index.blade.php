@extends('admin.master')
@section('content')
<div class="content"><h2 class="h4 mb-3">Activity Logs</h2><div class="block block-rounded"><div class="table-responsive"><table class="table table-striped mb-0"><thead><tr><th>Date</th><th>Actor</th><th>Log</th><th>Description</th><th>Subject</th></tr></thead><tbody>@foreach($activities as $activity)<tr><td>{{ $activity->created_at->format('Y-m-d H:i:s') }}</td><td>{{ $activity->causer?->name ?? 'System' }}</td><td>{{ $activity->log_name }}</td><td>{{ $activity->description }}</td><td>{{ class_basename($activity->subject_type) }} #{{ $activity->subject_id }}</td></tr>@endforeach</tbody></table></div></div>{{ $activities->links() }}</div>
@endsection
