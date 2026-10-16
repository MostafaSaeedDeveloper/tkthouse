@props(['src' => null, 'alt' => ''])
<span class="a-thumb">@if($src)<img src="{{ $src }}" alt="{{ $alt }}" loading="lazy">@else<i class="fa fa-image" aria-hidden="true"></i>@endif</span>
