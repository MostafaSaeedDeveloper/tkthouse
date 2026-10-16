{{-- <x-admin.modal id="exportModal" title="Export" size="lg"> body … <x-slot:footer> buttons </x-slot:footer> </x-admin.modal> — Bootstrap modal markup (JS from the bundle), styled by the design system --}}
@props(['id', 'title', 'size' => null])
<div class="modal fade" id="{{ $id }}" tabindex="-1" aria-labelledby="{{ $id }}Title" aria-hidden="true">
    <div class="modal-dialog {{ $size ? 'modal-'.$size : '' }} modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title" id="{{ $id }}Title">{{ $title }}</h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">{{ $slot }}</div>
            @isset($footer)<div class="modal-footer">{{ $footer }}</div>@endisset
        </div>
    </div>
</div>
