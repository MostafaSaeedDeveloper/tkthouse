@extends('admin.master')
@section('content')<h2>Event Report</h2><pre>{{ json_encode($data, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE) }}</pre>@endsection
