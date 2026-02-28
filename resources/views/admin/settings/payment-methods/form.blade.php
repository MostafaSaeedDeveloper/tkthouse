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

    <form method="POST" enctype="multipart/form-data" action="{{ $method->exists ? route('admin.payment-methods.update', $method) : route('admin.payment-methods.store') }}" class="block block-rounded">
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
                    <small class="text-muted">Examples: visa, wallet, paymob_card, paymob_wallet, paymob_apple_pay</small>
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label">Provider</label>
                    <select class="form-select" name="provider" id="provider-select" required>
                        <option value="manual" @selected(old('provider', $method->provider ?: 'manual') === 'manual')>Manual</option>
                        <option value="paymob" @selected(old('provider', $method->provider) === 'paymob')>Paymob</option>
                    </select>
                </div>
            </div>

            <div class="row">
                <div class="col-md-4 mb-3">
                    <label class="form-label">Checkout Label</label>
                    <input class="form-control" name="checkout_label" value="{{ old('checkout_label', $method->checkout_label) }}" placeholder="Text shown in checkout">
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label">Checkout Icon Image</label>
                    <input type="file" class="form-control" name="checkout_icon_file" accept="image/*">
                    @if($method->checkout_icon && str_contains($method->checkout_icon, '/'))
                        <div class="mt-2"><img src="{{ asset('storage/'.$method->checkout_icon) }}" alt="icon" style="height:36px;width:36px;object-fit:contain;border-radius:6px;background:#fff"></div>
                    @endif
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label">Checkout Description (Optional)</label>
                    <input class="form-control" name="checkout_description" value="{{ old('checkout_description', $method->checkout_description) }}" placeholder="Optional short description under payment method">
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
                    <div class="col-md-3 mb-3"><label class="form-label">Integration ID</label><input class="form-control" name="paymob_integration_id" value="{{ old('paymob_integration_id', $cfg['integration_id'] ?? '') }}"></div>
                </div>
                <div class="mb-3">
                    <label class="form-label">Callback URL (Copy & add in Paymob dashboard)</label>
                    <div class="input-group">
                        <input id="paymob-callback-url" type="text" class="form-control" readonly value="{{ route('front.paymob.callback') }}">
                        <button class="btn btn-alt-secondary" type="button" id="copy-paymob-callback">Copy</button>
                    </div>
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
  const copyBtn = document.getElementById('copy-paymob-callback');
  const callbackInput = document.getElementById('paymob-callback-url');
  const toggle = () => { paymobBox.style.display = provider.value === 'paymob' ? 'block' : 'none'; };
  provider.addEventListener('change', toggle);
  copyBtn?.addEventListener('click', async () => {
    try {
      await navigator.clipboard.writeText(callbackInput.value);
      copyBtn.textContent = 'Copied';
      setTimeout(() => copyBtn.textContent = 'Copy', 1200);
    } catch (e) {
      callbackInput.select();
      document.execCommand('copy');
    }
  });
  toggle();
})();
</script>
@endpush
