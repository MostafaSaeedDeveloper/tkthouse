@props(['current' => 2])
@php
    $steps = [1 => 'Tickets', 2 => 'Your details', 3 => 'Payment', 4 => 'Confirmation'];
@endphp
<ol class="tkt-stepper-nav" aria-label="Checkout progress">
    @foreach($steps as $n => $label)
        <li class="{{ $n < $current ? 'is-done' : ($n === $current ? 'is-current' : '') }}" @if($n === $current) aria-current="step" @endif>
            <span class="tkt-step-dot">
                @if($n < $current)
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 6 9 17l-5-5"/></svg>
                @else
                    {{ $n }}
                @endif
            </span>
            <span class="tkt-step-label">{{ $label }}</span>
        </li>
    @endforeach
</ol>
