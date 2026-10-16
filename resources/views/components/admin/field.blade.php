{{-- Labelled field wrapper: <x-admin.field label="Name" name="name" hint="…"><input class="form-control" …></x-admin.field> --}}
@props(['label', 'name' => null, 'hint' => null, 'for' => null, 'required' => false])
<div {{ $attributes->class(['a-field']) }}>
    <label class="form-label" @if($for ?? $name) for="{{ $for ?? $name }}" @endif>{{ $label }}@if($required)<span class="a-muted" aria-hidden="true"> *</span>@endif</label>
    {{ $slot }}
    @if($hint)<div class="a-hint">{{ $hint }}</div>@endif
    @if($name)@error($name)<div class="a-error" role="alert">{{ $message }}</div>@enderror @endif
</div>
