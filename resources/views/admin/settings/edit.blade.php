@extends('admin.master')

@section('content')
<div class="content">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h2 class="h4 mb-0">General Settings</h2>
        <div class="dropdown">
            <button class="btn btn-alt-secondary dropdown-toggle" data-bs-toggle="dropdown" type="button">Settings Menu</button>
            <div class="dropdown-menu dropdown-menu-end">
                <a class="dropdown-item" href="{{ route('admin.settings.edit') }}">General Settings</a>
                <a class="dropdown-item" href="{{ route('admin.payment-methods.index') }}">Payment Methods</a>
            </div>
        </div>
    </div>

    @include('admin.partials.flash')

    <form method="POST" action="{{ route('admin.settings.update') }}" enctype="multipart/form-data" class="block block-rounded">
        @csrf
        @method('PUT')
        <div class="block-content">
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label">Site Name</label>
                    <input type="text" class="form-control" name="site_name" value="{{ old('site_name', $settings['site_name'] ?? 'TKT House') }}" required>
                </div>
                <div class="col-md-3 mb-3">
                    <label class="form-label">Primary Color</label>
                    <input type="color" class="form-control form-control-color" name="primary_color" value="{{ old('primary_color', $settings['primary_color'] ?? '#f5b800') }}">
                </div>
                <div class="col-md-3 mb-3">
                    <label class="form-label">Secondary Color</label>
                    <input type="color" class="form-control form-control-color" name="secondary_color" value="{{ old('secondary_color', $settings['secondary_color'] ?? '#111111') }}">
                </div>
            </div>

            <div class="row">
                <div class="col-md-4 mb-3"><label class="form-label">Logo Light</label><input type="file" class="form-control" name="logo_light" accept="image/*"></div>
                <div class="col-md-4 mb-3"><label class="form-label">Logo Dark</label><input type="file" class="form-control" name="logo_dark" accept="image/*"></div>
                <div class="col-md-4 mb-3"><label class="form-label">Logo Footer</label><input type="file" class="form-control" name="logo_footer" accept="image/*"></div>
            </div>
        </div>
        <div class="block-content block-content-full text-end bg-body-light">
            <button class="btn btn-primary" type="submit">Save Settings</button>
        </div>
    </form>
</div>
@endsection
