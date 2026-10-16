{{-- <x-admin.search name="q" :value="request('q')" placeholder="Order # / customer" label="Search" /> — use inside <x-admin.filters> --}}
@props(['name' => 'q', 'value' => null, 'placeholder' => 'Search…', 'label' => 'Search'])
<div class="a-field a-field--search">
    <label class="form-label" for="{{ $name }}">{{ $label }}</label>
    <div class="a-search-input"><i class="fa fa-magnifying-glass" aria-hidden="true"></i><input type="search" class="form-control" id="{{ $name }}" name="{{ $name }}" value="{{ $value }}" placeholder="{{ $placeholder }}" autocomplete="off"></div>
</div>
