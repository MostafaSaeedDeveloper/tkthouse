@props(['title' => null, 'subtitle' => null, 'flush' => false, 'id' => null])
<section {{ $attributes->class(['a-card']) }} @if($id) id="{{ $id }}" @endif @if($title) aria-labelledby="{{ $id ? $id.'-t' : 'card-'.md5($title) }}" @endif>
    @if($title || isset($actions))
        <div class="a-card__head">
            <div style="min-width:0">
                @if($title)<h2 class="a-card__title" id="{{ $id ? $id.'-t' : 'card-'.md5($title) }}">{{ $title }}</h2>@endif
                @if($subtitle)<p class="a-card__sub">{{ $subtitle }}</p>@endif
            </div>
            @isset($actions)<div class="a-btn-group">{{ $actions }}</div>@endisset
        </div>
    @endif
    <div class="a-card__body {{ $flush ? 'a-card__body--flush' : '' }}">{{ $slot }}</div>
    @isset($footer)<div class="a-card__foot">{{ $footer }}</div>@endisset
</section>
