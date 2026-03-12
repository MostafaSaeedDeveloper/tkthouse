@extends('admin.master')

@section('content')
@php
    $rows = old('guests', session('imported_guests', [['name' => '', 'email' => '', 'phone' => '']]));
    $selectedEventId = (int) old('event_id', session('import_event_id', 0));
    $selectedGuestType = old('guest_type', session('import_guest_type', ''));
@endphp
<div class="content">
    @include('admin.partials.flash')
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h3 mb-0">Create Guest List Invitations</h1>
        <a href="{{ route('admin.guest-lists.index') }}" class="btn btn-alt-secondary">Back</a>
    </div>

    <div class="block block-rounded mb-3">
        <div class="block-content py-3">
            <form method="POST" action="{{ route('admin.guest-lists.import') }}" enctype="multipart/form-data" class="row g-2 align-items-end">
                @csrf
                <div class="col-md-3">
                    <label class="form-label">Event</label>
                    <select name="event_id" class="form-select" id="import-event-id" required>
                        <option value="">Select event</option>
                        @foreach($events as $event)
                            <option value="{{ $event->id }}" @selected($selectedEventId === $event->id)>{{ $event->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Guest Type</label>
                    <select name="guest_type" class="form-select" id="import-guest-type" required>
                        <option value="">Select type</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Import Excel/CSV</label>
                    <input type="file" class="form-control" name="file" accept=".csv,.txt,.xlsx" required>
                </div>
                <div class="col-md-3 d-flex gap-2">
                    <button class="btn btn-alt-primary" type="submit">Import</button>
                    <button class="btn btn-alt-info" type="submit" formaction="{{ route('admin.guest-lists.export') }}" formmethod="GET">Export Event List</button>
                </div>
            </form>
        </div>
    </div>

    <form method="POST" action="{{ route('admin.guest-lists.store') }}" id="guest-list-form">
        @csrf
        <input type="hidden" name="event_id" id="event-id" value="{{ $selectedEventId ?: '' }}">
        <input type="hidden" name="guest_type" id="guest-type" value="{{ $selectedGuestType }}">

        <div class="block block-rounded mb-3">
            <div class="block-content py-3 d-flex justify-content-between align-items-center flex-wrap gap-2">
                <div>
                    <div class="text-muted small">Invitation Setup</div>
                    <div id="setup-summary" class="fw-semibold">Choose event and type</div>
                </div>
                <button type="button" class="btn btn-alt-primary" id="open-setup-modal">Choose Event / Type / Count</button>
            </div>
        </div>

        <div class="block block-rounded">
            <div class="block-content">
                <div class="table-responsive">
                    <table class="table table-bordered" id="guest-table">
                        <thead>
                        <tr>
                            <th>Name *</th>
                            <th>Email</th>
                            <th>Phone</th>
                            <th style="width:60px"></th>
                        </tr>
                        </thead>
                        <tbody>
                        @foreach($rows as $index => $row)
                            <tr>
                                <td><input class="form-control" name="guests[{{ $index }}][name]" value="{{ $row['name'] ?? '' }}" required></td>
                                <td><input class="form-control" name="guests[{{ $index }}][email]" value="{{ $row['email'] ?? '' }}"></td>
                                <td><input class="form-control" name="guests[{{ $index }}][phone]" value="{{ $row['phone'] ?? '' }}"></td>
                                <td><button type="button" class="btn btn-sm btn-alt-danger remove-row">×</button></td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="d-flex gap-2 mt-3">
                    <button type="button" class="btn btn-alt-primary" id="add-row">Add Guest</button>
                    <button class="btn btn-primary" type="submit">Create Invitations</button>
                </div>
            </div>
        </div>
    </form>
</div>

<div class="modal fade" id="invitationSetupModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <form class="modal-content" id="setup-form">
            <div class="modal-header">
                <h5 class="modal-title">Setup Invitations</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label">Event</label>
                    <select class="form-select" id="setup-event" required>
                        <option value="">Select event</option>
                        @foreach($events as $event)
                            <option value="{{ $event->id }}" @selected($selectedEventId === $event->id)>{{ $event->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label">Guest Type (same as event ticket type)</label>
                    <select class="form-select" id="setup-type" required>
                        <option value="">Select type</option>
                    </select>
                </div>
                <div>
                    <label class="form-label">Initial Number of Guests</label>
                    <input type="number" class="form-control" id="setup-count" min="1" max="500" value="{{ max(1, count($rows)) }}" required>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-alt-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary">Apply</button>
            </div>
        </form>
    </div>
</div>
@endsection

@push('scripts')
<script>
(() => {
    const events = @json($events->map(fn($event) => [
        'id' => $event->id,
        'name' => $event->name,
        'types' => $event->tickets->pluck('name')->values(),
    ])->values());

    const tableBody = document.querySelector('#guest-table tbody');
    const addRowBtn = document.getElementById('add-row');
    const setupSummary = document.getElementById('setup-summary');
    const hiddenEventId = document.getElementById('event-id');
    const hiddenGuestType = document.getElementById('guest-type');

    const setupModalEl = document.getElementById('invitationSetupModal');
    const setupModal = window.bootstrap ? new bootstrap.Modal(setupModalEl) : null;
    const openSetupBtn = document.getElementById('open-setup-modal');
    const setupForm = document.getElementById('setup-form');
    const setupEvent = document.getElementById('setup-event');
    const setupType = document.getElementById('setup-type');
    const setupCount = document.getElementById('setup-count');

    const importEvent = document.getElementById('import-event-id');
    const importType = document.getElementById('import-guest-type');

    const normalizeGuestType = (typeName) => {
        const clean = String(typeName || '').replace(/^Guest\s+/i, '').trim();
        return clean ? `Guest ${clean}` : '';
    };

    const eventById = (id) => events.find((event) => String(event.id) === String(id));

    const fillTypeOptions = (targetSelect, eventId, selectedValue = '') => {
        const event = eventById(eventId);
        const types = event?.types?.length ? event.types : ['Regular'];
        const normalizedSelected = normalizeGuestType(selectedValue);

        targetSelect.innerHTML = '<option value="">Select type</option>';
        types.forEach((type) => {
            const option = document.createElement('option');
            option.value = type;
            option.textContent = normalizeGuestType(type);
            if (normalizeGuestType(type) === normalizedSelected) {
                option.selected = true;
            }
            targetSelect.appendChild(option);
        });
    };

    const reindex = () => {
        [...tableBody.querySelectorAll('tr')].forEach((row, idx) => {
            row.querySelectorAll('input').forEach((input) => {
                input.name = input.name.replace(/guests\[\d+\]/, `guests[${idx}]`);
            });
        });
    };

    const appendRow = () => {
        const row = document.createElement('tr');
        row.innerHTML = `
            <td><input class="form-control" name="guests[0][name]" required></td>
            <td><input class="form-control" name="guests[0][email]"></td>
            <td><input class="form-control" name="guests[0][phone]"></td>
            <td><button type="button" class="btn btn-sm btn-alt-danger remove-row">×</button></td>
        `;
        tableBody.appendChild(row);
        reindex();
    };

    const updateSummary = () => {
        const event = eventById(hiddenEventId.value);
        const count = tableBody.querySelectorAll('tr').length;
        if (!event || !hiddenGuestType.value) {
            setupSummary.textContent = 'Choose event and type';
            return;
        }
        setupSummary.textContent = `${event.name} — ${hiddenGuestType.value} (${count} guests)`;
    };

    addRowBtn?.addEventListener('click', appendRow);

    tableBody?.addEventListener('click', (e) => {
        if (!e.target.classList.contains('remove-row')) return;
        e.target.closest('tr')?.remove();
        reindex();
        updateSummary();
    });

    setupEvent?.addEventListener('change', () => {
        fillTypeOptions(setupType, setupEvent.value);
    });

    importEvent?.addEventListener('change', () => {
        fillTypeOptions(importType, importEvent.value);
    });

    setupForm?.addEventListener('submit', (e) => {
        e.preventDefault();
        if (!setupEvent.value || !setupType.value || !setupCount.value) return;

        hiddenEventId.value = setupEvent.value;
        hiddenGuestType.value = normalizeGuestType(setupType.value);

        importEvent.value = setupEvent.value;
        fillTypeOptions(importType, setupEvent.value, hiddenGuestType.value);

        tableBody.innerHTML = '';
        const total = Math.max(1, parseInt(setupCount.value, 10) || 1);
        for (let i = 0; i < total; i++) appendRow();

        updateSummary();
        setupModal?.hide();
    });

    document.getElementById('guest-list-form')?.addEventListener('submit', (e) => {
        if (!hiddenEventId.value || !hiddenGuestType.value) {
            e.preventDefault();
            alert('Please choose event and guest type first.');
            setupModal?.show();
        }
    });

    openSetupBtn?.addEventListener('click', () => setupModal?.show());

    fillTypeOptions(setupType, setupEvent.value || '{{ $selectedEventId ?: '' }}', '{{ $selectedGuestType }}');
    fillTypeOptions(importType, importEvent.value || '{{ $selectedEventId ?: '' }}', '{{ $selectedGuestType }}');

    if (hiddenEventId.value && hiddenGuestType.value) {
        updateSummary();
    } else {
        setupModal?.show();
    }
})();
</script>
@endpush
