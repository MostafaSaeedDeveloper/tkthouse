@csrf
<div class="row g-3">
    <div class="col-md-6">
        <label class="form-label">Status</label>
        <select name="status" class="form-select">
            @php($statuses = ['not_checked_in' => 'Not Checked In', 'checked_in' => 'Checked In', 'canceled' => 'Canceled'])
            @foreach($statuses as $value => $label)
                <option value="{{ $value }}" @selected(old('status', $ticket->status ?? 'not_checked_in') === $value)>{{ $label }}</option>
            @endforeach
        </select>
    </div>

    <div class="col-md-4">
        <label class="form-label">Holder Name</label>
        <input type="text" name="holder_name" value="{{ old('holder_name', $ticket->holder_name ?? '') }}" class="form-control">
    </div>
    <div class="col-md-4">
        <label class="form-label">Holder Email</label>
        <input type="email" name="holder_email" value="{{ old('holder_email', $ticket->holder_email ?? '') }}" class="form-control">
    </div>
    <div class="col-md-4">
        <label class="form-label">Holder Phone</label>
        <input type="text" name="holder_phone" value="{{ old('holder_phone', $ticket->holder_phone ?? '') }}" class="form-control">
    </div>

    <div class="col-md-12">
        <label class="form-label">Description</label>
        <textarea name="description" class="form-control" rows="4">{{ old('description', $ticket->description ?? '') }}</textarea>
    </div>
</div>

<div class="mt-4 d-flex gap-2">
    <button class="btn btn-primary" type="submit">Save Ticket</button>
    @if(isset($ticket) && $ticket->exists)
        <a href="{{ route('admin.tickets.show', $ticket) }}" class="btn btn-alt-secondary">View Ticket</a>
    @endif
    <a href="{{ route('admin.tickets.index') }}" class="btn btn-alt-secondary">Back</a>
</div>
