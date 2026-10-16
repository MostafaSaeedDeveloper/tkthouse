@props(['title', 'text' => null, 'icon' => 'ticket'])
<div {{ $attributes->class(['tkt-empty']) }}>
    <div class="tkt-empty__icon">
        @if($icon === 'search')
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m21 21-4.3-4.3"/></svg>
        @elseif($icon === 'clock')
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/></svg>
        @else
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 9a2 2 0 0 0 0 6v3a1 1 0 0 0 1 1h16a1 1 0 0 0 1-1v-3a2 2 0 0 1 0-6V6a1 1 0 0 0-1-1H4a1 1 0 0 0-1 1z"/><path d="M13 5v2M13 17v2M13 11v2"/></svg>
        @endif
    </div>
    <h3 class="tkt-empty__title">{{ $title }}</h3>
    @if($text)<p class="tkt-empty__text">{{ $text }}</p>@endif
    @if(! $slot->isEmpty())<div class="tkt-empty__action">{{ $slot }}</div>@endif
</div>
