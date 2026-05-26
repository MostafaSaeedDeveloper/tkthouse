<!doctype html>
<html lang="en" class="remember-theme">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1.0">
  @php
  $siteName = \App\Support\SystemSettings::get('site_name', 'TKT House');
  $primaryColor = \App\Support\SystemSettings::get('primary_color', '#f5b800');
  $secondaryColor = \App\Support\SystemSettings::get('secondary_color', '#111111');
  $logoLight = \App\Support\SystemSettings::get('site_logo_light') ? asset('storage/'.\App\Support\SystemSettings::get('site_logo_light')) : asset('images/logo-light.png');
@endphp
  <title>{{ $siteName }} — Admin</title>
  <meta name="robots" content="noindex, nofollow">

  <link rel="shortcut icon"                         href="{{ asset('images/favicon.png') }}">
  <link rel="icon" type="image/png" sizes="192x192" href="{{ asset('images/favicon.png') }}">
  <link rel="apple-touch-icon" sizes="180x180"      href="{{ asset('images/favicon.png') }}">

  {{-- 1. Dashmix base (structure + layout) --}}
  <link rel="stylesheet" href="{{ asset('admin/assets/css/dashmix.min.css') }}">

  {{-- 2. TKT House theme — must come AFTER dashmix to override --}}
  <link rel="stylesheet" href="{{ asset('admin/assets/css/custom.css') }}">
  <link rel="stylesheet" href="{{ asset('admin/assets/js/plugins/flatpickr/flatpickr.min.css') }}">
  <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/intl-tel-input@22.0.2/build/css/intlTelInput.css">
  <style>
  .iti { display: block !important; width: 100%; }
  .iti__selected-country { background: transparent !important; border-right: 1px solid rgba(255,255,255,0.09) !important; padding: 0 8px !important; gap: 3px !important; display: flex !important; align-items: center !important; }
  .iti__selected-country .iti__flag        { order: 1 !important; }
  .iti__selected-country .iti__selected-dial-code { order: 2 !important; }
  .iti__selected-country .iti__arrow       { order: 3 !important; margin-left: 3px !important; }
  .iti__selected-country:hover { background: rgba(215,166,0,0.08) !important; }
  .iti__selected-dial-code { color: #dbe4ff !important; font-size: 13px !important; font-weight: 500 !important; }
  .iti__arrow { border-top-color: #7e849b !important; }
  .iti--open .iti__arrow { border-bottom-color: #7e849b !important; }
  .iti__flag-container   { background: transparent !important; }
  .iti__dropdown-content { background: #181821 !important; border: 1px solid rgba(255,255,255,0.35) !important; border-radius: 10px !important; overflow: hidden !important; box-shadow: 0 16px 48px rgba(0,0,0,0.75) !important; z-index: 999999 !important; padding: 0 !important; }
  .iti__country-list { background: #181821 !important; border: none !important; border-radius: 0 !important; box-shadow: none !important; z-index: 999999 !important; max-height: 280px !important; overflow-y: auto !important; padding: 4px 0 !important; margin: 0 !important; }
  .iti__country-list::-webkit-scrollbar { width: 4px; } .iti__country-list::-webkit-scrollbar-thumb { background: rgba(255,255,255,0.14); border-radius: 99px; }
  .iti__search-input-wrapper { background: #181821 !important; padding: 8px 10px 6px !important; }
  .iti__search-input { display: block !important; width: 100% !important; height: 44px !important; background: #0f0f18 !important; border: 1px solid rgba(255,255,255,0.13) !important; border-radius: 10px !important; color: #dbe4ff !important; font-size: 13px !important; padding: 0 14px !important; outline: none !important; box-sizing: border-box !important; }
  .iti__search-input::placeholder { color: #7e849b !important; }
  .iti__divider { border-color: rgba(255,255,255,0.08) !important; margin: 2px 0 !important; }
  .iti__country { display: flex !important; align-items: center !important; gap: 10px !important; padding: 0 14px !important; height: 46px !important; color: #dbe4ff !important; font-size: 13px !important; background: transparent !important; }
  .iti__country:hover, .iti__country.iti__highlight { background: rgba(214,166,0,0.18) !important; }
  .iti__country-name { color: #dbe4ff !important; flex: 1 !important; white-space: nowrap !important; overflow: hidden !important; text-overflow: ellipsis !important; }
  .iti__dial-code { color: #7e849b !important; font-size: 12px !important; }
  </style>

  <script src="{{ asset('admin/assets/js/setTheme.js') }}"></script>
  <style>:root{--brand-primary: {{ $primaryColor }};--brand-secondary: {{ $secondaryColor }};}</style>
</head>

<body>

<div id="page-container"
     class="sidebar-o sidebar-dark enable-page-overlay side-scroll page-header-fixed main-content-narrow">

  {{-- ── Sidebar ── --}}
  <nav id="sidebar" aria-label="Main Navigation">
    @include('admin.navbar')
  </nav>

  {{-- ── Top Header ── --}}
  <header id="page-header">
    <div class="content-header">

      {{-- Left --}}
      <div class="space-x-1">
        <button type="button" class="btn btn-alt-secondary"
                data-toggle="layout" data-action="sidebar_toggle">
          <i class="fa fa-fw fa-bars"></i>
        </button>
        <button type="button" class="btn btn-alt-secondary"
                data-toggle="layout" data-action="header_search_on">
          <i class="fa fa-fw fa-search opacity-50"></i>
          <span class="ms-1 d-none d-sm-inline-block">Search</span>
        </button>
      </div>

      {{-- Right --}}
      <div class="space-x-1">

        {{-- User dropdown --}}
        <div class="dropdown d-inline-block">
          <button type="button" class="btn btn-alt-secondary"
                  id="page-header-user-dropdown"
                  data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
            <i class="fa fa-fw fa-circle-user d-sm-none"></i>
            <span class="d-none d-sm-inline-block">{{ auth()->user()?->name ?? 'Administrator' }}</span>
            <i class="fa fa-fw fa-angle-down opacity-50 ms-1 d-none d-sm-inline-block"></i>
          </button>
          <div class="dropdown-menu dropdown-menu-end p-0"
               aria-labelledby="page-header-user-dropdown">
            <div class="bg-primary-dark rounded-top text-center p-3">
              User Options
            </div>
            <div class="p-2">
              <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="dropdown-item">
                  <i class="far fa-fw fa-arrow-alt-circle-left me-1"></i> Sign Out
                </button>
              </form>
            </div>
          </div>
        </div>

      </div>

    </div>

    {{-- Search overlay --}}
    <div id="page-header-search" class="overlay-header">
      <div class="content-header">
        <form class="w-100" method="GET" action="#">
          <div class="input-group">
            <button type="button" class="btn btn-alt-secondary"
                    data-toggle="layout" data-action="header_search_off">
              <i class="fa fa-fw fa-times-circle"></i>
            </button>
            <input type="text" class="form-control border-0"
                   placeholder="Search…" name="q">
          </div>
        </form>
      </div>
    </div>

    {{-- Loader --}}
    <div id="page-header-loader" class="overlay-header">
      <div class="content-header">
        <div class="w-100 text-center">
          <i class="fa fa-fw fa-sun fa-spin" style="color:var(--gold)"></i>
        </div>
      </div>
    </div>

  </header>

  {{-- ── Main ── --}}
  <main id="main-container">
    @yield('content')
  </main>

  {{-- ── Footer ── --}}
  <footer id="page-footer">
    <div class="content py-2">
      <div class="row align-items-center">
        <div class="col-sm-6 text-center text-sm-start">
          <a href="{{ route('front.home') }}" style="font-family:'Syne',sans-serif;font-size:16px;font-weight:800;letter-spacing:-0.5px;">
            <img style="height: 20px" src="{{ $logoLight }}" alt="{{ $siteName }}">
        </a>
          <span class="ms-2" style="font-size:11px;"> &copy; {{ date('Y') }}</span>
        </div>
        <div class="col-sm-6 text-center text-sm-end">
          <a href="{{ route('front.home') }}" style="font-size:12px;">← Back to website</a>
        </div>
      </div>
    </div>
  </footer>

</div>

{{-- Scripts --}}
<script src="{{ asset('admin/assets/js/dashmix.app.min.js') }}"></script>
<script src="{{ asset('admin/assets/js/plugins/chart.js/chart.umd.js') }}"></script>
<script src="{{ asset('admin/assets/js/plugins/ckeditor5-classic/build/ckeditor.js') }}"></script>
<script src="{{ asset('admin/assets/js/plugins/flatpickr/flatpickr.min.js') }}"></script>
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script>
(() => {
  if (typeof ClassicEditor === 'undefined') return;
  document.querySelectorAll('.js-ckeditor-description').forEach(el => {
    if (el.dataset.ckeditorInitialized === '1') return;
    ClassicEditor.create(el)
      .then(e => { e.ui.view.editable.element.style.minHeight = '220px'; el.dataset.ckeditorInitialized = '1'; })
      .catch(console.error);
  });
})();
</script>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
(() => {
  if (typeof Swal === 'undefined') return;

  const Toast = Swal.mixin({
    toast: true, position: 'top-end',
    showConfirmButton: false, timer: 3000, timerProgressBar: true,
  });

  if (window.adminToastSuccess)
    Toast.fire({ icon: 'success', title: window.adminToastSuccess });

  if (Array.isArray(window.adminValidationErrors) && window.adminValidationErrors.length)
    Swal.fire({
      icon: 'error', title: 'Validation Error',
      html: '<ul style="text-align:left;margin:0;padding-left:20px">'
        + window.adminValidationErrors.map(i => `<li>${i}</li>`).join('') + '</ul>',
    });

  document.querySelectorAll('form').forEach(form => {
    const method = (form.querySelector('input[name="_method"]')?.value || form.method || '').toUpperCase();
    if (method !== 'DELETE') return;
    form.addEventListener('submit', e => {
      if (form.dataset.confirmed === '1') return;
      e.preventDefault();
      Swal.fire({
        title: 'Are you sure?', text: 'This action cannot be undone.',
        icon: 'warning', showCancelButton: true,
        confirmButtonText: 'Yes, delete it', cancelButtonText: 'Cancel',
      }).then(r => { if (r.isConfirmed) { form.dataset.confirmed = '1'; form.submit(); } });
    });
  });
})();
</script>


<script>
(() => {
  if (typeof window.jQuery === 'undefined' || typeof jQuery.fn.select2 === 'undefined') return;
  jQuery('select').not('.no-select2, .swal2-select, .js-modal-select2').filter(function () {
    return jQuery(this).closest('.swal2-container').length === 0;
  }).each(function () {
    const $el = jQuery(this);
    if ($el.data('select2')) return;
    $el.addClass('js-select2').select2({ width: '100%' });
  });
})();
</script>

<script src="https://cdn.jsdelivr.net/npm/intl-tel-input@22.0.2/build/js/intlTelInput.min.js"></script>
<script>
(function () {
    var ITI_UTILS = 'https://cdn.jsdelivr.net/npm/intl-tel-input@22.0.2/build/js/utils.js';
    function initPhoneInput(input) {
        if (input._itiInstance || !window.intlTelInput) return;
        var iti = window.intlTelInput(input, {
            separateDialCode: true, initialCountry: 'eg',
            preferredCountries: ['eg', 'sa', 'ae', 'us', 'gb'],
            utilsScript: ITI_UTILS
        });
        input._itiInstance = iti;
        var fieldName = input.getAttribute('name');
        if (fieldName) {
            input.removeAttribute('name');
            var hidden = document.createElement('input');
            hidden.type = 'hidden'; hidden.name = fieldName;
            input.insertAdjacentElement('afterend', hidden);
            function sync() {
                var num = '', country = iti.getSelectedCountryData();
                if (window.intlTelInputUtils) {
                    num = iti.getNumber(window.intlTelInputUtils.numberFormat.E164) || '';
                } else if (country && country.dialCode) {
                    var raw = input.value.trim().replace(/^0+/, '').replace(/\D/g, '');
                    num = raw ? ('+' + country.dialCode + raw) : '';
                } else { num = input.value; }
                hidden.value = num.replace(/(?!^\+)\D/g, '');
            }
            input.addEventListener('input', sync);
            input.addEventListener('countrychange', sync);
            if (iti.promise && typeof iti.promise.then === 'function') iti.promise.then(sync);
            sync();
        }
    }
    function initAll(root) {
        (root || document).querySelectorAll('[data-phone-intl]:not([data-iti-ready])').forEach(function (el) {
            el.setAttribute('data-iti-ready', '1'); initPhoneInput(el);
        });
    }
    document.addEventListener('DOMContentLoaded', function () { initAll(); });
    new MutationObserver(function (muts) {
        muts.forEach(function (m) {
            m.addedNodes.forEach(function (n) {
                if (n.nodeType !== 1) return;
                if (n.matches && n.matches('[data-phone-intl]')) { n.setAttribute('data-iti-ready','1'); initPhoneInput(n); }
                else initAll(n);
            });
        });
    }).observe(document.body, { childList: true, subtree: true });
    window.initPhoneInputs = initAll;
})();
</script>
@stack('scripts')

</body>
</html>
