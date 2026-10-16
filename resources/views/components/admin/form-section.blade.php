@props(['title', 'description' => null, 'cols' => 2])
<section {{ $attributes->class(['a-form-section']) }}>
    <div class="a-form-section__head">
        <h3 class="a-form-section__title">{{ $title }}</h3>
        @if($description)<p class="a-form-section__desc">{{ $description }}</p>@endif
    </div>
    <div class="a-form-grid a-form-grid--{{ $cols }}">{{ $slot }}</div>
</section>
