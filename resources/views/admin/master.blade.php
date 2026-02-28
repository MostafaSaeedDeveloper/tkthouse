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

  <link rel="shortcut icon"                         href="{{ asset('favicon.ico') }}">
  <link rel="icon" type="image/png" sizes="192x192" href="{{ asset('favicon.ico') }}">
  <link rel="apple-touch-icon" sizes="180x180"      href="{{ asset('favicon.ico') }}">

  {{-- 1. Dashmix base (structure + layout) --}}
  <link rel="stylesheet" href="{{ asset('admin/assets/css/dashmix.min.css') }}">

  {{-- 2. TKT House theme — must come AFTER dashmix to override --}}
  <link rel="stylesheet" href="{{ asset('admin/assets/css/custom.css') }}">
  <link rel="stylesheet" href="{{ asset('admin/assets/js/plugins/flatpickr/flatpickr.min.css') }}">
  <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />

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
  jQuery('select').not('.no-select2, .swal2-select').filter(function () {
    return jQuery(this).closest('.swal2-container').length === 0;
  }).each(function () {
    const $el = jQuery(this);
    if ($el.data('select2')) return;
    $el.addClass('js-select2').select2({ width: '100%' });
  });
})();
</script>

@stack('scripts')

</body>
</html>
