@props(['action', 'reset' => null])
@php
    // Phones show the filters behind a "Filters" button; the badge says how many are applied. Open by default when the form has errors.
    $activeFilters = collect(request()->except(['page', 'per_page', 'sort', 'dir', 'direction', '_token']))->filter(fn ($v) => is_array($v) ? count(array_filter($v, 'filled')) > 0 : filled($v))->count();
    $filtersId = 'af-'.substr(md5($action), 0, 6);
@endphp
<form method="GET" action="{{ $action }}" {{ $attributes->class(['a-filters', 'is-open' => $errors->any()]) }} role="search" data-a-filters>
    <button type="button" class="a-filters__toggle" aria-expanded="{{ $errors->any() ? 'true' : 'false' }}" aria-controls="{{ $filtersId }}">
        <span><i class="fa fa-sliders" aria-hidden="true"></i> Filters @if($activeFilters)<b class="a-filters__count" aria-label="{{ $activeFilters }} applied">{{ $activeFilters }}</b>@endif</span>
        <i class="fa fa-chevron-down a-filters__chev" aria-hidden="true"></i>
    </button>
    <div class="a-filters__body" id="{{ $filtersId }}">
        {{ $slot }}
        <div class="a-filters__actions">
            <button type="submit" class="a-btn a-btn--dark">Apply</button>
            @if($reset)<a class="a-btn a-btn--ghost" href="{{ $reset }}">Reset</a>@endif
        </div>
    </div>
</form>
