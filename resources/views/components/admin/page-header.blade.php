@props(['title', 'subtitle' => null, 'crumbs' => []])
<div class="a-page-head">
    <div style="min-width:0">
        @if(count($crumbs))<x-admin.breadcrumb :items="$crumbs" />@endif
        <h1 class="a-page-head__title">{{ $title }}</h1>
        @if($subtitle)<p class="a-page-head__sub">{{ $subtitle }}</p>@endif
    </div>
    @if(! $slot->isEmpty())<div class="a-page-head__actions">{{ $slot }}</div>@endif
</div>
