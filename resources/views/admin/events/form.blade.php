@php
    $lineupRows = old('lineups', isset($event) ? $event->lineups->map(fn($lineup) => [
        'artist_name' => $lineup->artist_name,
        'instagram' => $lineup->instagram,
        'performance_time' => $lineup->performance_time,
        'performance_date' => $lineup->performance_date?->format('Y-m-d'),
        'image' => $lineup->image,
        'sort_order' => $lineup->sort_order,
        '_existing_image_url' => $lineup->image_url,
    ])->toArray() : []);

    $ticketSync = app(\App\Services\Events\EventTicketSync::class);
    $ticketRows = old('tickets', isset($event) ? $event->tickets->map(fn($ticket) => [
        'id' => $ticket->id,
        'sold' => $ticketSync->usage($event, $ticket)['total'],
        'name' => $ticket->name,
        'price' => $ticket->price,
        'status' => $ticket->status,
        'color' => $ticket->color,
        'label' => $ticket->label,
        'description' => $ticket->description,
        'max_per_order' => $ticket->max_per_order,
        'is_couple' => (bool) $ticket->is_couple,
    ])->toArray() : [['status' => 'active', 'color' => '#0d6efd', 'max_per_order' => 10, 'is_couple' => false]]);

    if (empty($ticketRows)) {
        $ticketRows = [['status' => 'active', 'color' => '#0d6efd', 'max_per_order' => 10, 'is_couple' => false]];
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

<nav class="a-ev-jump" aria-label="Jump to section">
    <a href="#ev-basic">Basics</a><a href="#ev-sale">Availability</a><a href="#ev-media">Media</a><a href="#ev-content">Content</a><a href="#ev-tickets">Tickets</a><a href="#ev-fees">Fees</a><a href="#ev-lineup">Lineup</a>
</nav>

<x-admin.form-section id="ev-basic" title="Basic information" description="Name, schedule and where the event takes place." :cols="2">
    <x-admin.field label="Event Name" for="event_name" name="name" required class="is-full">
        <input name="name" id="event_name" class="form-control" value="{{ old('name', $event->name ?? '') }}" required>
    </x-admin.field>
    <x-admin.field label="Start Date" for="event_date" name="event_date" required>
        <input type="text" name="event_date" id="event_date" class="form-control js-flatpickr" value="{{ old('event_date', isset($event) ? $event->event_date?->format('Y-m-d') : '') }}" data-date-format="Y-m-d" required>
    </x-admin.field>
    <x-admin.field label="End Date" for="end_date" name="end_date" hint="Optional, for multi-day events.">
        <input type="text" name="end_date" id="end_date" class="form-control js-flatpickr" value="{{ old('end_date', isset($event) ? $event->end_date?->format('Y-m-d') : '') }}" data-date-format="Y-m-d">
    </x-admin.field>
    <x-admin.field label="Time" for="event_time" name="event_time" required>
        <input type="time" name="event_time" id="event_time" class="form-control" value="{{ old('event_time', $event->event_time ?? '') }}" required>
    </x-admin.field>
    <x-admin.field label="Location" for="event_location" name="location" required>
        <input name="location" id="event_location" class="form-control" value="{{ old('location', $event->location ?? '') }}" required>
    </x-admin.field>
    <x-admin.field label="Slug" for="event_slug" name="slug" hint="Leave empty to generate it from the event name.">
        <input name="slug" id="event_slug" class="form-control" value="{{ old('slug', $event->slug ?? '') }}" placeholder="auto-generated-from-name" pattern="[a-z0-9]+(-[a-z0-9]+)*" title="Lowercase letters, numbers and single hyphens">
        <span class="a-hint" id="slug-preview" aria-live="polite"></span>
    </x-admin.field>
    <x-admin.field label="Map URL (Optional)" for="event_map_url" name="map_url">
        <input type="url" name="map_url" id="event_map_url" class="form-control" placeholder="https://maps.google.com/…" value="{{ old('map_url', $event->map_url ?? '') }}">
    </x-admin.field>
    <x-admin.field label="Map Embed Code (Optional)" for="event_map_embed" name="map_embed" class="is-full">
        <textarea name="map_embed" id="event_map_embed" class="form-control" rows="4" placeholder='<iframe src="https://www.google.com/maps/embed?pb=..." ...></iframe>'>{{ old('map_embed', $event->map_embed ?? '') }}</textarea>
    </x-admin.field>
</x-admin.form-section>

<x-admin.form-section id="ev-sale" class="a-ev-block" title="Availability & checkout" description="Control whether the event is on sale and how customers pay." :cols="2">
    <x-admin.field label="Status" for="event_status" name="status">
        <select name="status" id="event_status" class="form-select">
            <option value="active" @selected(old('status', $event->status ?? 'active') === 'active')>Active</option>
            <option value="inactive" @selected(old('status', $event->status ?? '') === 'inactive')>Inactive</option>
            <option value="draft" @selected(old('status', $event->status ?? '') === 'draft')>Draft</option>
            <option value="sold_out" @selected(old('status', $event->status ?? '') === 'sold_out')>Sold Out</option>
        </select>
    </x-admin.field>
    <x-admin.field label="Checkout Flow" for="event_checkout_flow" name="requires_booking_approval">
        <select name="requires_booking_approval" id="event_checkout_flow" class="form-select">
            <option value="1" @selected((int) old('requires_booking_approval', $event->requires_booking_approval ?? 1) === 1)>Require admin approval before payment</option>
            <option value="0" @selected((int) old('requires_booking_approval', $event->requires_booking_approval ?? 1) === 0)>Allow direct payment at checkout</option>
        </select>
    </x-admin.field>
</x-admin.form-section>

<x-admin.form-section id="ev-media" class="a-ev-block" title="Media" description="Cover, home page banner, venue map and gallery images." :cols="1">
    <div class="a-ev-media">
        <x-admin.field label="Event Image (Optional)" for="cover_image" name="cover_image" hint="Upload an image to be shown as the event cover.">
            <input type="file" name="cover_image" id="cover_image" class="form-control" accept="image/*">
        </x-admin.field>
        <div>
            <span class="form-label">Image Preview</span>
            <div class="a-ev-preview">
                <img id="cover-image-preview" data-has-existing="{{ isset($event) && $event->cover_image ? 1 : 0 }}" src="{{ isset($event) ? ($event->cover_image_url ?? '') : '' }}" alt="Event cover preview" class="{{ isset($event) && $event->cover_image ? '' : 'd-none' }}">
                <div id="cover-image-placeholder" class="{{ isset($event) && $event->cover_image ? 'd-none' : '' }}">No image selected.</div>
            </div>
        </div>
    </div>

    <div class="a-ev-media">
        <x-admin.field label="Event Banner (Optional)" for="event_banner" name="event_banner" hint="Upload a banner image to appear in the home page slider.">
            <input type="file" name="event_banner" id="event_banner" class="form-control" accept="image/*">
        </x-admin.field>
        <div>
            <span class="form-label">Banner Preview</span>
            <div class="a-ev-preview">
                <img id="event-banner-preview" data-has-existing="{{ isset($event) && $event->event_banner ? 1 : 0 }}" src="{{ isset($event) ? ($event->event_banner_url ?? '') : '' }}" alt="Event banner preview" class="{{ isset($event) && $event->event_banner ? '' : 'd-none' }}">
                <div id="event-banner-placeholder" class="{{ isset($event) && $event->event_banner ? 'd-none' : '' }}">No banner selected.</div>
            </div>
        </div>
    </div>

    <div class="a-ev-media">
        <x-admin.field label="Venue Map Image (Optional)" for="venue_map" name="venue_map" hint="Upload a venue map image to show before House Rules on the event page.">
            <input type="file" name="venue_map" id="venue_map" class="form-control" accept="image/*">
        </x-admin.field>
        <div>
            <span class="form-label">Venue Map Preview</span>
            <div class="a-ev-preview">
                <img id="venue-map-preview" data-has-existing="{{ isset($event) && $event->venue_map ? 1 : 0 }}" src="{{ isset($event) ? ($event->venue_map_url ?? '') : '' }}" alt="Venue map preview" class="{{ isset($event) && $event->venue_map ? '' : 'd-none' }}">
                <div id="venue-map-placeholder" class="{{ isset($event) && $event->venue_map ? 'd-none' : '' }}">No venue map selected.</div>
            </div>
        </div>
    </div>

    <x-admin.field label="Gallery Images (Optional, Multiple)" for="gallery_images" name="gallery_images">
        <input type="file" name="gallery_images[]" id="gallery_images" class="form-control" multiple>
    </x-admin.field>

    @if(isset($event) && $event->images->count())
        <div>
            <div class="a-hint">Current Images: {{ $event->images->count() }}</div>
            <div class="form-check">
                <input class="form-check-input" type="checkbox" value="1" name="replace_gallery" id="replace_gallery">
                <label class="form-check-label" for="replace_gallery">Replace gallery images</label>
            </div>
        </div>
    @endif
</x-admin.form-section>

<x-admin.form-section id="ev-content" class="a-ev-block" title="Content" description="Shown to attendees on the event page." :cols="1">
    <x-admin.field label="Description (Optional)" for="event_description" name="description" hint="You can leave this empty if you don't need a description.">
        <textarea name="description" id="event_description" class="form-control js-ckeditor-description" rows="3" placeholder="Add event details for attendees...">{{ old('description', $event->description ?? '') }}</textarea>
    </x-admin.field>
    <x-admin.field label="House Rules" for="event_house_rules" name="house_rules">
        <textarea name="house_rules" id="event_house_rules" class="form-control" rows="3">{{ old('house_rules', $event->house_rules ?? '') }}</textarea>
    </x-admin.field>
</x-admin.form-section>

{{-- Ticket types: fully dynamic, add as many as needed --}}
<div class="a-ev-block" id="ev-tickets">
    <div class="a-ev-section-head">
        <div>
            <h3>Ticket Types</h3>
            <p class="a-ev-block__desc">Add as many ticket types as you need, each with its own price, availability and limits.</p>
        </div>
        <button type="button" class="a-btn a-btn--outline a-btn--sm" id="add-ticket-row"><i class="fa fa-plus"></i> Add Ticket Type</button>
    </div>
    <div class="a-ev-rows" id="ticket-rows" data-next-index="{{ count($ticketRows) }}">
        @foreach($ticketRows as $index => $ticket)
            <div class="a-ev-row a-ev-row--ticket ticket-row" data-ticket-id="{{ $ticket['id'] ?? '' }}" data-sold="{{ $ticket['sold'] ?? 0 }}">
                @if(!empty($ticket['id']))<input type="hidden" name="tickets[{{ $index }}][id]" value="{{ $ticket['id'] }}">@endif
                <div class="a-field a-ev-span-2"><label class="form-label" for="tickets-{{ $index }}-name">Ticket Name</label><input class="form-control" id="tickets-{{ $index }}-name" name="tickets[{{ $index }}][name]" value="{{ $ticket['name'] ?? '' }}"></div>
                <div class="a-field"><label class="form-label" for="tickets-{{ $index }}-price">Price (EGP)</label><input type="number" step="0.01" min="0" class="form-control" id="tickets-{{ $index }}-price" name="tickets[{{ $index }}][price]" value="{{ $ticket['price'] ?? '' }}"></div>
                <div class="a-field"><label class="form-label" for="tickets-{{ $index }}-status">Status</label><select class="form-select" id="tickets-{{ $index }}-status" name="tickets[{{ $index }}][status]"><option value="active" @selected(($ticket['status'] ?? 'active') === 'active')>Active</option><option value="inactive" @selected(($ticket['status'] ?? '') === 'inactive')>Inactive</option><option value="sold_out" @selected(($ticket['status'] ?? '') === 'sold_out')>Sold Out</option><option value="hidden" @selected(($ticket['status'] ?? '') === 'hidden')>Hidden</option></select></div>
                <div class="a-field"><label class="form-label" for="tickets-{{ $index }}-color">Color</label><input type="color" class="form-control form-control-color w-100" id="tickets-{{ $index }}-color" name="tickets[{{ $index }}][color]" value="{{ $ticket['color'] ?? '#0d6efd' }}"></div>
                <div class="a-field"><label class="form-label" for="tickets-{{ $index }}-max">Max per order</label><input type="number" min="1" max="100" class="form-control" id="tickets-{{ $index }}-max" name="tickets[{{ $index }}][max_per_order]" value="{{ $ticket['max_per_order'] ?? 10 }}"></div>
                <div class="a-field a-ev-span-2"><label class="form-label" for="tickets-{{ $index }}-label">Label</label><input class="form-control" id="tickets-{{ $index }}-label" name="tickets[{{ $index }}][label]" value="{{ $ticket['label'] ?? '' }}"></div>
                <div class="a-field a-ev-span-3"><label class="form-label" for="tickets-{{ $index }}-description">Description</label><input class="form-control" id="tickets-{{ $index }}-description" name="tickets[{{ $index }}][description]" value="{{ $ticket['description'] ?? '' }}"></div>
                <div class="a-field">
                    <span class="form-label">Couple ticket</span>
                    <div class="form-check">
                        <input type="hidden" name="tickets[{{ $index }}][is_couple]" value="0">
                        <input class="form-check-input" type="checkbox" value="1" name="tickets[{{ $index }}][is_couple]" id="ticket-couple-{{ $index }}" @checked((bool) ($ticket['is_couple'] ?? false))>
                        <label class="form-check-label" for="ticket-couple-{{ $index }}">Couples only <span class="a-hint">(sold as 2, max 2 per order)</span></label>
                    </div>
                </div>
                <div class="a-ev-row__tools a-ev-span-6"><button type="button" class="a-btn a-btn--ghost a-btn--sm move-row" data-dir="-1" aria-label="Move ticket type up"><i class="fa fa-arrow-up"></i></button><button type="button" class="a-btn a-btn--ghost a-btn--sm move-row" data-dir="1" aria-label="Move ticket type down"><i class="fa fa-arrow-down"></i></button><button type="button" class="a-btn a-btn--danger a-btn--sm remove-row" aria-label="Remove ticket type"><i class="fa fa-trash"></i> Remove</button></div>
            </div>
        @endforeach
    </div>
    <div id="removed-tickets"></div>
</div>

<div class="a-ev-block" id="ev-fees">
    <div class="a-ev-section-head">
        <div>
            <h3>Event Fees</h3>
            <p class="a-ev-block__desc">Optional service fees added at checkout, as a percentage or a fixed amount.</p>
        </div>
        <button type="button" class="a-btn a-btn--outline a-btn--sm" id="add-fee-row"><i class="fa fa-plus"></i> Add Fee</button>
    </div>
    <div class="a-ev-rows" id="fee-rows" data-next-index="{{ count($feeRows) }}">
        @foreach($feeRows as $index => $fee)
            <div class="a-ev-row a-ev-row--fee fee-row">
                <div class="a-field"><label class="form-label" for="fees-{{ $index }}-name">Fee Name</label><input class="form-control" id="fees-{{ $index }}-name" name="fees[{{ $index }}][name]" value="{{ $fee['name'] ?? '' }}"></div>
                <div class="a-field"><label class="form-label" for="fees-{{ $index }}-type">Fee Type</label><select class="form-select" data-fee-type id="fees-{{ $index }}-type" name="fees[{{ $index }}][fee_type]"><option value="percentage" @selected(($fee['fee_type'] ?? 'percentage') === 'percentage')>Percentage (%)</option><option value="fixed" @selected(($fee['fee_type'] ?? '') === 'fixed')>Fixed Amount</option></select></div>
                <div class="a-field"><label class="form-label" for="fees-{{ $index }}-value">Value</label><input type="number" step="0.01" min="0" class="form-control" data-fee-value id="fees-{{ $index }}-value" name="fees[{{ $index }}][value]" value="{{ $fee['value'] ?? '' }}"></div>
                <div class="a-field"><label class="form-label" for="fees-{{ $index }}-description">Description</label><input class="form-control" id="fees-{{ $index }}-description" name="fees[{{ $index }}][description]" value="{{ $fee['description'] ?? '' }}"></div>
                <div class="a-ev-row__tools"><button type="button" class="a-btn a-btn--danger a-btn--sm remove-row" aria-label="Remove fee"><i class="fa fa-trash"></i> Remove</button></div>
            </div>
        @endforeach
    </div>
</div>

<div class="a-ev-block" id="ev-lineup">
    <div class="a-ev-section-head">
        <div>
            <h3>Lineup Artists</h3>
            <p class="a-ev-block__desc">Performers shown on the event page, ordered by the Order field.</p>
        </div>
        <button type="button" class="a-btn a-btn--outline a-btn--sm" id="add-lineup-row"><i class="fa fa-plus"></i> Add Artist</button>
    </div>
    <div class="a-ev-rows" id="lineup-rows" data-next-index="{{ count($lineupRows) }}">
        @foreach($lineupRows as $index => $lineup)
            <div class="a-ev-row a-ev-row--lineup lineup-row">
                <div class="a-field a-ev-span-3">
                    <label class="form-label" for="lineups-{{ $index }}-artist">Artist Name</label>
                    <input class="form-control" id="lineups-{{ $index }}-artist" name="lineups[{{ $index }}][artist_name]" value="{{ $lineup['artist_name'] ?? '' }}" placeholder="DJ / Artist name">
                </div>
                <div class="a-field a-ev-span-3">
                    <label class="form-label" for="lineups-{{ $index }}-instagram">Instagram Handle</label>
                    <div class="input-group">
                        <span class="input-group-text">@</span>
                        <input class="form-control" id="lineups-{{ $index }}-instagram" name="lineups[{{ $index }}][instagram]" value="{{ ltrim($lineup['instagram'] ?? '', '@') }}" placeholder="username">
                    </div>
                </div>
                <div class="a-field a-ev-span-2">
                    <label class="form-label" for="lineups-{{ $index }}-time">Performance Time</label>
                    <input class="form-control" id="lineups-{{ $index }}-time" name="lineups[{{ $index }}][performance_time]" value="{{ $lineup['performance_time'] ?? '' }}" placeholder="e.g. 10:00 PM">
                </div>
                <div class="a-field a-ev-span-2">
                    <label class="form-label" for="lineups-{{ $index }}-date">Performance Date</label>
                    <input type="text" class="form-control js-flatpickr" id="lineups-{{ $index }}-date" name="lineups[{{ $index }}][performance_date]" value="{{ $lineup['performance_date'] ?? '' }}" data-date-format="Y-m-d" placeholder="YYYY-MM-DD">
                </div>
                <div class="a-field">
                    <label class="form-label" for="lineups-{{ $index }}-order">Order</label>
                    <input type="number" min="0" class="form-control" id="lineups-{{ $index }}-order" name="lineups[{{ $index }}][sort_order]" value="{{ $lineup['sort_order'] ?? $index }}">
                </div>
                <div class="a-field a-ev-span-6">
                    <label class="form-label" for="lineup-image-{{ $index }}">Artist Image</label>
                    <input type="hidden" name="lineups[{{ $index }}][existing_image]" value="{{ $lineup['image'] ?? '' }}">
                    <input type="file" class="form-control lineup-image-input" id="lineup-image-{{ $index }}" name="lineup_images[{{ $index }}]" accept="image/*" data-preview-id="lineup-preview-{{ $index }}">
                    @if(!empty($lineup['_existing_image_url']))
                        <img id="lineup-preview-{{ $index }}" src="{{ $lineup['_existing_image_url'] }}" alt="Artist image" class="a-ev-lineup-img">
                    @else
                        <img id="lineup-preview-{{ $index }}" src="" alt="" class="a-ev-lineup-img d-none">
                    @endif
                </div>
                <div class="a-ev-row__tools a-ev-span-6">
                    <button type="button" class="a-btn a-btn--danger a-btn--sm remove-lineup-row"><i class="fa fa-trash"></i> Remove</button>
                </div>
            </div>
        @endforeach
    </div>
</div>

<template id="lineup-row-template">
    <div class="a-ev-row a-ev-row--lineup lineup-row">
        <div class="a-field a-ev-span-3">
            <label class="form-label" for="lineup-__LINEUPINDEX__-artist">Artist Name</label>
            <input class="form-control" id="lineup-__LINEUPINDEX__-artist" name="__LINEUPNAME__[artist_name]" placeholder="DJ / Artist name">
        </div>
        <div class="a-field a-ev-span-3">
            <label class="form-label" for="lineup-__LINEUPINDEX__-instagram">Instagram Handle</label>
            <div class="input-group">
                <span class="input-group-text">@</span>
                <input class="form-control" id="lineup-__LINEUPINDEX__-instagram" name="__LINEUPNAME__[instagram]" placeholder="username">
            </div>
        </div>
        <div class="a-field a-ev-span-2">
            <label class="form-label" for="lineup-__LINEUPINDEX__-time">Performance Time</label>
            <input class="form-control" id="lineup-__LINEUPINDEX__-time" name="__LINEUPNAME__[performance_time]" placeholder="e.g. 10:00 PM">
        </div>
        <div class="a-field a-ev-span-2">
            <label class="form-label" for="lineup-__LINEUPINDEX__-date">Performance Date</label>
            <input type="text" class="form-control js-flatpickr" id="lineup-__LINEUPINDEX__-date" name="__LINEUPNAME__[performance_date]" data-date-format="Y-m-d" placeholder="YYYY-MM-DD">
        </div>
        <div class="a-field">
            <label class="form-label" for="lineup-__LINEUPINDEX__-order">Order</label>
            <input type="number" min="0" class="form-control" id="lineup-__LINEUPINDEX__-order" name="__LINEUPNAME__[sort_order]" value="__LINEUPINDEX__">
        </div>
        <div class="a-field a-ev-span-6">
            <label class="form-label" for="lineup-image-__LINEUPINDEX__">Artist Image</label>
            <input type="file" class="form-control lineup-image-input" id="lineup-image-__LINEUPINDEX__" name="lineup_images[__LINEUPINDEX__]" accept="image/*" data-preview-id="lineup-preview-__LINEUPINDEX__">
            <img id="lineup-preview-__LINEUPINDEX__" src="" alt="" class="a-ev-lineup-img d-none">
        </div>
        <div class="a-ev-row__tools a-ev-span-6">
            <button type="button" class="a-btn a-btn--danger a-btn--sm remove-lineup-row"><i class="fa fa-trash"></i> Remove</button>
        </div>
    </div>
</template>

<template id="ticket-row-template">
    <div class="a-ev-row a-ev-row--ticket ticket-row" data-ticket-id="" data-sold="0">
        <div class="a-field a-ev-span-2"><label class="form-label" for="__ID__-name">Ticket Name</label><input class="form-control" id="__ID__-name" name="__NAME__[name]"></div>
        <div class="a-field"><label class="form-label" for="__ID__-price">Price (EGP)</label><input type="number" step="0.01" min="0" class="form-control" id="__ID__-price" name="__NAME__[price]"></div>
        <div class="a-field"><label class="form-label" for="__ID__-status">Status</label><select class="form-select" id="__ID__-status" name="__NAME__[status]"><option value="active">Active</option><option value="inactive">Inactive</option><option value="sold_out">Sold Out</option><option value="hidden">Hidden</option></select></div>
        <div class="a-field"><label class="form-label" for="__ID__-color">Color</label><input type="color" class="form-control form-control-color w-100" id="__ID__-color" name="__NAME__[color]" value="#0d6efd"></div>
        <div class="a-field"><label class="form-label" for="__ID__-max">Max per order</label><input type="number" min="1" max="100" class="form-control" id="__ID__-max" name="__NAME__[max_per_order]" value="10"></div>
        <div class="a-field a-ev-span-2"><label class="form-label" for="__ID__-label">Label</label><input class="form-control" id="__ID__-label" name="__NAME__[label]"></div>
        <div class="a-field a-ev-span-3"><label class="form-label" for="__ID__-description">Description</label><input class="form-control" id="__ID__-description" name="__NAME__[description]"></div>
        <div class="a-field">
            <span class="form-label">Couple ticket</span>
            <div class="form-check">
                <input type="hidden" name="__NAME__[is_couple]" value="0">
                <input class="form-check-input" type="checkbox" value="1" name="__NAME__[is_couple]" id="__ID__">
                <label class="form-check-label" for="__ID__">Couples only <span class="a-hint">(sold as 2, max 2 per order)</span></label>
            </div>
        </div>
        <div class="a-ev-row__tools a-ev-span-6"><button type="button" class="a-btn a-btn--ghost a-btn--sm move-row" data-dir="-1" aria-label="Move ticket type up"><i class="fa fa-arrow-up"></i></button><button type="button" class="a-btn a-btn--ghost a-btn--sm move-row" data-dir="1" aria-label="Move ticket type down"><i class="fa fa-arrow-down"></i></button><button type="button" class="a-btn a-btn--danger a-btn--sm remove-row" aria-label="Remove ticket type"><i class="fa fa-trash"></i> Remove</button></div>
    </div>
</template>

<template id="fee-row-template">
    <div class="a-ev-row a-ev-row--fee fee-row">
        <div class="a-field"><label class="form-label" for="__ID__-name">Fee Name</label><input class="form-control" id="__ID__-name" name="__NAME__[name]"></div>
        <div class="a-field"><label class="form-label" for="__ID__-type">Fee Type</label><select class="form-select" data-fee-type id="__ID__-type" name="__NAME__[fee_type]"><option value="percentage">Percentage (%)</option><option value="fixed">Fixed Amount</option></select></div>
        <div class="a-field"><label class="form-label" for="__ID__-value">Value</label><input type="number" step="0.01" min="0" class="form-control" data-fee-value id="__ID__-value" name="__NAME__[value]"></div>
        <div class="a-field"><label class="form-label" for="__ID__-description">Description</label><input class="form-control" id="__ID__-description" name="__NAME__[description]"></div>
        <div class="a-ev-row__tools"><button type="button" class="a-btn a-btn--danger a-btn--sm remove-row" aria-label="Remove fee"><i class="fa fa-trash"></i> Remove</button></div>
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
            let html = template.innerHTML.replaceAll('__NAME__', `${prefix}[${nextIndex}]`);
            html = html.replaceAll('__ID__', `${prefix}-${nextIndex}-couple`);
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

            const row = btn.closest('.ticket-row, .fee-row');
            const existingId = row?.dataset?.ticketId;
            if (existingId) {
                // an existing ticket type is only removed because the user said so (explicit marker), never because its row is missing
                const sold = Number(row.dataset.sold || 0);
                if (sold > 0 && !confirm('This ticket type already has ' + sold + ' sale(s). It will be hidden from customers, not deleted; existing orders and tickets stay as they are. Continue?')) return;
                const marker = document.createElement('input');
                marker.type = 'hidden'; marker.name = 'removed_ticket_ids[]'; marker.value = existingId;
                document.getElementById('removed-tickets')?.appendChild(marker);
            }
            row?.remove();
            renumber();
        });

        document.getElementById('add-lineup-row')?.addEventListener('click', () => {
            const container = document.getElementById('lineup-rows');
            const template = document.getElementById('lineup-row-template');
            if (!container || !template) return;

            const nextIndex = Number(container.dataset.nextIndex || 0);
            let html = template.innerHTML
                .replaceAll('__LINEUPNAME__', `lineups[${nextIndex}]`)
                .replaceAll('__LINEUPINDEX__', String(nextIndex));
            container.insertAdjacentHTML('beforeend', html);
            container.dataset.nextIndex = String(nextIndex + 1);
            initEventDatePicker(); // the new row's date field gets the same picker
            renumber();
        });

        document.addEventListener('click', (event) => {
            const btn = event.target.closest('.remove-lineup-row');
            if (!btn) return;
            btn.closest('.lineup-row')?.remove();
            renumber();
        });

        document.addEventListener('change', (event) => {
            const input = event.target.closest('.lineup-image-input');
            if (!input) return;
            const previewId = input.dataset.previewId;
            const preview = previewId ? document.getElementById(previewId) : null;
            if (!preview) return;
            const file = input.files?.[0];
            if (file) {
                preview.src = URL.createObjectURL(file);
                preview.classList.remove('d-none');
            }
        });

        const coverInput = document.getElementById('cover_image');
        const coverPreview = document.getElementById('cover-image-preview');
        const coverPlaceholder = document.getElementById('cover-image-placeholder');

        const bindImagePreview = (input, preview, placeholder) => {
            input?.addEventListener('change', (event) => {
                const file = event.target.files?.[0];

                if (!file) {
                    const hasExistingImage = preview?.dataset.hasExisting === '1';
                    if (!hasExistingImage) {
                        preview?.classList.add('d-none');
                        placeholder?.classList.remove('d-none');
                    }
                    return;
                }

                const objectUrl = URL.createObjectURL(file);
                if (preview) {
                    preview.src = objectUrl;
                    preview.classList.remove('d-none');
                    preview.dataset.hasExisting = '1';
                }
                placeholder?.classList.add('d-none');
            });
        };

        bindImagePreview(coverInput, coverPreview, coverPlaceholder);

        const eventBannerInput = document.getElementById('event_banner');
        const eventBannerPreview = document.getElementById('event-banner-preview');
        const eventBannerPlaceholder = document.getElementById('event-banner-placeholder');
        bindImagePreview(eventBannerInput, eventBannerPreview, eventBannerPlaceholder);

        const venueMapInput = document.getElementById('venue_map');
        const venueMapPreview = document.getElementById('venue-map-preview');
        const venueMapPlaceholder = document.getElementById('venue-map-placeholder');
        bindImagePreview(venueMapInput, venueMapPreview, venueMapPlaceholder);


        const nameInput = document.getElementById('event_name');
        const slugInput = document.getElementById('event_slug');

        const slugify = (value) => value
            .toString()
            .toLowerCase()
            .trim()
            .replace(/[^a-z0-9\s-]/g, '')
            .replace(/\s+/g, '-')
            .replace(/-+/g, '-');

        let slugManuallyEdited = false;

        slugInput?.addEventListener('input', () => {
            slugManuallyEdited = slugInput.value.trim().length > 0;
            slugInput.value = slugify(slugInput.value);
        });

        nameInput?.addEventListener('input', () => {
            if (!slugInput || slugManuallyEdited) {
                return;
            }

            slugInput.value = slugify(nameInput.value);
        });

        const initEventDatePicker = () => {
            if (typeof flatpickr === 'undefined') {
                return;
            }

            const dateInputs = document.querySelectorAll('.js-flatpickr');
            dateInputs.forEach((input) => {
                if (input._flatpickr) {
                    return;
                }

                flatpickr(input, {
                    dateFormat: input.dataset.dateFormat || 'Y-m-d',
                    allowInput: true,
                    clickOpens: true,
                });
            });
        };

        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', initEventDatePicker);
        } else {
            initEventDatePicker();
        }


        /* ---- helpers added for usability ---- */
        const MAX_IMAGE_BYTES = 2 * 1024 * 1024; // matches the server rule (2 MB)

        // "Ticket 1", "Fee 2"... headings and an empty state, kept in sync when rows are added or removed
        function renumber() {
            [['#ticket-rows', '.ticket-row', 'Ticket'], ['#fee-rows', '.fee-row', 'Fee'], ['#lineup-rows', '.lineup-row', 'Artist']].forEach(([box, row, word]) => {
                const container = document.querySelector(box);
                if (!container) return;
                const rows = container.querySelectorAll(row);
                rows.forEach((el, i) => {
                    let h = el.querySelector(':scope > .a-ev-row__title');
                    if (!h) { h = document.createElement('div'); h.className = 'a-ev-row__title a-ev-span-6'; el.prepend(h); }
                    const sold = Number(el.dataset.sold || 0);
                    h.textContent = word + ' ' + (i + 1) + (sold ? ' · ' + sold + ' sold' : '');
                });
                let empty = container.parentElement.querySelector('.a-ev-empty');
                if (!rows.length && !empty) {
                    empty = document.createElement('p'); empty.className = 'a-ev-empty a-hint';
                    empty.textContent = box === '#lineup-rows' ? 'No artists yet — add the performers people will see on the event page.' : box === '#fee-rows' ? 'No fees: customers pay the ticket price only.' : 'No ticket types yet — an event needs at least one to sell tickets.';
                    container.after(empty);
                } else if (rows.length && empty) { empty.remove(); }
            });
        }
        renumber();

        // Reorder ticket types (position is sent explicitly so the order never depends on field indexes)
        document.addEventListener('click', (e) => {
            const btn = e.target.closest('.move-row');
            if (!btn) return;
            const row = btn.closest('.ticket-row'), dir = Number(btn.dataset.dir);
            const sib = dir < 0 ? row.previousElementSibling : row.nextElementSibling;
            if (!sib) return;
            dir < 0 ? sib.before(row) : sib.after(row);
            renumber();
        });
        document.querySelector('#ticket-rows')?.closest('form')?.addEventListener('submit', () => {
            document.querySelectorAll('#ticket-rows .ticket-row').forEach((row, i) => {
                const field = row.querySelector('input[name^="tickets["]'); const m = field && field.name.match(/^tickets\[(\d+)\]/);
                if (!m) return;
                let pos = row.querySelector('input[data-position]');
                if (!pos) { pos = document.createElement('input'); pos.type = 'hidden'; pos.dataset.position = '1'; pos.name = 'tickets[' + m[1] + '][position]'; row.appendChild(pos); }
                pos.value = i;
            });
        });

        // Slug preview (name -> slug auto-fill already exists above; this only shows the resulting address)
        const slugPreview = document.getElementById('slug-preview');
        const showSlug = () => { if (slugPreview && slugInput) { const v = slugInput.value.trim(); slugPreview.textContent = v ? 'Page address: /events/' + v : 'Page address is created from the event name.'; } };
        nameInput?.addEventListener('input', () => setTimeout(showSlug, 0)); slugInput?.addEventListener('input', showSlug); showSlug();

        // End date can never be before the start date
        const startDate = document.getElementById('event_date'), endDate = document.getElementById('end_date');
        const syncEnd = () => { if (endDate?._flatpickr) { endDate._flatpickr.set('minDate', startDate.value || null); } };
        startDate?.addEventListener('change', syncEnd);
        window.addEventListener('load', () => setTimeout(syncEnd, 0));

        // Fee value unit follows the fee type
        document.addEventListener('change', (e) => { if (e.target.matches('[data-fee-type]')) unit(e.target.closest('.fee-row')); });
        function unit(row) {
            if (!row) return;
            const t = row.querySelector('[data-fee-type]')?.value, v = row.querySelector('[data-fee-value]');
            if (v) { v.placeholder = t === 'percentage' ? 'e.g. 5 (%)' : 'e.g. 20 (EGP)'; if (t === 'percentage') v.max = 100; else v.removeAttribute('max'); }
        }
        document.querySelectorAll('.fee-row').forEach(unit);
        document.getElementById('add-fee-row')?.addEventListener('click', () => unit([...document.querySelectorAll('.fee-row')].pop()));

        // Tell about oversized images immediately instead of after the upload
        document.addEventListener('change', (e) => {
            const input = e.target;
            if (!(input instanceof HTMLInputElement) || input.type !== 'file' || !input.files?.length) return;
            const big = [...input.files].filter((f) => f.size > MAX_IMAGE_BYTES);
            let note = input.parentElement.querySelector('.a-ev-filewarn');
            if (big.length) {
                if (!note) { note = document.createElement('div'); note.className = 'a-error a-ev-filewarn'; note.setAttribute('role', 'alert'); input.after(note); }
                note.textContent = big.map((f) => f.name).join(', ') + ' is larger than 2 MB and will be rejected. Choose a smaller image.';
                input.value = '';
            } else if (note) { note.remove(); }
        });

        window.addEventListener('load', initEventDatePicker);

    })();
</script>
