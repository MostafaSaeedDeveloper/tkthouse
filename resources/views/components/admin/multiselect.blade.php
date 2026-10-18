@props(['name', 'options' => [], 'selected' => [], 'placeholder' => 'Select…', 'emptyText' => null, 'label' => null, 'id' => null])
@php
    // $options: [value => label]. A real <select multiple> is always rendered (it is what the form submits and what shows without JavaScript);
    // admin-v2/multiselect.js turns it into chips + a searchable list.
    $id = $id ?: 'ms-'.substr(md5($name), 0, 6);
    $selected = array_map('strval', (array) $selected);
@endphp
<div class="a-msel" data-a-msel data-placeholder="{{ $placeholder }}" data-empty-text="{{ $emptyText }}">
    <select multiple name="{{ $name }}[]" id="{{ $id }}" class="a-msel__native no-custom-select" @if($label) aria-label="{{ $label }}" @endif size="6">
        @foreach($options as $value => $text)
            <option value="{{ $value }}" @selected(in_array((string) $value, $selected, true))>{{ $text }}</option>
        @endforeach
    </select>
</div>
