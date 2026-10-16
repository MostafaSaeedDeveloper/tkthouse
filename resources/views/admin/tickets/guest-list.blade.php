@extends('admin.master')
@section('title', 'Guest List')
@push('styles')<link rel="stylesheet" href="{{ asset('admin-v2/events-tickets.css') }}?v={{ filemtime(public_path('admin-v2/events-tickets.css')) }}">@endpush

@section('content')
<x-admin.page-header title="Guest List" subtitle="Invitation tickets issued outside of paid orders." :crumbs="['Dashboard' => route('admin.dashboard'), 'Tickets' => route('admin.tickets.index'), 'Guest List']">
    <a href="{{ route('admin.guest-list.template') }}" class="a-btn a-btn--outline"><i class="fa fa-file-arrow-down"></i> Import Template</a>
    <a href="{{ route('admin.guest-list.export') }}" class="a-btn a-btn--outline"><i class="fa fa-file-export"></i> Export</a>
    <button type="button" class="a-btn a-btn--outline" data-bs-toggle="modal" data-bs-target="#importGuestListModal"><i class="fa fa-file-import"></i> Import</button>
    <button type="button" class="a-btn a-btn--primary" data-bs-toggle="modal" data-bs-target="#createInvitationModal"><i class="fa fa-plus"></i> Create Invitation</button>
</x-admin.page-header>

<x-admin.flash />

<x-admin.filters :action="route('admin.guest-list.index')" :reset="route('admin.guest-list.index')">
    <div class="a-field"><label class="form-label" for="glSearch">Search</label><input type="text" id="glSearch" name="search" value="{{ request('search') }}" class="form-control" placeholder="Ticket # / guest"></div>
    <div class="a-field"><label class="form-label" for="glEvent">Event Name</label>
        <select id="glEvent" name="event_name" class="form-select js-select2">
            <option value="">All events</option>
            @foreach($eventNames as $eventName)
                <option value="{{ $eventName }}" @selected(request('event_name') === $eventName)>{{ $eventName }}</option>
            @endforeach
        </select></div>
    <div class="a-field"><label class="form-label" for="glType">Guest Type</label>
        <select id="glType" name="guest_type" class="form-select js-select2">
            <option value="">All types</option>
            @foreach($guestTypes as $guestType)
                <option value="{{ $guestType }}" @selected(request('guest_type') === $guestType)>{{ $guestType }}</option>
            @endforeach
        </select></div>
</x-admin.filters>

<x-admin.card flush>
    @if($tickets->isEmpty())
        <x-admin.empty icon="fa-list-check" title="No guest tickets found" text="Create an invitation or import a CSV to add guests.">
            <button type="button" class="a-btn a-btn--primary" data-bs-toggle="modal" data-bs-target="#createInvitationModal"><i class="fa fa-plus"></i> Create Invitation</button>
        </x-admin.empty>
    @else
        <x-admin.table stack caption="Guest tickets">
            <thead>
                <tr><th>Ticket Number</th><th>Name</th><th>Event</th><th>Guest Type</th><th>Status</th><th class="a-num">Actions</th></tr>
            </thead>
            <tbody>
            @foreach($tickets as $ticket)
                <tr>
                    <td data-label="Ticket Number"><a class="a-table__main" href="{{ route('admin.tickets.show', $ticket) }}">{{ $ticket->ticket_number }}</a></td>
                    <td data-label="Name" class="a-wrap">{{ $ticket->holder_name }}</td>
                    <td data-label="Event" class="a-wrap">{{ $ticket->eventLabel() }}</td>
                    <td data-label="Guest Type" class="a-wrap">{{ $ticket->guest_type }}</td>
                    <td data-label="Status"><x-admin.status :status="$ticket->status" kind="ticket" /></td>
                    <td data-label="Actions">
                        <div class="a-table__actions">
                            <a class="a-btn a-btn--outline a-btn--sm" href="{{ route('admin.tickets.show', $ticket) }}" aria-label="View ticket {{ $ticket->ticket_number }}" title="View"><i class="fa fa-eye"></i></a>
                            @can('tickets.delete')
                                <form action="{{ route('admin.tickets.destroy', $ticket) }}" method="POST" data-a-confirm="Delete guest ticket {{ $ticket->ticket_number }}? This cannot be undone." data-a-confirm-title="Delete ticket" data-a-confirm-ok="Delete">
                                    @csrf
                                    @method('DELETE')
                                    <input type="hidden" name="redirect_to" value="guest-list">
                                    <button class="a-btn a-btn--danger a-btn--sm" type="submit" aria-label="Delete ticket {{ $ticket->ticket_number }}" title="Delete"><i class="fa fa-trash"></i></button>
                                </form>
                            @endcan
                        </div>
                    </td>
                </tr>
            @endforeach
            </tbody>
        </x-admin.table>
        <x-admin.pager :paginator="$tickets" />
    @endif
</x-admin.card>

<div class="modal fade" id="createInvitationModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <form method="GET" action="{{ route('admin.guest-list.create') }}">
        <div class="modal-header">
          <h5 class="modal-title">Create Invitation</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
            <div class="a-tk-modal-fields">
                <div class="a-field a-span-3">
                    <label class="form-label" for="invEvent">Event</label>
                    <select id="invEvent" name="event_name" class="form-select js-modal-select2" required>
                        <option value="">Select event</option>
                        @foreach($eventNames as $eventName)
                            <option value="{{ $eventName }}">{{ $eventName }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="a-field a-span-2">
                    <label class="form-label" for="invGuestType">Guest Type</label>
                    <input type="text" id="invGuestType" class="form-control" name="guest_type" placeholder="Regular" required>
                </div>
                <div class="a-field">
                    <label class="form-label" for="invCount">Count</label>
                    <input type="number" id="invCount" name="count" class="form-control" value="1" min="1" max="500">
                </div>
            </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="a-btn a-btn--ghost" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="a-btn a-btn--primary">Generate</button>
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
            <div class="a-tk-modal-fields">
                <div class="a-field a-span-3">
                    <label class="form-label" for="impEvent">Event</label>
                    <select id="impEvent" name="event_name" class="form-select js-modal-select2" required>
                        <option value="">Select event</option>
                        @foreach($eventNames as $eventName)
                            <option value="{{ $eventName }}">{{ $eventName }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="a-field a-span-3">
                    <label class="form-label" for="importFile">CSV File</label>
                    <input type="file" id="importFile" name="file" class="form-control" accept=".csv,text/csv" required>
                </div>
            </div>
            <div class="a-hint" style="margin-top:14px">
                Template now contains only ticket data (guest_type, name, email, phone, gender).
            </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="a-btn a-btn--ghost" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="a-btn a-btn--primary">Import</button>
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

