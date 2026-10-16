@props(['label', 'value', 'icon' => 'fa-chart-simple', 'hint' => null])
<div {{ $attributes->class(['a-kpi']) }}>
    <span class="a-kpi__icon" aria-hidden="true"><i class="fa {{ $icon }}"></i></span>
    <div class="a-kpi__body">
        <div class="a-kpi__label">{{ $label }}</div>
        <div class="a-kpi__value">{{ $value }}</div>
        @if($hint)<div class="a-kpi__hint">{{ $hint }}</div>@endif
    </div>
</div>
