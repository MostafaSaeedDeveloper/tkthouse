@extends('admin.master')

@section('content')
<div class="content">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h2 class="h4 mb-1">{{ $method->exists ? 'Edit' : 'Create' }} Payment Method</h2>
            <a href="{{ route('admin.payment-methods.index') }}" class="fs-sm">← Back to Payment Methods</a>
        </div>
    </div>

    @include('admin.partials.flash')

    <form method="POST" action="{{ $method->exists ? route('admin.payment-methods.update', $method) : route('admin.payment-methods.store') }}" class="block block-rounded">
        @csrf
        @if($method->exists) @method('PUT') @endif

        @php($cfg = $method->config ?? [])

        <div class="block-content">
            <div class="row">
                <div class="col-md-4 mb-3">
                    <label class="form-label">Name</label>
                    <input class="form-control" name="name" value="{{ old('name', $method->name) }}" required>
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label">Code</label>
                    <input class="form-control" name="code" value="{{ old('code', $method->code) }}" required>
                    <small class="text-muted">lowercase, numbers, underscore only (e.g. paymob)</small>
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label">Provider</label>
                    <select class="form-select" name="provider" id="provider-select" required>
                        <option value="manual" @selected(old('provider', $method->provider ?: 'manual') === 'manual')>Manual</option>
                        <option value="paymob" @selected(old('provider', $method->provider) === 'paymob')>Paymob</option>
                    </select>
                </div>
            </div>

            <div class="form-check form-switch mb-4">
                <input class="form-check-input" type="checkbox" name="is_active" value="1" id="is_active" @checked(old('is_active', $method->is_active ?? true))>
                <label class="form-check-label" for="is_active">Active</label>
            </div>

            <div id="paymob-config" style="display:none;">
                <h5>Paymob Configuration</h5>
                <div class="row">
                    <div class="col-md-6 mb-3"><label class="form-label">API Key</label><input class="form-control" name="paymob_api_key" value="{{ old('paymob_api_key', $cfg['api_key'] ?? '') }}"></div>
                    <div class="col-md-3 mb-3"><label class="form-label">Iframe ID</label><input class="form-control" name="paymob_iframe_id" value="{{ old('paymob_iframe_id', $cfg['iframe_id'] ?? '') }}"></div>
                    <div class="col-md-3 mb-3"><label class="form-label">Card Integration ID</label><input class="form-control" name="paymob_integration_id_card" value="{{ old('paymob_integration_id_card', $cfg['integration_id_card'] ?? '') }}"></div>
                    <div class="col-md-3 mb-3"><label class="form-label">Wallet Integration ID</label><input class="form-control" name="paymob_integration_id_wallet" value="{{ old('paymob_integration_id_wallet', $cfg['integration_id_wallet'] ?? '') }}"></div>
                </div>
            </div>
        </div>

        <div class="block-content block-content-full text-end bg-body-light">
            <button class="btn btn-primary" type="submit">Save</button>
        </div>
    </form>
</div>
@endsection

@push('scripts')
<script>
(() => {
  const provider = document.getElementById('provider-select');
  const paymobBox = document.getElementById('paymob-config');
  const toggle = () => { paymobBox.style.display = provider.value === 'paymob' ? 'block' : 'none'; };
  provider.addEventListener('change', toggle);
  toggle();
})();
</script>
@endpush
