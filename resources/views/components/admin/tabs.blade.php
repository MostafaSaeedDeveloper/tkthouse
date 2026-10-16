{{-- <x-admin.tabs :items="['Overview' => route(...), 'Settings' => route(...)]" :active="'Overview'" /> — link tabs (server-side state) --}}
@props(['items' => [], 'active' => null])
<nav class="a-tabs" aria-label="Sections">
    @foreach($items as $label => $url)<a href="{{ $url }}" @class(['is-active' => $label === $active]) @if($label === $active) aria-current="page" @endif>{{ $label }}</a>@endforeach
</nav>
