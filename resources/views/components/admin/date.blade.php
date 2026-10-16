{{-- <x-admin.date label="From" name="from" :value="request('from')" /> — flatpickr-enabled (class js-flatpickr is initialised in the layout) --}}
@props(['label', 'name', 'value' => null, 'hint' => null, 'required' => false, 'id' => null])
@php $id = $id ?? $name; @endphp
<x-admin.field :label="$label" :name="$name" :for="$id" :hint="$hint" :required="$required">
    <input type="text" id="{{ $id }}" name="{{ $name }}" value="{{ old($name, $value) }}" placeholder="YYYY-MM-DD" autocomplete="off" {{ $attributes->class(['form-control', 'js-flatpickr', 'is-invalid' => $errors->has($name)]) }}>
</x-admin.field>
