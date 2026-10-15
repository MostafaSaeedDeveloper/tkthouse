@props(['title', 'sub' => null, 'eyebrow' => 'TKT House'])
<div class="tkt-page-head tkt-page-head--simple">
    <div class="tkt-container">
        <div class="tkt-page-head__top">
            <div>
                <div class="tkt-eyebrow">{{ $eyebrow }}</div>
                <h1 class="tkt-h1">{{ $title }}</h1>
                @if($sub)<p class="tkt-text-muted" style="margin-top:8px;max-width:640px">{{ $sub }}</p>@endif
            </div>
        </div>
    </div>
</div>
