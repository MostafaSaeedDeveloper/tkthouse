@extends('front.layout.master')
@section('content')<h1>TKT House</h1>@foreach($events as $event)<div><a href="{{ route('front.events.show',$event->slug) }}">{{ $event->title }}</a></div>@endforeach@endsection
