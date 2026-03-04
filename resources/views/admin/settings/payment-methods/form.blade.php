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
                        <option value="fawaterak" @selected(old('provider', $method->provider) === 'fawaterak')>Fawaterak</option>
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
                    @if($method->checkout_icon_url)
                        <div class="mt-2"><img src="{{ $method->checkout_icon_url }}" alt="icon" style="height:36px;width:36px;object-fit:contain;border-radius:6px;background:#fff"></div>
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


            <div id="fawaterak-config" style="display:none;">
                <h5>Fawaterak Configuration</h5>
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">API Key</label>
                        <input class="form-control" id="fawaterak-api-key" name="fawaterak_api_key" value="{{ old('fawaterak_api_key', $cfg['api_key'] ?? '') }}">
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Payment Method (from Fawaterak)</label>
                        <select class="form-select" id="fawaterak-method-select" name="fawaterak_provider_key" data-current="{{ old('fawaterak_provider_key', $cfg['provider_key'] ?? '') }}">
                            <option value="{{ old('fawaterak_provider_key', $cfg['provider_key'] ?? '') }}">{{ old('fawaterak_provider_key', $cfg['provider_key'] ?? '') ? 'Current: '.old('fawaterak_provider_key', $cfg['provider_key'] ?? '') : 'Enter API key to load methods' }}</option>
                        </select>
                        <small id="fawaterak-method-hint" class="text-muted">The dropdown updates when API key changes.</small>
                    </div>
                </div>
                <p class="text-muted fs-sm mb-0">You can create multiple payment methods with provider = Fawaterak, each one can use a different API/Provider key pair.</p>
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
  const fawaterakBox = document.getElementById('fawaterak-config');
  const copyBtn = document.getElementById('copy-paymob-callback');
  const callbackInput = document.getElementById('paymob-callback-url');
  const fApiKey = document.getElementById('fawaterak-api-key');
  const fMethodSelect = document.getElementById('fawaterak-method-select');
  const fHint = document.getElementById('fawaterak-method-hint');

  const toggle = () => {
    paymobBox.style.display = provider.value === 'paymob' ? 'block' : 'none';
    fawaterakBox.style.display = provider.value === 'fawaterak' ? 'block' : 'none';
    if (provider.value === 'fawaterak') loadFawaterakMethods();
  };

  let loading = false;
  const loadFawaterakMethods = async () => {
    const apiKey = (fApiKey?.value || '').trim();
    if (!apiKey || loading) return;
    loading = true;
    fHint.textContent = 'Loading methods...';
    try {
      const url = `{{ route('admin.payment-methods.fawaterak-methods') }}?api_key=${encodeURIComponent(apiKey)}`;
      const res = await fetch(url, { headers: { 'Accept': 'application/json' } });
      const data = await res.json();
      const current = fMethodSelect.dataset.current || '';
      fMethodSelect.innerHTML = '';
      (data.methods || []).forEach((m) => {
        const opt = document.createElement('option');
        opt.value = String(m.id);
        opt.textContent = `${m.name} (#${m.id})`;
        if (current && (String(current) === String(m.id))) opt.selected = true;
        fMethodSelect.appendChild(opt);
      });
      if (!fMethodSelect.options.length) {
        const opt = document.createElement('option');
        opt.value = current;
        opt.textContent = current ? `Current: ${current}` : 'No methods returned';
        fMethodSelect.appendChild(opt);
      }
      fHint.textContent = 'Methods loaded from Fawaterak.';
    } catch (e) {
      fHint.textContent = 'Failed to fetch methods from Fawaterak API.';
    } finally {
      loading = false;
    }
  };

  provider.addEventListener('change', toggle);
  fApiKey?.addEventListener('change', loadFawaterakMethods);
  fApiKey?.addEventListener('blur', loadFawaterakMethods);

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
