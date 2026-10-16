{{-- <x-admin.button variant="primary|secondary|danger|dark|ghost|success" size="sm|md|lg" href="…" icon="plus" loading> Label </x-admin.button>
     Renders <a> when href is given, otherwise <button type="…">. Icon-only buttons need aria-label. --}}
@props(['variant' => 'secondary', 'size' => 'md', 'href' => null, 'icon' => null, 'type' => 'button', 'loading' => false])
@php
    $v = ['primary' => 'primary', 'secondary' => 'outline', 'outline' => 'outline', 'danger' => 'danger', 'dark' => 'dark', 'ghost' => 'ghost', 'success' => 'success'][$variant] ?? 'outline';
    $classes = ['a-btn', 'a-btn--'.$v, 'a-btn--sm' => $size === 'sm', 'a-btn--lg' => $size === 'lg'];
@endphp
@if($href)
    <a href="{{ $href }}" {{ $attributes->class($classes) }}>@if($icon)<i class="fa fa-{{ $icon }}" aria-hidden="true"></i>@endif{{ $slot }}</a>
@else
    <button type="{{ $type }}" {{ $attributes->class($classes) }} @if($loading) data-a-loading @endif>@if($icon)<i class="fa fa-{{ $icon }}" aria-hidden="true"></i>@endif{{ $slot }}</button>
@endif
