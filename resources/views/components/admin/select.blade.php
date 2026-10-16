{{-- <x-admin.select label="Status" name="status" :options="['paid' => 'Paid']" :value="request('status')" placeholder="All" /> --}}
@props(['label', 'name', 'options' => [], 'value' => null, 'placeholder' => null, 'hint' => null, 'required' => false, 'id' => null])
@php $id = $id ?? $name; $current = (string) old($name, $value); @endphp
<x-admin.field :label="$label" :name="$name" :for="$id" :hint="$hint" :required="$required">
    <select id="{{ $id }}" name="{{ $name }}" {{ $attributes->class(['form-select', 'is-invalid' => $errors->has($name)]) }} @if($required) required @endif>
        @if($placeholder !== null)<option value="">{{ $placeholder }}</option>@endif
        @foreach($options as $k => $label2)<option value="{{ $k }}" @selected((string) $k === $current)>{{ $label2 }}</option>@endforeach
        {{ $slot }}
    </select>
</x-admin.field>
