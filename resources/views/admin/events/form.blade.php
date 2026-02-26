<div class="row">
<div class="col-md-6 mb-3"><label class="form-label">Event Name</label><input name="name" class="form-control" value="{{ old('name',$event->name ?? '') }}" required></div>
<div class="col-md-3 mb-3"><label class="form-label">Date</label><input type="date" name="event_date" class="form-control" value="{{ old('event_date', isset($event) ? $event->event_date?->format('Y-m-d') : '') }}" required></div>
<div class="col-md-3 mb-3"><label class="form-label">Time</label><input type="time" name="event_time" class="form-control" value="{{ old('event_time',$event->event_time ?? '') }}" required></div>
<div class="col-md-6 mb-3"><label class="form-label">Location</label><input name="location" class="form-control" value="{{ old('location',$event->location ?? '') }}" required></div>
<div class="col-md-6 mb-3"><label class="form-label">Map URL (optional)</label><input name="map_url" class="form-control" value="{{ old('map_url',$event->map_url ?? '') }}"></div>
<div class="col-md-4 mb-3"><label class="form-label">Status</label><select name="status" class="form-select"><option value="active" @selected(old('status',$event->status ?? 'active')==='active')>active</option><option value="inactive" @selected(old('status',$event->status ?? '')==='inactive')>inactive</option><option value="draft" @selected(old('status',$event->status ?? '')==='draft')>draft</option></select></div>
<div class="col-md-8 mb-3"><label class="form-label">Cover Image</label><input type="file" name="cover_image" class="form-control" {{ isset($event) ? '' : 'required' }}></div>
<div class="col-12 mb-3"><label class="form-label">Description</label><textarea name="description" class="form-control" rows="4" required>{{ old('description',$event->description ?? '') }}</textarea></div>
<div class="col-12 mb-3"><label class="form-label">House Rules</label><textarea name="house_rules" class="form-control" rows="3">{{ old('house_rules',$event->house_rules ?? '') }}</textarea></div>
<div class="col-12 mb-3"><label class="form-label">Gallery Images (optional multiple)</label><input type="file" name="gallery_images[]" class="form-control" multiple></div>
@if(isset($event) && $event->images->count())<div class="col-12 mb-3"><div class="small text-muted">Current Images: {{ $event->images->count() }}</div><div class="form-check"><input class="form-check-input" type="checkbox" value="1" name="replace_gallery" id="replace_gallery"><label class="form-check-label" for="replace_gallery">Replace gallery images</label></div></div>@endif
</div>

<h4 class="h5 mt-3">Ticket Types</h4>
@for($i = 0; $i < 3; $i++)
<div class="row border rounded p-3 mb-2">
<div class="col-md-3 mb-2"><input class="form-control" name="tickets[{{ $i }}][name]" placeholder="Ticket name" value="{{ old('tickets.'.$i.'.name', $event->tickets[$i]->name ?? '') }}"></div>
<div class="col-md-2 mb-2"><input type="number" step="0.01" class="form-control" name="tickets[{{ $i }}][price]" placeholder="Price" value="{{ old('tickets.'.$i.'.price', $event->tickets[$i]->price ?? '') }}"></div>
<div class="col-md-2 mb-2"><select class="form-select" name="tickets[{{ $i }}][status]"><option value="active">active</option><option value="inactive">inactive</option><option value="sold_out">sold_out</option></select></div>
<div class="col-md-2 mb-2"><input class="form-control" name="tickets[{{ $i }}][label]" placeholder="Label" value="{{ old('tickets.'.$i.'.label', $event->tickets[$i]->label ?? '') }}"></div>
<div class="col-md-3 mb-2"><input class="form-control" name="tickets[{{ $i }}][description]" placeholder="Description" value="{{ old('tickets.'.$i.'.description', $event->tickets[$i]->description ?? '') }}"></div>
</div>
@endfor

<h4 class="h5 mt-3">Event Fees</h4>
@for($i = 0; $i < 3; $i++)
<div class="row border rounded p-3 mb-2">
<div class="col-md-3 mb-2"><input class="form-control" name="fees[{{ $i }}][name]" placeholder="Fee name" value="{{ old('fees.'.$i.'.name', $event->fees[$i]->name ?? '') }}"></div>
<div class="col-md-3 mb-2"><select class="form-select" name="fees[{{ $i }}][fee_type]"><option value="percentage">percentage</option><option value="fixed">fixed</option></select></div>
<div class="col-md-2 mb-2"><input type="number" step="0.01" class="form-control" name="fees[{{ $i }}][value]" placeholder="Value" value="{{ old('fees.'.$i.'.value', $event->fees[$i]->value ?? '') }}"></div>
<div class="col-md-4 mb-2"><input class="form-control" name="fees[{{ $i }}][description]" placeholder="Description" value="{{ old('fees.'.$i.'.description', $event->fees[$i]->description ?? '') }}"></div>
</div>
@endfor
