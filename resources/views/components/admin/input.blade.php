{{-- <x-admin.input label="Name" name="name" :value="$x" type="text" hint="…" required /> — label + control + error in one --}}
@props(['label', 'name', 'type' => 'text', 'value' => null, 'hint' => null, 'required' => false, 'id' => null])
@php $id = $id ?? $name; @endphp
<x-admin.field :label="$label" :name="$name" :for="$id" :hint="$hint" :required="$required">
    <input type="{{ $type }}" id="{{ $id }}" name="{{ $name }}" value="{{ old($name, $value) }}" {{ $attributes->class(['form-control', 'is-invalid' => $errors->has($name)]) }} @if($required) required @endif>
</x-admin.field>
