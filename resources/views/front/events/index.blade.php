@extends('front.layout.master')
@section('content')<h2>Events</h2>@foreach($events as $event)<div>{{ $event->title }}</div>@endforeach@endsection
