@extends('admin.master')

@section('content')
<div class="content">
    @include('admin.partials.flash')

    <div class="d-flex justify-content-between mb-3">
        <h1 class="h3 mb-0">Guest List</h1>
        <div class="d-flex gap-2">
            <a href="{{ route('admin.guest-list.template') }}" class="btn btn-alt-info">Import Template</a>
            <a href="{{ route('admin.guest-list.export') }}" class="btn btn-alt-secondary">Export</a>
            <button class="btn btn-alt-primary" data-bs-toggle="modal" data-bs-target="#importGuestListModal">Import</button>
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createInvitationModal">Create Invitation</button>
        </div>
    </div>

    <div class="block block-rounded mb-3">
        <div class="block-content py-3">
            <form method="GET" class="row g-2 align-items-end">
                <div class="col-md-4">
                    <label class="form-label">Search</label>
                    <input type="text" name="search" value="{{ request('search') }}" class="form-control" placeholder="Ticket # / guest">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Event Name</label>
                    <select name="event_name" class="form-select js-select2">
                        <option value="">All events</option>
                        @foreach($eventNames as $eventName)
                            <option value="{{ $eventName }}" @selected(request('event_name') === $eventName)>{{ $eventName }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Guest Type</label>
                    <select name="guest_type" class="form-select js-select2">
                        <option value="">All types</option>
                        @foreach($guestTypes as $guestType)
                            <option value="{{ $guestType }}" @selected(request('guest_type') === $guestType)>{{ $guestType }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2 d-flex gap-2">
                    <button class="btn btn-primary" type="submit">Filter</button>
                    <a class="btn btn-alt-secondary" href="{{ route('admin.guest-list.index') }}">Reset</a>
                </div>
            </form>
        </div>
    </div>

    <div class="block block-rounded">
        <div class="block-content p-0">
            <div class="table-responsive">
                <table class="table table-hover table-vcenter mb-0">
                    <thead>
                        <tr>
                            <th>Ticket Number</th>
                            <th>Name</th>
                            <th>Event</th>
                            <th>Guest Type</th>
                            <th>Status</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($tickets as $ticket)
                            <tr>
                                <td><a href="{{ route('admin.tickets.show', $ticket) }}">{{ $ticket->ticket_number }}</a></td>
                                <td>{{ $ticket->holder_name }}</td>
                                <td>{{ $ticket->eventLabel() }}</td>
                                <td>{{ $ticket->guest_type }}</td>
                                <td>{{ str($ticket->status)->headline() }}</td>
                                <td class="text-end">
                                    <a class="btn btn-sm btn-alt-info" href="{{ route('admin.tickets.show', $ticket) }}"><i class="fa fa-eye"></i></a>
                                    @can('tickets.delete')
                                        <form action="{{ route('admin.tickets.destroy', $ticket) }}" method="POST" class="d-inline">
                                            @csrf
                                            @method('DELETE')
                                            <input type="hidden" name="redirect_to" value="guest-list">
                                            <button class="btn btn-sm btn-alt-danger" type="submit"><i class="fa fa-trash"></i></button>
                                        </form>
                                    @endcan
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="text-center py-4 text-muted">No guest tickets found.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <div class="mt-3">{{ $tickets->links() }}</div>
</div>

<div class="modal fade" id="createInvitationModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <form method="GET" action="{{ route('admin.guest-list.create') }}">
        <div class="modal-header">
          <h5 class="modal-title">Create Invitation</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">Event</label>
                    <select name="event_name" class="form-select js-modal-select2" required>
                        <option value="">Select event</option>
                        @foreach($eventNames as $eventName)
                            <option value="{{ $eventName }}">{{ $eventName }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Guest Type</label>
                    <input type="text" class="form-control" name="guest_type" placeholder="Regular" required>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Count</label>
                    <input type="number" name="count" class="form-control" value="1" min="1" max="500">
                </div>
            </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-alt-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary">Generate</button>
        </div>
      </form>
    </div>
  </div>
</div>

<div class="modal fade" id="importGuestListModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <form method="POST" action="{{ route('admin.guest-list.import') }}" enctype="multipart/form-data">
        @csrf
        <div class="modal-header">
          <h5 class="modal-title">Import Guest List</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">Event</label>
                    <select name="event_name" class="form-select js-modal-select2" required>
                        <option value="">Select event</option>
                        @foreach($eventNames as $eventName)
                            <option value="{{ $eventName }}">{{ $eventName }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label">CSV File</label>
                    <input type="file" name="file" class="form-control" accept=".csv,text/csv" required>
                </div>
            </div>
            <div class="mt-3 text-muted small">
                Template now contains only ticket data (guest_type, name, email, phone, gender).
            </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-alt-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary">Import</button>
        </div>
      </form>
    </div>
  </div>
</div>
@endsection

@push('scripts')
<script>
(() => {
  if (typeof window.jQuery === 'undefined' || typeof jQuery.fn.select2 === 'undefined') return;

  function initModalSelect2(modalId) {
    const $modal = jQuery(modalId);
    if (! $modal.length) return;

    $modal.on('shown.bs.modal', function () {
      $modal.find('select.js-modal-select2').each(function () {
        const $el = jQuery(this);
        if ($el.data('select2')) return;
        $el.select2({
          width: '100%',
          dropdownParent: $modal.find('.modal-content'),
        });
      });
    });

    $modal.on('hidden.bs.modal', function () {
      $modal.find('select.js-modal-select2').each(function () {
        const $el = jQuery(this);
        if ($el.data('select2')) {
          $el.select2('destroy');
        }
      });
    });
  }

  initModalSelect2('#createInvitationModal');
  initModalSelect2('#importGuestListModal');
})();
</script>
@endpush

