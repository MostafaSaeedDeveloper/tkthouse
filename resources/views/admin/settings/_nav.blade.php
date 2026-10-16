@php
    $u = auth()->user();
    $tabs = [];
    foreach (\App\Settings\Registry::GROUPS as $key => $g) {
        if ($u->can($g['permission'])) {
            $tabs[] = [$g['label'], $key === 'general' ? route('admin.settings.edit') : route('admin.settings.section', $key), request()->is('dashboard/settings/'.$key) || ($key === 'general' && request()->routeIs('admin.settings.edit'))];
        }
    }
    if ($u->can('settings.integrations.view')) { $tabs[] = ['Integrations', route('admin.integrations.index'), request()->routeIs('admin.integrations.*')]; }
    if ($u->can('payment-methods.view')) { $tabs[] = ['Payment methods', route('admin.payment-methods.index'), false]; }
@endphp
<nav class="a-tabs" aria-label="Settings sections">
    @foreach($tabs as [$label, $url, $active])
        <a href="{{ $url }}" class="{{ $active ? 'is-active' : '' }}" @if($active) aria-current="page" @endif>{{ $label }}</a>
    @endforeach
</nav>
