@extends('admin.master')

@section('content')
<div class="content">
    @include('admin.partials.flash')

    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h1 class="h3 mb-0">Create Guest Tickets</h1>
            <small class="text-muted">Event: {{ $selectedEventName }} | Type: {{ $selectedGuestType }}</small>
        </div>
        <a href="{{ route('admin.guest-list.index') }}" class="btn btn-alt-secondary">Back to Guest List</a>
    </div>

    <div class="block block-rounded">
        <div class="block-content">
            <form method="POST" action="{{ route('admin.guest-list.store') }}" id="guestForm">
                @csrf

                <input type="hidden" name="event_name" value="{{ $selectedEventName }}">
                <input type="hidden" name="guest_type" value="{{ $selectedGuestType }}">

                <div class="row g-3 mb-3">
                    <div class="col-md-4">
                        <label class="form-label">Event</label>
                        <select class="form-select" disabled>
                            @foreach($eventNames as $eventName)
                                <option value="{{ $eventName }}" @selected($eventName === $selectedEventName)>{{ $eventName }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Guest Type</label>
                        <input class="form-control" value="{{ $selectedGuestType }}" disabled>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Status</label>
                        <select name="status" class="form-select">
                            @foreach(['not_checked_in' => 'Not Checked In', 'checked_in' => 'Checked In', 'canceled' => 'Canceled'] as $value => $label)
                                <option value="{{ $value }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div class="mb-2">
                    <h5 class="mb-0">guest list</h5>
                </div>

                <div class="table-responsive">
                    <table class="table table-bordered" id="rowsTable">
                        <thead>
                            <tr>
                                <th>Name *</th>
                                <th>Email</th>
                                <th>Phone</th>
                                <th>Gender</th>
                                <th style="width:80px"></th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                        <tfoot>
                            <tr>
                                <td colspan="5" class="text-start">
                                    <button type="button" id="addInvitationRow" class="btn btn-sm btn-alt-primary">Add Invitation</button>
                                </td>
                            </tr>
                        </tfoot>
                    </table>
                </div>

                <div class="mt-3 d-flex gap-2 justify-content-end">
                    <a href="{{ route('admin.guest-list.index') }}" class="btn btn-alt-secondary">Cancel</a>
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
    const tbody = document.querySelector('#rowsTable tbody');
    const addRowButton = document.getElementById('addInvitationRow');
    let rowIndex = 0;
    const initialCount = {{ max(1, (int) $selectedCount) }};

    function addRow(name = '', email = '', phone = '', gender = '') {
        const tr = document.createElement('tr');
        const normalizedGender = ['male', 'female'].includes(String(gender).toLowerCase()) ? String(gender).toLowerCase() : '';

        tr.innerHTML = `
            <td><input type="text" name="guests[${rowIndex}][name]" class="form-control" value="${name}" required></td>
            <td><input type="email" name="guests[${rowIndex}][email]" class="form-control" value="${email}"></td>
            <td><input type="text" name="guests[${rowIndex}][phone]" class="form-control" value="${phone}"></td>
            <td>
                <select name="guests[${rowIndex}][gender]" class="form-select">
                    <option value="" ${normalizedGender === '' ? 'selected' : ''}>-</option>
                    <option value="male" ${normalizedGender === 'male' ? 'selected' : ''}>Male</option>
                    <option value="female" ${normalizedGender === 'female' ? 'selected' : ''}>Female</option>
                </select>
            </td>
            <td class="text-center"><button type="button" class="btn btn-sm btn-alt-danger remove-row"><i class="fa fa-trash"></i></button></td>
        `;
        tbody.appendChild(tr);
        rowIndex++;
    }

    function ensureAtLeastOneRow() {
        if (tbody.querySelectorAll('tr').length === 0) {
            addRow();
        }
    }

    addRowButton.addEventListener('click', function () {
        addRow();
    });

    tbody.addEventListener('click', function (event) {
        const button = event.target.closest('.remove-row');
        if (! button) {
            return;
        }

        button.closest('tr')?.remove();
        ensureAtLeastOneRow();
    });

    for (let i = 0; i < initialCount; i++) {
        addRow();
    }
})();
</script>
@endpush
