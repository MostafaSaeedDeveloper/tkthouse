@extends('admin.master')

@section('content')
<div class="content">
    @include('admin.partials.flash')

    <div class="d-flex justify-content-between mb-3">
        <h1 class="h3 mb-0">Guest List</h1>
        <div class="d-flex gap-2">
            <a href="{{ route('admin.guest-list.template') }}" class="btn btn-alt-info">Import Template</a>
            <a href="{{ route('admin.guest-list.export') }}" class="btn btn-alt-secondary">Export</a>
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

    <div class="block block-rounded mb-3">
        <div class="block-header block-header-default"><h3 class="block-title">Import Guest List</h3></div>
        <div class="block-content pb-3">
            <form method="POST" action="{{ route('admin.guest-list.import') }}" enctype="multipart/form-data" class="row g-2 align-items-end">
                @csrf
                <div class="col-md-8">
                    <label class="form-label">CSV File</label>
                    <input type="file" name="file" class="form-control" accept=".csv,text/csv">
                </div>
                <div class="col-md-4">
                    <button class="btn btn-primary" type="submit">Import</button>
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
                        @foreach($tickets as $ticket)
                            <tr>
                                <td>{{ $ticket->ticket_number }}</td>
                                <td>{{ $ticket->holder_name }}</td>
                                <td>{{ $ticket->eventLabel() }}</td>
                                <td>{{ $ticket->guest_type }}</td>
                                <td>{{ str($ticket->status)->headline() }}</td>
                                <td class="text-end">
                                    <a class="btn btn-sm btn-alt-info" href="{{ route('admin.tickets.show', $ticket) }}"><i class="fa fa-eye"></i></a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <div class="mt-3">{{ $tickets->links() }}</div>
</div>

<div class="modal fade" id="createInvitationModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-scrollable">
    <div class="modal-content">
      <form method="POST" action="{{ route('admin.guest-list.store') }}" id="invitationForm">
        @csrf
        <div class="modal-header">
          <h5 class="modal-title">Create Invitation</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
            <div class="row g-3 mb-3">
                <div class="col-md-5">
                    <label class="form-label">Event</label>
                    <select name="event_name" class="form-select" required>
                        <option value="">Select event</option>
                        @foreach($eventNames as $eventName)
                            <option value="{{ $eventName }}">{{ $eventName }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Guest Type</label>
                    <input type="text" class="form-control" name="guest_type" placeholder="Regular" required>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Count</label>
                    <input type="number" id="guestCount" class="form-control" value="1" min="1" max="200">
                </div>
                <div class="col-md-2 d-flex align-items-end">
                    <button type="button" class="btn btn-alt-primary w-100" id="buildRows">Generate</button>
                </div>
            </div>

            <div class="table-responsive">
                <table class="table table-bordered" id="guestRowsTable">
                    <thead>
                        <tr>
                            <th>Name *</th>
                            <th>Email</th>
                            <th>Phone</th>
                            <th>Guest Type</th>
                        </tr>
                    </thead>
                    <tbody></tbody>
                </table>
            </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-alt-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary">Create Tickets</button>
        </div>
      </form>
    </div>
  </div>
</div>
@endsection

@push('scripts')
<script>
(function () {
    const buildButton = document.getElementById('buildRows');
    const countInput = document.getElementById('guestCount');
    const tbody = document.querySelector('#guestRowsTable tbody');

    function buildRows() {
        const count = Math.max(1, Math.min(200, Number(countInput.value || 1)));
        tbody.innerHTML = '';

        for (let i = 0; i < count; i++) {
            const row = document.createElement('tr');
            row.innerHTML = `
                <td><input type="text" name="guests[${i}][name]" class="form-control" required></td>
                <td><input type="email" name="guests[${i}][email]" class="form-control"></td>
                <td><input type="text" name="guests[${i}][phone]" class="form-control"></td>
                <td><input type="text" class="form-control" value="From header" disabled></td>
            `;
            tbody.appendChild(row);
        }
    }

    if (buildButton) {
        buildButton.addEventListener('click', buildRows);
        buildRows();
    }
})();
</script>
@endpush
