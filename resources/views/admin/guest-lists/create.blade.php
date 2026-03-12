@extends('admin.master')

@section('content')
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
                <div class="col-md-4">
                    <label class="form-label">Event</label>
                    <select name="event_id" class="form-select" required>
                        <option value="">Select event</option>
                        @foreach($events as $event)
                            <option value="{{ $event->id }}" @selected((int) old('event_id', session('import_event_id')) === $event->id)>{{ $event->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Import Excel/CSV</label>
                    <input type="file" class="form-control" name="file" accept=".csv,.txt,.xlsx" required>
                </div>
                <div class="col-md-4 d-flex gap-2">
                    <button class="btn btn-alt-primary" type="submit">Import</button>
                    <button class="btn btn-alt-info" type="submit" formaction="{{ route('admin.guest-lists.export') }}" formmethod="GET">Export Event List</button>
                </div>
            </form>
        </div>
    </div>

    <form method="POST" action="{{ route('admin.guest-lists.store') }}" id="guest-list-form">
        @csrf
        <div class="block block-rounded">
            <div class="block-content">
                <div class="mb-3" style="max-width:420px;">
                    <label class="form-label">Event</label>
                    <select name="event_id" class="form-select" required>
                        <option value="">Select event</option>
                        @foreach($events as $event)
                            <option value="{{ $event->id }}" @selected((int) old('event_id', session('import_event_id')) === $event->id)>{{ $event->name }}</option>
                        @endforeach
                    </select>
                </div>

                @php($rows = old('guests', session('imported_guests', [['name'=>'','email'=>'','phone'=>'','type'=>'']])) )
                <div class="table-responsive">
                    <table class="table table-bordered" id="guest-table">
                        <thead>
                        <tr>
                            <th>Name *</th>
                            <th>Email</th>
                            <th>Phone</th>
                            <th>Type/Category</th>
                            <th style="width:60px"></th>
                        </tr>
                        </thead>
                        <tbody>
                        @foreach($rows as $index => $row)
                            <tr>
                                <td><input class="form-control" name="guests[{{ $index }}][name]" value="{{ $row['name'] ?? '' }}" required></td>
                                <td><input class="form-control" name="guests[{{ $index }}][email]" value="{{ $row['email'] ?? '' }}"></td>
                                <td><input class="form-control" name="guests[{{ $index }}][phone]" value="{{ $row['phone'] ?? '' }}"></td>
                                <td><input class="form-control" name="guests[{{ $index }}][type]" value="{{ $row['type'] ?? '' }}"></td>
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
@endsection

@push('scripts')
<script>
(() => {
    const tableBody = document.querySelector('#guest-table tbody');
    const addRowBtn = document.getElementById('add-row');

    const reindex = () => {
        [...tableBody.querySelectorAll('tr')].forEach((row, idx) => {
            row.querySelectorAll('input').forEach((input) => {
                input.name = input.name.replace(/guests\[\d+\]/, `guests[${idx}]`);
            });
        });
    };

    addRowBtn?.addEventListener('click', () => {
        const row = document.createElement('tr');
        row.innerHTML = `
            <td><input class="form-control" name="guests[0][name]" required></td>
            <td><input class="form-control" name="guests[0][email]"></td>
            <td><input class="form-control" name="guests[0][phone]"></td>
            <td><input class="form-control" name="guests[0][type]"></td>
            <td><button type="button" class="btn btn-sm btn-alt-danger remove-row">×</button></td>
        `;
        tableBody.appendChild(row);
        reindex();
    });

    tableBody?.addEventListener('click', (e) => {
        if (!e.target.classList.contains('remove-row')) return;
        e.target.closest('tr')?.remove();
        reindex();
    });
})();
</script>
@endpush
