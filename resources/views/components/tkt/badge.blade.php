@props(['variant' => 'default', 'dot' => false])
<span {{ $attributes->class(['tkt-badge', 'tkt-badge--'.$variant => $variant !== 'default', 'tkt-badge--dot' => $dot]) }}>{{ $slot }}</span>
