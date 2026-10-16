{{-- Row / toolbar actions menu. <x-admin.dropdown label="Actions"> <a class="a-menu__item" href="…">View</a> <form …><button class="a-menu__item a-menu__item--danger">Delete</button></form> </x-admin.dropdown>
     Behaviour (open/close, Esc, outside click) comes from admin.js ([data-a-menu-toggle]). icon-only when label is empty. --}}
@props(['label' => 'Actions', 'icon' => 'ellipsis', 'align' => 'right'])
<div class="a-menu" {{ $attributes }}>
    <button type="button" class="a-btn a-btn--outline a-btn--sm {{ $label === '' ? 'a-btn--icon' : '' }}" data-a-menu-toggle aria-haspopup="true" aria-expanded="false" @if($label === '') aria-label="Actions" @endif>
        @if($icon)<i class="fa fa-{{ $icon }}" aria-hidden="true"></i>@endif{{ $label }}
    </button>
    <div class="a-menu__panel" role="menu" @if($align === 'left') style="right:auto;left:0" @endif>{{ $slot }}</div>
</div>
