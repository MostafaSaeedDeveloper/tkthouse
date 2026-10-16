@props(['title', 'subtitle' => null, 'crumbs' => []])
<div class="a-page-head">
    <div style="min-width:0">
        @if(count($crumbs))
            <nav aria-label="Breadcrumb">
                <ol class="a-crumbs">
                    @foreach($crumbs as $label => $url)
                        @if(is_int($label))
                            <li><span aria-current="page">{{ $url }}</span></li>
                        @elseif($url)
                            <li><a href="{{ $url }}">{{ $label }}</a></li>
                        @else
                            <li><span aria-current="page">{{ $label }}</span></li>
                        @endif
                    @endforeach
                </ol>
            </nav>
        @endif
        <h1 class="a-page-head__title">{{ $title }}</h1>
        @if($subtitle)<p class="a-page-head__sub">{{ $subtitle }}</p>@endif
    </div>
    @if(! $slot->isEmpty())<div class="a-page-head__actions">{{ $slot }}</div>@endif
</div>
