@php
    $ticketRows = old('tickets', isset($event) ? $event->tickets->map(fn($ticket) => [
        'name' => $ticket->name,
        'price' => $ticket->price,
        'status' => $ticket->status,
        'label' => $ticket->label,
        'description' => $ticket->description,
    ])->toArray() : [['status' => 'active']]);

    if (empty($ticketRows)) {
        $ticketRows = [['status' => 'active']];
    }

    $feeRows = old('fees', isset($event) ? $event->fees->map(fn($fee) => [
        'name' => $fee->name,
        'fee_type' => $fee->fee_type,
        'value' => $fee->value,
        'description' => $fee->description,
    ])->toArray() : [['fee_type' => 'percentage']]);

    if (empty($feeRows)) {
        $feeRows = [['fee_type' => 'percentage']];
    }
@endphp

<div class="row">
    <div class="col-md-6 mb-3">
        <label class="form-label">Event Name</label>
        <input name="name" class="form-control" value="{{ old('name', $event->name ?? '') }}" required>
    </div>
    <div class="col-md-3 mb-3">
        <label class="form-label">Date</label>
        <input type="date" name="event_date" class="form-control" value="{{ old('event_date', isset($event) ? $event->event_date?->format('Y-m-d') : '') }}" required>
    </div>
    <div class="col-md-3 mb-3">
        <label class="form-label">Time</label>
        <input type="time" name="event_time" class="form-control" value="{{ old('event_time', $event->event_time ?? '') }}" required>
    </div>
    <div class="col-md-6 mb-3">
        <label class="form-label">Location</label>
        <input name="location" class="form-control" value="{{ old('location', $event->location ?? '') }}" required>
    </div>
    <div class="col-md-6 mb-3">
        <label class="form-label">Map URL (Optional)</label>
        <input name="map_url" class="form-control" value="{{ old('map_url', $event->map_url ?? '') }}">
    </div>
    <div class="col-md-4 mb-3">
        <label class="form-label">Status</label>
        <select name="status" class="form-select">
            <option value="active" @selected(old('status', $event->status ?? 'active') === 'active')>Active</option>
            <option value="inactive" @selected(old('status', $event->status ?? '') === 'inactive')>Inactive</option>
            <option value="draft" @selected(old('status', $event->status ?? '') === 'draft')>Draft</option>
            <option value="sold_out" @selected(old('status', $event->status ?? '') === 'sold_out')>Sold Out</option>
        </select>
    </div>
    <div class="col-md-4 mb-3">
        <label class="form-label">Checkout Flow</label>
        <select name="requires_booking_approval" class="form-select">
            <option value="1" @selected((int) old('requires_booking_approval', $event->requires_booking_approval ?? 1) === 1)>Require admin approval before payment</option>
            <option value="0" @selected((int) old('requires_booking_approval', $event->requires_booking_approval ?? 1) === 0)>Allow direct payment at checkout</option>
        </select>
    </div>
    <div class="col-md-8 mb-3">
        <label class="form-label">Event Image</label>
        <input type="file" name="cover_image" id="cover_image" class="form-control" accept="image/*">
        <div class="form-text">Optional. Uploading a new image will replace the current cover image.</div>
    </div>
    <div class="col-md-4 mb-3">
        <label class="form-label">Image Preview</label>
        <div class="border rounded p-2 bg-body-light d-flex align-items-center justify-content-center" style="min-height: 140px;">
            <img
                id="cover_image_preview"
                data-original-src="{{ $event->cover_image_url ?? '' }}"
                src="{{ $event->cover_image_url ?? '' }}"
                alt="Cover image preview"
                style="max-height: 130px; width: 100%; object-fit: cover; {{ isset($event) && $event->cover_image_url ? '' : 'display:none;' }}"
            >
            <span id="cover_image_placeholder" class="text-muted small {{ isset($event) && $event->cover_image_url ? 'd-none' : '' }}">No image selected</span>
        </div>
    </div>
    <div class="col-12 mb-3">
        <label class="form-label">Description</label>
        <textarea name="description" class="form-control js-ckeditor-description" rows="3" required>{{ old('description', $event->description ?? '') }}</textarea>
    </div>
    <div class="col-12 mb-3">
        <label class="form-label">House Rules</label>
        <textarea name="house_rules" class="form-control" rows="3">{{ old('house_rules', $event->house_rules ?? '') }}</textarea>
    </div>
    <div class="col-12 mb-3">
        <label class="form-label">Gallery Images (Optional, Multiple)</label>
        <input type="file" name="gallery_images[]" class="form-control" multiple>
    </div>

    @if(isset($event) && $event->images->count())
        <div class="col-12 mb-3">
            <div class="small text-muted">Current Images: {{ $event->images->count() }}</div>
            <div class="form-check">
                <input class="form-check-input" type="checkbox" value="1" name="replace_gallery" id="replace_gallery">
                <label class="form-check-label" for="replace_gallery">Replace gallery images</label>
            </div>
        </div>
    @endif
</div>

<div class="d-flex justify-content-between align-items-center mt-4 mb-2">
    <h4 class="h5 mb-0">Ticket Types</h4>
    <button type="button" class="btn btn-sm btn-alt-primary" id="add-ticket-row">Add Ticket Type</button>
</div>
<div id="ticket-rows" data-next-index="{{ count($ticketRows) }}">
    @foreach($ticketRows as $index => $ticket)
        <div class="row border rounded p-3 mb-2 ticket-row">
            <div class="col-md-3 mb-2"><label class="form-label">Ticket Name</label><input class="form-control" name="tickets[{{ $index }}][name]" value="{{ $ticket['name'] ?? '' }}"></div>
            <div class="col-md-2 mb-2"><label class="form-label">Price</label><input type="number" step="0.01" class="form-control" name="tickets[{{ $index }}][price]" value="{{ $ticket['price'] ?? '' }}"></div>
            <div class="col-md-2 mb-2"><label class="form-label">Status</label><select class="form-select" name="tickets[{{ $index }}][status]"><option value="active" @selected(($ticket['status'] ?? 'active') === 'active')>Active</option><option value="inactive" @selected(($ticket['status'] ?? '') === 'inactive')>Inactive</option><option value="sold_out" @selected(($ticket['status'] ?? '') === 'sold_out')>Sold Out</option></select></div>
            <div class="col-md-2 mb-2"><label class="form-label">Label</label><input class="form-control" name="tickets[{{ $index }}][label]" value="{{ $ticket['label'] ?? '' }}"></div>
            <div class="col-md-2 mb-2"><label class="form-label">Description</label><input class="form-control" name="tickets[{{ $index }}][description]" value="{{ $ticket['description'] ?? '' }}"></div>
            <div class="col-md-1 mb-2 d-flex align-items-end"><button type="button" class="btn btn-sm btn-alt-danger w-100 remove-row"><i class="fa fa-trash"></i></button></div>
        </div>
    @endforeach
</div>

<div class="d-flex justify-content-between align-items-center mt-4 mb-2">
    <h4 class="h5 mb-0">Event Fees</h4>
    <button type="button" class="btn btn-sm btn-alt-primary" id="add-fee-row">Add Fee</button>
</div>
<div id="fee-rows" data-next-index="{{ count($feeRows) }}">
    @foreach($feeRows as $index => $fee)
        <div class="row border rounded p-3 mb-2 fee-row">
            <div class="col-md-3 mb-2"><label class="form-label">Fee Name</label><input class="form-control" name="fees[{{ $index }}][name]" value="{{ $fee['name'] ?? '' }}"></div>
            <div class="col-md-3 mb-2"><label class="form-label">Fee Type</label><select class="form-select" name="fees[{{ $index }}][fee_type]"><option value="percentage" @selected(($fee['fee_type'] ?? 'percentage') === 'percentage')>Percentage (%)</option><option value="fixed" @selected(($fee['fee_type'] ?? '') === 'fixed')>Fixed Amount</option></select></div>
            <div class="col-md-2 mb-2"><label class="form-label">Value</label><input type="number" step="0.01" class="form-control" name="fees[{{ $index }}][value]" value="{{ $fee['value'] ?? '' }}"></div>
            <div class="col-md-3 mb-2"><label class="form-label">Description</label><input class="form-control" name="fees[{{ $index }}][description]" value="{{ $fee['description'] ?? '' }}"></div>
            <div class="col-md-1 mb-2 d-flex align-items-end"><button type="button" class="btn btn-sm btn-alt-danger w-100 remove-row"><i class="fa fa-trash"></i></button></div>
        </div>
    @endforeach
</div>

<template id="ticket-row-template">
    <div class="row border rounded p-3 mb-2 ticket-row">
        <div class="col-md-3 mb-2"><label class="form-label">Ticket Name</label><input class="form-control" name="__NAME__[name]"></div>
        <div class="col-md-2 mb-2"><label class="form-label">Price</label><input type="number" step="0.01" class="form-control" name="__NAME__[price]"></div>
        <div class="col-md-2 mb-2"><label class="form-label">Status</label><select class="form-select" name="__NAME__[status]"><option value="active">Active</option><option value="inactive">Inactive</option><option value="sold_out">Sold Out</option></select></div>
        <div class="col-md-2 mb-2"><label class="form-label">Label</label><input class="form-control" name="__NAME__[label]"></div>
        <div class="col-md-2 mb-2"><label class="form-label">Description</label><input class="form-control" name="__NAME__[description]"></div>
        <div class="col-md-1 mb-2 d-flex align-items-end"><button type="button" class="btn btn-sm btn-alt-danger w-100 remove-row"><i class="fa fa-trash"></i></button></div>
    </div>
</template>

<template id="fee-row-template">
    <div class="row border rounded p-3 mb-2 fee-row">
        <div class="col-md-3 mb-2"><label class="form-label">Fee Name</label><input class="form-control" name="__NAME__[name]"></div>
        <div class="col-md-3 mb-2"><label class="form-label">Fee Type</label><select class="form-select" name="__NAME__[fee_type]"><option value="percentage">Percentage (%)</option><option value="fixed">Fixed Amount</option></select></div>
        <div class="col-md-2 mb-2"><label class="form-label">Value</label><input type="number" step="0.01" class="form-control" name="__NAME__[value]"></div>
        <div class="col-md-3 mb-2"><label class="form-label">Description</label><input class="form-control" name="__NAME__[description]"></div>
        <div class="col-md-1 mb-2 d-flex align-items-end"><button type="button" class="btn btn-sm btn-alt-danger w-100 remove-row"><i class="fa fa-trash"></i></button></div>
    </div>
</template>

<script>
    (() => {
        const addRow = (containerId, templateId, prefix) => {
            const container = document.getElementById(containerId);
            const template = document.getElementById(templateId);
            if (!container || !template) {
                return;
            }

            const nextIndex = Number(container.dataset.nextIndex || 0);
            const html = template.innerHTML.replaceAll('__NAME__', `${prefix}[${nextIndex}]`);
            container.insertAdjacentHTML('beforeend', html);
            container.dataset.nextIndex = String(nextIndex + 1);
        };

        document.getElementById('add-ticket-row')?.addEventListener('click', () => {
            addRow('ticket-rows', 'ticket-row-template', 'tickets');
        });

        document.getElementById('add-fee-row')?.addEventListener('click', () => {
            addRow('fee-rows', 'fee-row-template', 'fees');
        });

        document.addEventListener('click', (event) => {
            const btn = event.target.closest('.remove-row');
            if (!btn) {
                return;
            }

            btn.closest('.ticket-row, .fee-row')?.remove();
        });

        const coverInput = document.getElementById('cover_image');
        const coverPreview = document.getElementById('cover_image_preview');
        const coverPlaceholder = document.getElementById('cover_image_placeholder');

        coverInput?.addEventListener('change', (event) => {
            const [file] = event.target.files || [];

            if (!file) {
                const originalSrc = coverPreview?.dataset.originalSrc || '';

                if (originalSrc) {
                    coverPreview.src = originalSrc;
                    coverPreview.style.display = '';
                    coverPlaceholder?.classList.add('d-none');
                } else {
                    coverPreview.style.display = 'none';
                    coverPlaceholder?.classList.remove('d-none');
                }

                return;
            }

            const reader = new FileReader();
            reader.onload = ({ target }) => {
                coverPreview.src = target?.result || '';
                coverPreview.style.display = '';
                coverPlaceholder?.classList.add('d-none');
            };

            reader.readAsDataURL(file);
        });
    })();
</script>
