@extends('admin.master')

@section('content')
<div class="content py-4">
  <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
    <div>
      <h2 class="h4 mb-1">Scanners</h2>
      <p class="text-muted mb-0">Manage scanner users for the gate team.</p>
    </div>
    <div class="d-flex gap-2">
      <a href="{{ route('admin.scanners.export-history') }}" class="btn btn-alt-primary">
        <i class="fa fa-file-csv me-1"></i> Export Check-in History
      </a>
      <a href="{{ route('admin.scanners.create') }}" class="btn btn-primary">
        <i class="fa fa-plus me-1"></i> Add Scanner
      </a>
    </div>
  </div>

  @include('admin.partials.flash')

  <form class="mb-3" method="GET" action="{{ route('admin.scanners.index') }}">
    <input class="form-control" type="text" name="search" value="{{ $search }}" placeholder="Search scanner users...">
  </form>

  <div class="block block-rounded">
    <div class="table-responsive">
      <table class="table table-hover table-vcenter mb-0">
        <thead>
          <tr>
            <th>Name</th>
            <th>Username</th>
            <th>Email</th>
            <th>Scan Count</th>
            <th style="width:200px">Actions</th>
          </tr>
        </thead>
        <tbody>
          @forelse($scannerUsers as $scanner)
            <tr>
              <td>{{ $scanner->name }}</td>
              <td>{{ $scanner->username }}</td>
              <td>{{ $scanner->email }}</td>
              <td>
                <span class="badge bg-info">{{ number_format($scanner->scans_count ?? 0) }}</span>
              </td>
              <td>
                <a href="{{ route('admin.scanners.show', $scanner) }}" class="btn btn-sm btn-alt-info">Show</a>
                <form method="POST" action="{{ route('admin.scanners.destroy', $scanner) }}" class="d-inline" onsubmit="return confirm('Delete this scanner user?');">
                  @csrf
                  @method('DELETE')
                  <button class="btn btn-sm btn-alt-danger" type="submit">Delete</button>
                </form>
              </td>
            </tr>
          @empty
            <tr><td colspan="5" class="text-center text-muted">No scanner users found.</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>

  <div class="mt-3">{{ $scannerUsers->links() }}</div>
</div>
@endsection
