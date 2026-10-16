@props(['variant' => 'default', 'dot' => false])
<span {{ $attributes->class(['a-badge', 'a-badge--'.$variant => $variant !== 'default', 'a-badge--dot' => $dot]) }}>{{ $slot }}</span>
