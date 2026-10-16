@csrf
@php($statuses = ['not_checked_in' => 'Not Checked In', 'checked_in' => 'Checked In', 'canceled' => 'Canceled'])
<x-admin.form-section title="Ticket status" description="Where this ticket stands at the door." :cols="2">
    <x-admin.field label="Status" for="ticket_status" name="status">
        <select name="status" id="ticket_status" class="form-select no-select2">
            @foreach($statuses as $value => $label)
                <option value="{{ $value }}" @selected(old('status', $ticket->status ?? 'not_checked_in') === $value)>{{ $label }}</option>
            @endforeach
        </select>
    </x-admin.field>
</x-admin.form-section>

<x-admin.form-section title="Holder" description="Who the ticket is issued to." :cols="3">
    <x-admin.field label="Holder Name" for="holder_name" name="holder_name">
        <input type="text" id="holder_name" name="holder_name" value="{{ old('holder_name', $ticket->holder_name ?? '') }}" class="form-control">
    </x-admin.field>
    <x-admin.field label="Holder Email" for="holder_email" name="holder_email">
        <input type="email" id="holder_email" name="holder_email" value="{{ old('holder_email', $ticket->holder_email ?? '') }}" class="form-control">
    </x-admin.field>
    <x-admin.field label="Holder Phone" for="holder_phone" name="holder_phone">
        <input type="tel" id="holder_phone" name="holder_phone" data-phone-intl value="{{ old('holder_phone', $ticket->holder_phone ?? '') }}" class="form-control">
    </x-admin.field>
    <x-admin.field label="Description" for="ticket_description" name="description" class="is-full">
        <textarea id="ticket_description" name="description" class="form-control" rows="4">{{ old('description', $ticket->description ?? '') }}</textarea>
    </x-admin.field>
</x-admin.form-section>

<div class="a-form-actions">
    <a href="{{ route('admin.tickets.index') }}" class="a-btn a-btn--ghost">Back</a>
    @if(isset($ticket) && $ticket->exists)
        <a href="{{ route('admin.tickets.show', $ticket) }}" class="a-btn a-btn--outline">View Ticket</a>
    @endif
    <button class="a-btn a-btn--primary" type="submit">Save Ticket</button>
</div>
