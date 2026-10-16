@props(['type' => 'info'])
<div {{ $attributes->class(['a-alert', 'a-alert--'.$type]) }} role="{{ in_array($type, ['danger', 'warning']) ? 'alert' : 'status' }}"><div style="min-width:0">{{ $slot }}</div></div>
