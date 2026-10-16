{{-- <x-admin.breadcrumb :items="['Dashboard' => route('admin.dashboard'), 'Orders']" /> — last item (int key or null url) is the current page --}}
@props(['items' => []])
<nav aria-label="Breadcrumb">
    <ol class="a-crumbs">
        @foreach($items as $label => $url)
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
