@extends('admin.master')

@section('content')
<div class="content">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h2 class="h4 mb-0">System Settings</h2>
    </div>

    @include('admin.partials.flash')

    <form method="POST" action="{{ route('admin.settings.update') }}" enctype="multipart/form-data" class="block block-rounded">
        @csrf
        @method('PUT')
        <div class="block-content">
            <h5>General</h5>
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

            <hr>
            <h5>Payment Methods</h5>
            <div class="mb-3">
                <label class="form-check me-4 d-inline-flex align-items-center gap-2"><input type="checkbox" class="form-check-input" name="payment_methods[]" value="visa" @checked(in_array('visa', old('payment_methods', $paymentMethods), true))> Visa / Card</label>
                <label class="form-check me-4 d-inline-flex align-items-center gap-2"><input type="checkbox" class="form-check-input" name="payment_methods[]" value="wallet" @checked(in_array('wallet', old('payment_methods', $paymentMethods), true))> Wallet</label>
                <label class="form-check d-inline-flex align-items-center gap-2"><input type="checkbox" class="form-check-input" name="payment_methods[]" value="paymob" @checked(in_array('paymob', old('payment_methods', $paymentMethods), true))> Paymob Gateway</label>
            </div>

            <div class="form-check form-switch mb-3">
                <input class="form-check-input" type="checkbox" name="paymob_enabled" value="1" id="paymob_enabled" @checked(old('paymob_enabled', $settings['paymob_enabled'] ?? false))>
                <label class="form-check-label" for="paymob_enabled">Enable Paymob Integration</label>
            </div>

            <div class="row">
                <div class="col-md-6 mb-3"><label class="form-label">Paymob API Key</label><input type="text" class="form-control" name="paymob_api_key" value="{{ old('paymob_api_key', $settings['paymob_api_key'] ?? '') }}"></div>
                <div class="col-md-3 mb-3"><label class="form-label">Paymob Iframe ID</label><input type="text" class="form-control" name="paymob_iframe_id" value="{{ old('paymob_iframe_id', $settings['paymob_iframe_id'] ?? '') }}"></div>
                <div class="col-md-3 mb-3"><label class="form-label">Card Integration ID</label><input type="text" class="form-control" name="paymob_integration_id_card" value="{{ old('paymob_integration_id_card', $settings['paymob_integration_id_card'] ?? '') }}"></div>
                <div class="col-md-3 mb-3"><label class="form-label">Wallet Integration ID</label><input type="text" class="form-control" name="paymob_integration_id_wallet" value="{{ old('paymob_integration_id_wallet', $settings['paymob_integration_id_wallet'] ?? '') }}"></div>
            </div>
        </div>
        <div class="block-content block-content-full text-end bg-body-light">
            <button class="btn btn-primary" type="submit">Save Settings</button>
        </div>
    </form>
</div>
@endsection
