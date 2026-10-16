{{-- Session flashes + validation summary, consistent on every screen --}}
@if(session('success'))<x-admin.alert type="success">{{ session('success') }}</x-admin.alert>@endif
@if(session('error'))<x-admin.alert type="danger">{{ session('error') }}</x-admin.alert>@endif
@if(session('warning'))<x-admin.alert type="warning">{{ session('warning') }}</x-admin.alert>@endif
@if($errors->any())
    <x-admin.alert type="danger"><strong>Please fix the following:</strong><ul>@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></x-admin.alert>
@endif
