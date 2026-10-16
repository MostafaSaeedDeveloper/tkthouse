@props(['action', 'reset' => null])
<form method="GET" action="{{ $action }}" {{ $attributes->class(['a-filters']) }} role="search">
    {{ $slot }}
    <div class="a-filters__actions">
        <button type="submit" class="a-btn a-btn--dark">Apply</button>
        @if($reset)<a class="a-btn a-btn--ghost" href="{{ $reset }}">Reset</a>@endif
    </div>
</form>
