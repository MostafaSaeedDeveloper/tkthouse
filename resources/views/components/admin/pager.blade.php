@props(['paginator'])
@if($paginator->hasPages())
    <div class="a-pager">
        <span>Showing {{ $paginator->firstItem() }}–{{ $paginator->lastItem() }} of {{ $paginator->total() }}</span>
        {{ $paginator->withQueryString()->links('pagination::admin') }}
    </div>
@endif
