@csrf
<div class="row">
    <div class="col-md-6">
        <label class="form-label">Name</label>
        <input type="text" name="name" value="{{ old('name', $ticket->name ?? '') }}" class="form-control">
    </div>
    <div class="col-md-6">
        <label class="form-label">Price</label>
        <input type="number" step="0.01" name="price" value="{{ old('price', $ticket->price ?? 0) }}" class="form-control">
    </div>
    <div class="col-md-12 mt-3">
        <label class="form-label">Description</label>
        <textarea name="description" class="form-control">{{ old('description', $ticket->description ?? '') }}</textarea>
    </div>
    <div class="col-md-6 mt-3">
        <label class="form-label">Status</label>
        <select name="status" class="form-select">
            @foreach(['active','inactive','sold_out'] as $status)
                <option value="{{ $status }}" @selected(old('status', $ticket->status ?? 'active') === $status)>{{ ucfirst($status) }}</option>
            @endforeach
        </select>
    </div>
</div>
<div class="mt-3">
    <button class="btn btn-primary" type="submit">Save</button>
</div>
