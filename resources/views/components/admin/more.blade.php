{{-- Several values in one cell: shows the first plainly and the rest behind "+N more" (accessible <details> popover, no JS).
     <x-admin.more :items="$order->items->pluck('ticket_name')->unique()->values()" /> --}}
@props(['items' => []])
@php $items = collect($items)->filter(fn ($v) => (string) $v !== '')->values(); @endphp
@if($items->isEmpty())
    <span class="a-muted">—</span>
@else
    <span class="a-more-wrap" style="display:inline-flex;align-items:center;max-width:100%;min-width:0">
        <span class="a-trunc" title="{{ $items->first() }}" style="max-width:200px">{{ $items->first() }}</span>
        @if($items->count() > 1)
            <details class="a-more">
                <summary aria-label="Show {{ $items->count() - 1 }} more">+{{ $items->count() - 1 }} more</summary>
                <div class="a-more__pop"><ul>@foreach($items as $v)<li>{{ $v }}</li>@endforeach</ul></div>
            </details>
        @endif
    </span>
@endif
