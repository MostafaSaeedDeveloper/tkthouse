@props(['title', 'sub' => null, 'href' => null, 'linkLabel' => 'See all'])
<div class="tkt-section-head">
    <div>
        <h2 class="tkt-section-head__title">{{ $title }}</h2>
        @if($sub)<p class="tkt-section-head__sub">{{ $sub }}</p>@endif
    </div>
    @if($href)
        <a class="tkt-link" href="{{ $href }}">{{ $linkLabel }}
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6"/></svg>
        </a>
    @endif
</div>
