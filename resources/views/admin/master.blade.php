<!doctype html>
<html lang="en">
<head>
<meta name="color-scheme" content="light">
<script>/* Admin V2 is a light-content design: keep Dashmix from switching to its dark theme when the OS prefers dark */(function(){var h=document.documentElement;function f(){if(h.classList.contains('dark'))h.classList.remove('dark');if(h.hasAttribute('data-bs-theme'))h.removeAttribute('data-bs-theme');}f();new MutationObserver(f).observe(h,{attributes:true,attributeFilter:['class','data-bs-theme']});})();</script>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1.0">
  <meta name="csrf-token" content="{{ csrf_token() }}">
  @php
    $siteName = \App\Support\SystemSettings::get('site_name', 'TKT House');
    $logoLight = \App\Support\SystemSettings::get('site_logo_light') ? asset('storage/'.\App\Support\SystemSettings::get('site_logo_light')) : asset('images/logo-light.png');
    $authUser = auth()->user();
    $initial = $authUser ? mb_strtoupper(mb_substr(trim((string) ($authUser->name ?: $authUser->email)), 0, 1)) : '?';
    $roleName = $authUser?->getRoleNames()->first();
    $pageTitle = trim($__env->yieldContent('title'));
  @endphp
  <title>{{ $pageTitle !== '' ? $pageTitle.' · ' : '' }}{{ $siteName }} Admin</title>
  <meta name="robots" content="noindex, nofollow">

  @include('partials.favicon')
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">

  {{-- Bootstrap 5 grid/utilities/JS come from the bundled base; the look is entirely admin-v2.css --}}
  <link rel="stylesheet" href="{{ asset('admin/assets/css/dashmix.min.css') }}">
  <link rel="stylesheet" href="{{ asset('admin-v2/admin-v2.css') }}?v={{ filemtime(public_path('admin-v2/admin-v2.css')) }}">
  <link rel="stylesheet" href="{{ asset('admin/assets/js/plugins/flatpickr/flatpickr.min.css') }}">
  <link rel="stylesheet" href="{{ asset('admin-v2/datepicker.css') }}?v={{ filemtime(public_path('admin-v2/datepicker.css')) }}">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/intl-tel-input@22.0.2/build/css/intlTelInput.css">
  @stack('styles')
</head>

<body class="a-body">
<a class="a-skip" href="#aMain">Skip to content</a>

<div class="a-shell" id="page-container">
  <aside class="a-sidebar" id="aSidebar" aria-label="Main navigation">
    <div class="a-sidebar__brand">
      <a href="{{ route('admin.dashboard') }}" aria-label="{{ $siteName }} admin home"><img src="{{ $logoLight }}" alt="{{ $siteName }}"></a>
      <button type="button" class="a-sidebar__close" data-a-nav-close aria-label="Close menu"><i class="fa fa-xmark" aria-hidden="true"></i></button>
    </div>
    <nav class="a-sidebar__scroll">
      @include('admin.navbar')
    </nav>
    <div class="a-sidebar__foot"><a href="{{ route('front.home') }}" target="_blank" rel="noopener"><i class="fa fa-arrow-up-right-from-square" aria-hidden="true"></i> View website</a></div>
  </aside>
  <div class="a-backdrop" data-a-nav-close></div>

  <div class="a-main">
    <header class="a-topbar" role="banner">
      <button type="button" class="a-topbar__menu" data-a-nav-open aria-label="Open menu" aria-controls="aSidebar" aria-expanded="false"><i class="fa fa-bars" aria-hidden="true"></i></button>

      <form class="a-search" role="search" method="GET" action="{{ route('admin.search') }}">
        <i class="fa fa-magnifying-glass" aria-hidden="true"></i>
        <label class="a-sr-only" for="aSearch">Search</label>
        <input id="aSearch" type="search" name="q" value="{{ request()->routeIs('admin.search') ? request('q') : '' }}" placeholder="Search events, orders, customers…" maxlength="80" autocomplete="off">
      </form>

      <div class="a-topbar__actions">
        @can('events.create')
          <a class="a-btn a-btn--primary d-none d-md-inline-flex" href="{{ route('admin.events.create') }}"><i class="fa fa-plus" aria-hidden="true"></i> Create event</a>
        @endcan
        @if($authUser?->notifications_enabled ?? true)
        @php
          $bellUnread = $authUser ? $authUser->unreadNotifications()->count() : 0;
          $bellItems = $authUser ? $authUser->notifications()->limit(\App\Http\Controllers\Admin\NotificationController::BELL_LIMIT)->get() : collect();
        @endphp
        <div class="a-notif" data-a-notif data-feed="{{ route('admin.notifications.feed') }}" data-read-all="{{ route('admin.notifications.read-all') }}">
          <button type="button" class="a-notif__btn" aria-haspopup="true" aria-expanded="false" aria-controls="aNotifPanel" aria-label="Notifications">
            <i class="fa fa-bell" aria-hidden="true"></i>
            <span class="a-notif__badge" data-notif-badge @if(! $bellUnread) hidden @endif>{{ $bellUnread > 99 ? '99+' : $bellUnread }}</span>
          </button>
          <div class="a-notif__panel" id="aNotifPanel" role="dialog" aria-label="Notifications" hidden>
            <div class="a-notif__head"><b>Notifications</b><button type="button" class="a-notif__link" data-notif-readall>Mark all read</button></div>
            <div class="a-notif__list" data-notif-list>@include('admin.notifications._items', ['items' => $bellItems])</div>
            <a class="a-notif__foot" href="{{ route('admin.notifications.index') }}">View all</a>
          </div>
          <span class="a-sr-only" role="status" aria-live="polite" data-notif-live></span>
        </div>
        @endif
        <div class="a-menu">
          <button type="button" class="a-userbtn" data-a-menu-toggle aria-haspopup="true" aria-expanded="false">
            <span class="a-userbtn__avatar">@if($authUser?->profile_image)<img src="{{ asset('storage/'.$authUser->profile_image) }}" alt="">@else{{ $initial }}@endif</span>
            <span class="a-userbtn__text"><b>{{ $authUser?->name ?? 'Administrator' }}</b><small>{{ $roleName ? ucwords(str_replace(['_', '-'], ' ', $roleName)) : 'Team member' }}</small></span>
            <i class="fa fa-chevron-down" aria-hidden="true" style="font-size:11px;color:#8c8c85"></i>
          </button>
          <div class="a-menu__panel" role="menu">
            <div class="a-menu__head"><b>{{ $authUser?->name }}</b><small>{{ $authUser?->email }}</small></div>
            <a class="a-menu__item" role="menuitem" href="{{ route('front.home') }}" target="_blank" rel="noopener"><i class="fa fa-globe" aria-hidden="true"></i> View website</a>
            <form method="POST" action="{{ route('logout') }}">@csrf
              <button type="submit" class="a-menu__item" role="menuitem"><i class="fa fa-arrow-right-from-bracket" aria-hidden="true"></i> Sign out</button>
            </form>
          </div>
        </div>
      </div>
    </header>

    <main class="a-content" id="aMain" tabindex="-1">
      @if(session('success') && ! isset($__adminToastHandled))
        {{-- success flashes are shown as toasts by the script below --}}
      @endif
      @yield('content')
    </main>
  </div>
</div>

{{-- Shared confirmation dialog (any form/button with data-a-confirm) --}}
<div class="modal fade" id="aConfirm" tabindex="-1" aria-labelledby="aConfirmTitle" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered" style="max-width:440px">
    <div class="modal-content">
      <div class="modal-header"><h2 class="modal-title" id="aConfirmTitle" data-a-confirm-title>Please confirm</h2><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
      <div class="modal-body"><p class="mb-0" data-a-confirm-text></p></div>
      <div class="modal-footer"><button type="button" class="a-btn a-btn--outline" data-bs-dismiss="modal">Cancel</button><button type="button" class="a-btn a-btn--primary" data-a-confirm-ok>Confirm</button></div>
    </div>
  </div>
</div>

{{-- Scripts --}}
<script src="{{ asset('admin/assets/js/dashmix.app.min.js') }}"></script>
<script src="{{ asset('admin-v2/admin-v2.js') }}?v={{ filemtime(public_path('admin-v2/admin-v2.js')) }}"></script>
<script src="{{ asset('admin-v2/custom-select.js') }}?v={{ filemtime(public_path('admin-v2/custom-select.js')) }}"></script>
<script src="{{ asset('admin-v2/multiselect.js') }}?v={{ filemtime(public_path('admin-v2/multiselect.js')) }}"></script>
<script src="{{ asset('admin-v2/notifications.js') }}?v={{ filemtime(public_path('admin-v2/notifications.js')) }}" defer></script>
<script src="{{ asset('admin/assets/js/plugins/chart.js/chart.umd.js') }}"></script>
<script src="{{ asset('admin/assets/js/plugins/ckeditor5-classic/build/ckeditor.js') }}"></script>
<script src="{{ asset('admin/assets/js/plugins/flatpickr/flatpickr.min.js') }}"></script>
<script src="{{ asset('admin-v2/datepicker.js') }}?v={{ filemtime(public_path('admin-v2/datepicker.js')) }}"></script>
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
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

  // Every DELETE form gets the shared confirmation dialog (admin-v2.js) unless it set its own message.
  document.querySelectorAll('form').forEach(form => {
    const method = (form.querySelector('input[name="_method"]')?.value || form.method || '').toUpperCase();
    if (method !== 'DELETE' || form.hasAttribute('data-a-confirm')) return;
    form.setAttribute('data-a-confirm', 'This action cannot be undone.');
    form.setAttribute('data-a-confirm-title', 'Delete this item?');
    form.setAttribute('data-a-confirm-ok', 'Delete');
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
