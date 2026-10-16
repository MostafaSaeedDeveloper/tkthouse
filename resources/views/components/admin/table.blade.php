{{-- Responsive table wrapper; pass stack="true" to turn rows into labelled blocks below 768px (add data-label to each td). --}}
@props(['stack' => false, 'compact' => false, 'caption' => null])
<div class="a-table-wrap" tabindex="0" role="region" @if($caption) aria-label="{{ $caption }}" @endif>
    <table {{ $attributes->class(['a-table', 'a-table--stack' => $stack, 'a-table--compact' => $compact]) }}>
        @if($caption)<caption class="a-sr-only">{{ $caption }}</caption>@endif
        {{ $slot }}
    </table>
</div>
