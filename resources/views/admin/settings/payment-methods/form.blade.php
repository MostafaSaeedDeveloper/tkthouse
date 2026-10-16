@extends('admin.master')
@section('title', ($method->exists ? 'Edit' : 'Create').' Payment Method')
@push('styles')<link rel="stylesheet" href="{{ asset('admin-v2/misc.css') }}?v={{ filemtime(public_path('admin-v2/misc.css')) }}">@endpush
@section('content')
<x-admin.page-header :title="($method->exists ? 'Edit' : 'Create').' Payment Method'" :crumbs="['Dashboard' => route('admin.dashboard'), 'Payment Methods' => route('admin.payment-methods.index'), $method->exists ? 'Edit' : 'Create']">
    <a href="{{ route('admin.payment-methods.index') }}" class="a-btn a-btn--ghost">&larr; Back to Payment Methods</a>
</x-admin.page-header>
<x-admin.flash />
<form method="POST" enctype="multipart/form-data" action="{{ $method->exists ? route('admin.payment-methods.update', $method) : route('admin.payment-methods.store') }}" data-a-loading>
    @csrf
    @if($method->exists) @method('PUT') @endif
    @php($cfg = $method->config ?? [])
    <x-admin.card class="a-m-narrow">
        <x-admin.form-section title="Method" cols="3">
            <x-admin.field label="Name" name="name" for="pm-name" required><input id="pm-name" class="form-control" name="name" value="{{ old('name', $method->name) }}" required></x-admin.field>
            <x-admin.field label="Code" name="code" for="pm-code" required hint="Examples: visa, wallet, paymob_card, paymob_wallet, paymob_apple_pay"><input id="pm-code" class="form-control" name="code" value="{{ old('code', $method->code) }}" required></x-admin.field>
            <x-admin.field label="Provider" name="provider" for="provider-select" required>
                <select class="form-select" name="provider" id="provider-select" required>
                    <option value="manual" @selected(old('provider', $method->provider ?: 'manual') === 'manual')>Manual</option>
                    <option value="paymob" @selected(old('provider', $method->provider) === 'paymob')>Paymob</option>
                    <option value="fawaterak" @selected(old('provider', $method->provider) === 'fawaterak')>Fawaterak</option>
                </select>
            </x-admin.field>
        </x-admin.form-section>

        <x-admin.form-section title="Checkout appearance" description="What customers see when choosing this method." cols="3">
            <x-admin.field label="Checkout Label" name="checkout_label" for="pm-label"><input id="pm-label" class="form-control" name="checkout_label" value="{{ old('checkout_label', $method->checkout_label) }}" placeholder="Text shown in checkout"></x-admin.field>
            <x-admin.field label="Checkout Icon Image" name="checkout_icon_file" for="pm-icon">
                <input id="pm-icon" type="file" class="form-control" name="checkout_icon_file" accept="image/*">
                @if($method->checkout_icon_url)<div class="mt-2"><img src="{{ $method->checkout_icon_url }}" alt="Current icon" style="height:36px;width:36px;object-fit:contain;border-radius:6px;background:#fff"></div>@endif
            </x-admin.field>
            <x-admin.field label="Checkout Description (Optional)" name="checkout_description" for="pm-desc"><input id="pm-desc" class="form-control" name="checkout_description" value="{{ old('checkout_description', $method->checkout_description) }}" placeholder="Optional short description under payment method"></x-admin.field>
            <div class="is-full a-m-switch form-check form-switch">
                <input class="form-check-input" type="checkbox" name="is_active" value="1" id="is_active" @checked(old('is_active', $method->is_active ?? true))>
                <label class="form-check-label" for="is_active"><strong>Active</strong><span class="a-hint d-block">Inactive methods are hidden at checkout.</span></label>
            </div>
        </x-admin.form-section>

        <div id="paymob-config" style="display:none;">
            <x-admin.form-section title="Paymob Configuration" cols="3">
                <x-admin.field class="is-full" label="API Key" name="paymob_api_key" for="pm-pk"><input id="pm-pk" class="form-control" name="paymob_api_key" value="{{ old('paymob_api_key', $cfg['api_key'] ?? '') }}"></x-admin.field>
                <x-admin.field label="Iframe ID" name="paymob_iframe_id" for="pm-iframe"><input id="pm-iframe" class="form-control" name="paymob_iframe_id" value="{{ old('paymob_iframe_id', $cfg['iframe_id'] ?? '') }}"></x-admin.field>
                <x-admin.field label="Integration ID" name="paymob_integration_id" for="pm-integ"><input id="pm-integ" class="form-control" name="paymob_integration_id" value="{{ old('paymob_integration_id', $cfg['integration_id'] ?? '') }}"></x-admin.field>
                <x-admin.field class="is-full" label="Callback URL (Copy & add in Paymob dashboard)" for="paymob-callback-url">
                    <div class="input-group">
                        <input id="paymob-callback-url" type="text" class="form-control" readonly value="{{ route('front.paymob.callback') }}">
                        <button class="a-btn a-btn--outline" type="button" id="copy-paymob-callback">Copy</button>
                    </div>
                </x-admin.field>
            </x-admin.form-section>
        </div>

        <div id="fawaterak-config" style="display:none;">
            <x-admin.form-section title="Fawaterak Configuration" description="You can create multiple payment methods with provider = Fawaterak, each one can use a different API/Provider key pair." cols="2">
                <x-admin.field label="API Key" name="fawaterak_api_key" for="fawaterak-api-key"><input class="form-control" id="fawaterak-api-key" name="fawaterak_api_key" value="{{ old('fawaterak_api_key', $cfg['api_key'] ?? '') }}"></x-admin.field>
                <div class="a-field">
                    <label class="form-label" for="fawaterak-method-select">Payment Method (from Fawaterak)</label>
                    <select class="form-select" id="fawaterak-method-select" name="fawaterak_provider_key" data-current="{{ old('fawaterak_provider_key', $cfg['provider_key'] ?? '') }}">
                        <option value="{{ old('fawaterak_provider_key', $cfg['provider_key'] ?? '') }}">{{ old('fawaterak_provider_key', $cfg['provider_key'] ?? '') ? 'Current: '.old('fawaterak_provider_key', $cfg['provider_key'] ?? '') : 'Enter API key to load methods' }}</option>
                    </select>
                    <div id="fawaterak-method-hint" class="a-hint">The dropdown updates when API key changes.</div>
                    @error('fawaterak_provider_key')<div class="a-error" role="alert">{{ $message }}</div>@enderror
                </div>
            </x-admin.form-section>
        </div>

        <div class="a-form-actions"><a class="a-btn a-btn--outline" href="{{ route('admin.payment-methods.index') }}">Cancel</a><button class="a-btn a-btn--primary" type="submit">Save</button></div>
    </x-admin.card>
</form>
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
        const providerKey = String(m.provider_key || m.id || '');
        opt.value = providerKey;
        opt.textContent = m.provider_key
          ? `${m.name} (${m.provider_key})`
          : `${m.name} (#${m.id})`;
        if (current && String(current) === providerKey) opt.selected = true;
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
