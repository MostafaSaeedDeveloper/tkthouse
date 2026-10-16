@extends('admin.master')
@section('title', 'Create Guest Tickets')

@section('content')
<x-admin.page-header title="Create Guest Tickets" :subtitle="'Event: '.$selectedEventName.' | Type: '.$selectedGuestType" :crumbs="['Dashboard' => route('admin.dashboard'), 'Tickets' => route('admin.tickets.index'), 'Guest List' => route('admin.guest-list.index'), 'Create']">
    <a href="{{ route('admin.guest-list.index') }}" class="a-btn a-btn--outline">Back to Guest List</a>
</x-admin.page-header>

<x-admin.flash />

<form method="POST" action="{{ route('admin.guest-list.store') }}" id="guestForm" data-a-loading>
    @csrf

    <input type="hidden" name="event_name" value="{{ $selectedEventName }}">
    <input type="hidden" name="guest_type" value="{{ $selectedGuestType }}">

    <x-admin.card title="Invitation details">
        <div class="a-form-grid a-form-grid--3">
            <div class="a-field">
                <label class="form-label" for="gcEvent">Event</label>
                <select class="form-select no-select2" id="gcEvent" disabled>
                    @foreach($eventNames as $eventName)
                        <option value="{{ $eventName }}" @selected($eventName === $selectedEventName)>{{ $eventName }}</option>
                    @endforeach
                </select>
            </div>
            <div class="a-field">
                <label class="form-label" for="gcType">Guest Type</label>
                <input class="form-control" id="gcType" value="{{ $selectedGuestType }}" disabled>
            </div>
            <x-admin.field label="Status" for="gcStatus" name="status">
                <select name="status" id="gcStatus" class="form-select no-select2">
                    @foreach(['not_checked_in' => 'Not Checked In', 'checked_in' => 'Checked In', 'canceled' => 'Canceled'] as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
            </x-admin.field>
        </div>
    </x-admin.card>

    <x-admin.card flush title="Guest List" subtitle="Name is required. Add one row per guest.">
        <x-slot:actions><button type="button" id="addInvitationRow" class="a-btn a-btn--outline a-btn--sm"><i class="fa fa-plus"></i> Add Invitation</button></x-slot:actions>
        <x-admin.table stack id="rowsTable" class="a-tk-rows" caption="Guests to invite">
            <thead>
                <tr>
                    <th>Name *</th>
                    <th>Email</th>
                    <th>Phone</th>
                    <th>Gender</th>
                    <th><span class="a-sr-only">Remove</span></th>
                </tr>
            </thead>
            <tbody></tbody>
        </x-admin.table>
        <x-slot:footer>
            <div class="a-btn-group a-tk-foot">
                <a href="{{ route('admin.guest-list.index') }}" class="a-btn a-btn--ghost">Cancel</a>
                <button type="submit" class="a-btn a-btn--primary">Create Tickets</button>
            </div>
        </x-slot:footer>
    </x-admin.card>
</form>
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
            <td data-label="Name *"><input type="text" aria-label="Guest name" name="guests[${rowIndex}][name]" class="form-control" value="${name}" required></td>
            <td data-label="Email"><input type="email" aria-label="Guest email" name="guests[${rowIndex}][email]" class="form-control" value="${email}"></td>
            <td data-label="Phone"><input type="tel" aria-label="Guest phone" name="guests[${rowIndex}][phone]" data-phone-intl class="form-control" value="${phone}"></td>
            <td data-label="Gender">
                <select aria-label="Guest gender" name="guests[${rowIndex}][gender]" class="form-select no-select2">
                    <option value="" ${normalizedGender === '' ? 'selected' : ''}>-</option>
                    <option value="male" ${normalizedGender === 'male' ? 'selected' : ''}>Male</option>
                    <option value="female" ${normalizedGender === 'female' ? 'selected' : ''}>Female</option>
                </select>
            </td>
            <td data-label=""><button type="button" class="a-btn a-btn--danger a-btn--sm remove-row" aria-label="Remove guest row"><i class="fa fa-trash"></i> Remove</button></td>
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
