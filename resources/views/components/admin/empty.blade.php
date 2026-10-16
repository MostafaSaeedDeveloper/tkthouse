@props(['title', 'text' => null, 'icon' => 'fa-inbox'])
<div {{ $attributes->class(['a-empty']) }}>
    <div class="a-empty__icon" aria-hidden="true"><i class="fa {{ $icon }}"></i></div>
    <div class="a-empty__title">{{ $title }}</div>
    @if($text)<p class="a-empty__text">{{ $text }}</p>@endif
    @if(! $slot->isEmpty())<div class="a-empty__action">{{ $slot }}</div>@endif
</div>
