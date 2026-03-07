@extends('admin.master')

@section('content')
<div class="content">
    @include('admin.partials.flash')
    <div class="block block-rounded">
        <div class="block-header block-header-default"><h3 class="block-title">Create Guest List Invitations</h3></div>
        <div class="block-content">
            <form method="POST" action="{{ route('admin.guest-list.store') }}" id="guestListForm">
                @csrf
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Event</label>
                        <select name="event_id" class="form-select js-select2" required>
                            <option value="">Select event</option>
                            @foreach($events as $event)
                                <option value="{{ $event->id }}" @selected(old('event_id') == $event->id)>{{ $event->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-12">
                        <label class="form-label">Description (optional)</label>
                        <textarea name="description" class="form-control" rows="2">{{ old('description') }}</textarea>
                    </div>
                </div>

                <hr>
                <h5 class="mb-3">Invitations</h5>

                <div class="table-responsive">
                    <table class="table table-bordered" id="guestRowsTable">
                        <thead>
                            <tr>
                                <th style="width:45%">Name (optional)</th>
                                <th style="width:45%">Email (optional)</th>
                                <th style="width:10%"></th>
                            </tr>
                        </thead>
                        <tbody>
                            @php($oldRows = old('rows', [['holder_name' => '', 'holder_email' => '']]))
                            @foreach($oldRows as $i => $row)
                                <tr>
                                    <td><input type="text" name="rows[{{ $i }}][holder_name]" class="form-control" value="{{ $row['holder_name'] ?? '' }}" placeholder="Guest name"></td>
                                    <td><input type="email" name="rows[{{ $i }}][holder_email]" class="form-control" value="{{ $row['holder_email'] ?? '' }}" placeholder="guest@example.com"></td>
                                    <td><button type="button" class="btn btn-sm btn-alt-danger w-100 remove-row"><i class="fa fa-trash"></i></button></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="d-flex gap-2 mb-4">
                    <button type="button" id="addRowBtn" class="btn btn-alt-primary"><i class="fa fa-plus me-1"></i>Add Row</button>
                </div>

                <div class="d-flex gap-2">
                    <button class="btn btn-primary" type="submit">Create Invitations</button>
                    <a href="{{ route('admin.guest-list.index') }}" class="btn btn-alt-secondary">Back</a>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        const tbody = document.querySelector('#guestRowsTable tbody');
        const addBtn = document.getElementById('addRowBtn');

        function updateIndexes() {
            [...tbody.querySelectorAll('tr')].forEach((row, index) => {
                row.querySelectorAll('input').forEach((input) => {
                    input.name = input.name.replace(/rows\[\d+\]/, 'rows[' + index + ']');
                });
            });
        }

        addBtn.addEventListener('click', function () {
            const index = tbody.querySelectorAll('tr').length;
            const tr = document.createElement('tr');
            tr.innerHTML = `
                <td><input type="text" name="rows[${index}][holder_name]" class="form-control" placeholder="Guest name"></td>
                <td><input type="email" name="rows[${index}][holder_email]" class="form-control" placeholder="guest@example.com"></td>
                <td><button type="button" class="btn btn-sm btn-alt-danger w-100 remove-row"><i class="fa fa-trash"></i></button></td>
            `;
            tbody.appendChild(tr);
        });

        tbody.addEventListener('click', function (event) {
            const btn = event.target.closest('.remove-row');
            if (!btn) return;
            if (tbody.querySelectorAll('tr').length === 1) return;
            btn.closest('tr').remove();
            updateIndexes();
        });
    });
</script>
@endsection
