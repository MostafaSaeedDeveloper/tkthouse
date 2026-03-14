@extends('admin.master')

@section('content')
<div class="content py-4">
  <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
    <div>
      <h2 class="h4 mb-1">Scanner Profile</h2>
      <p class="text-muted mb-0">{{ $user->name }} ({{ $user->username }})</p>
    </div>
    <a href="{{ route('admin.scanners.index') }}" class="btn btn-alt-secondary">Back</a>
  </div>

  <div class="row g-3 mb-4">
    <div class="col-md-4">
      <div class="block block-rounded h-100">
        <div class="block-content block-content-full">
          <div class="fs-sm text-muted">Last Login</div>
          <div class="fw-semibold">{{ $user->last_login_at?->format('d M Y, h:i A') ?? '-' }}</div>
          <div class="fs-sm text-muted mt-1">IP: {{ $user->last_login_ip ?? '-' }}</div>
        </div>
      </div>
    </div>
    <div class="col-md-8">
      <div class="row g-3">
        <div class="col-md-4"><div class="block block-rounded"><div class="block-content block-content-full"><div class="fs-sm text-muted">Total Scans</div><div class="fs-4 fw-bold">{{ number_format($stats['total_scans']) }}</div></div></div></div>
        <div class="col-md-4"><div class="block block-rounded"><div class="block-content block-content-full"><div class="fs-sm text-muted">Check-ins</div><div class="fs-4 fw-bold">{{ number_format($stats['checkins']) }}</div></div></div></div>
        <div class="col-md-4"><div class="block block-rounded"><div class="block-content block-content-full"><div class="fs-sm text-muted">Login Count</div><div class="fs-4 fw-bold">{{ number_format($stats['logins']) }}</div></div></div></div>
      </div>
    </div>
  </div>

  <div class="block block-rounded">
    <div class="block-header block-header-default">
      <h3 class="block-title">Scanner Logs</h3>
    </div>
    <div class="block-content block-content-full p-0">
      <div class="table-responsive">
        <table class="table table-hover table-vcenter mb-0">
          <thead>
            <tr>
              <th>Time</th>
              <th>Action</th>
              <th>Ticket #</th>
              <th>Event</th>
              <th>Status Change</th>
              <th>IP</th>
            </tr>
          </thead>
          <tbody>
            @forelse($scanLogs as $log)
              <tr>
                <td>{{ $log->scanned_at?->format('d M Y, h:i A') ?? '-' }}</td>
                <td><span class="badge bg-primary">{{ str($log->action)->replace('_',' ')->title() }}</span></td>
                <td>{{ $log->ticket_number ?? '-' }}</td>
                <td>{{ $log->event_name ?? '-' }}</td>
                <td>{{ ($log->previous_status || $log->new_status) ? ((str($log->previous_status ?? '-')->replace('_',' ')->title()) . ' → ' . (str($log->new_status ?? '-')->replace('_',' ')->title())) : '-' }}</td>
                <td>{{ $log->ip_address ?? '-' }}</td>
              </tr>
            @empty
              <tr>
                <td colspan="6" class="text-center text-muted py-4">No logs found for this scanner.</td>
              </tr>
            @endforelse
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <div class="mt-3">{{ $scanLogs->links() }}</div>
</div>
@endsection
