{{-- ═══════════════════════════════════════════
     SIDEBAR — TKT House Admin
     Styling handled entirely by custom.css
     ═══════════════════════════════════════════ --}}

{{-- ── Logo bar ── --}}
<div class="bg-header-dark">
  <div class="content-header bg-white-5">
    <a href="{{ route('admin.dashboard') }}"
       style="font-family:'Syne',sans-serif;font-size:20px;font-weight:800;letter-spacing:-0.5px;text-decoration:none;line-height:1;">
      <img style="height: 30px" src="{{ \App\Support\SystemSettings::get('site_logo_light') ? asset('storage/'.\App\Support\SystemSettings::get('site_logo_light')) : asset('images/logo-light.png') }}" alt="{{ \App\Support\SystemSettings::get('site_name', 'TKT House') }}">
    </a>
    <div class="d-flex align-items-center gap-1">
      <button type="button" class="btn btn-sm btn-alt-secondary d-lg-none"
              data-toggle="layout" data-action="sidebar_close">
        <i class="fa fa-times"></i>
      </button>
    </div>
  </div>
</div>

@php
  $canDashboard = auth()->user()?->can('dashboard.view');
  $canReports = auth()->user()?->can('reports.view');
  $canEvents = auth()->user()?->can('events.view');
  $canOrders = auth()->user()?->can('orders.view');
  $canCustomers = auth()->user()?->can('attendees.view');
  $canAffiliates = auth()->user()?->can('attendees.view');
  $canPromoCodes = auth()->user()?->can('promo-codes.view');
  $canTickets = auth()->user()?->can('tickets.view');

  $canUsers = auth()->user()?->can('users.view');
  $canScanners = auth()->user()?->can('scanners.view');
  $canRoles = auth()->user()?->can('roles.view');
  $canPermissions = auth()->user()?->can('permissions.view');
  $canActivityLogs = auth()->user()?->can('activity-logs.view');

  $canSettings = auth()->user()?->can('settings.view');
  $canPaymentMethods = auth()->user()?->can('payment-methods.view');

  $showEventsSalesSection = $canReports || $canEvents || $canOrders || $canCustomers || $canAffiliates || $canPromoCodes || $canTickets;
  $showSystemSection = $canUsers || $canRoles || $canPermissions || $canActivityLogs || $canSettings || $canPaymentMethods;

  $usersMenuOpen = request()->routeIs('admin.users.*')
      || request()->routeIs('admin.roles.*')
      || request()->routeIs('admin.permissions.*')
      || request()->routeIs('admin.activity-logs.*');

  $settingsMenuOpen = request()->routeIs('admin.settings.*')
      || request()->routeIs('admin.payment-methods.*');
@endphp

{{-- ── Nav links ── --}}
<div class="js-sidebar-scroll">
  <div class="content-side">
    <ul class="nav-main">

      @if($canDashboard)
        <li class="nav-main-item">
          <a class="nav-main-link {{ request()->routeIs('admin.dashboard') ? 'active' : '' }}"
             href="{{ route('admin.dashboard') }}">
            <i class="nav-main-link-icon fa fa-gauge-high"></i>
            <span class="nav-main-link-name">Dashboard</span>
          </a>
        </li>
      @endif

      @if($showEventsSalesSection)
        <li class="nav-main-heading">Events & Sales</li>

        @if($canReports)
          <li class="nav-main-item">
            <a class="nav-main-link {{ request()->routeIs('admin.reports.*') ? 'active' : '' }}"
               href="{{ route('admin.reports.index') }}">
              <i class="nav-main-link-icon fa fa-chart-column"></i>
              <span class="nav-main-link-name">Reports</span>
            </a>
          </li>
        @endif

        @if($canEvents)
          <li class="nav-main-item">
            <a class="nav-main-link {{ request()->routeIs('admin.events.*') ? 'active' : '' }}"
               href="{{ route('admin.events.index') }}">
              <i class="nav-main-link-icon fa fa-calendar-alt"></i>
              <span class="nav-main-link-name">Events</span>
            </a>
          </li>
        @endif

        @if($canOrders)
          <li class="nav-main-item">
            <a class="nav-main-link {{ request()->routeIs('admin.orders.*') ? 'active' : '' }}"
               href="{{ route('admin.orders.index') }}">
              <i class="nav-main-link-icon fa fa-receipt"></i>
              <span class="nav-main-link-name">Orders</span>
            </a>
          </li>
        @endif

        @if($canCustomers)
          <li class="nav-main-item">
            <a class="nav-main-link {{ request()->routeIs('admin.customers.*') ? 'active' : '' }}"
               href="{{ route('admin.customers.index') }}">
              <i class="nav-main-link-icon fa fa-users"></i>
              <span class="nav-main-link-name">Customers</span>
            </a>
          </li>
        @endif

        @if($canAffiliates)
          <li class="nav-main-item">
            <a class="nav-main-link {{ request()->routeIs('admin.affiliates.*') ? 'active' : '' }}"
               href="{{ route('admin.affiliates.index') }}">
              <i class="nav-main-link-icon fa fa-link"></i>
              <span class="nav-main-link-name">Affiliate</span>
            </a>
          </li>
        @endif

        @if($canPromoCodes)
          <li class="nav-main-item">
            <a class="nav-main-link {{ request()->routeIs('admin.promo-codes.*') ? 'active' : '' }}"
               href="{{ route('admin.promo-codes.index') }}">
              <i class="nav-main-link-icon fa fa-tags"></i>
              <span class="nav-main-link-name">Promo Codes</span>
            </a>
          </li>
        @endif

        @if($canTickets)
          @php($ticketsMenuOpen = request()->routeIs('admin.tickets.*') || request()->routeIs('admin.guest-list.*'))
          <li class="nav-main-item {{ $ticketsMenuOpen ? 'open' : '' }}">
            <a class="nav-main-link nav-main-link-submenu"
               data-toggle="submenu"
               aria-haspopup="true"
               aria-expanded="{{ $ticketsMenuOpen ? 'true' : 'false' }}"
               href="#">
              <i class="nav-main-link-icon fa fa-ticket"></i>
              <span class="nav-main-link-name">Tickets</span>
            </a>
            <ul class="nav-main-submenu">
              <li class="nav-main-item">
                <a class="nav-main-link {{ request()->routeIs('admin.tickets.*') ? 'active' : '' }}" href="{{ route('admin.tickets.index') }}">
                  <span class="nav-main-link-name">All Tickets</span>
                </a>
              </li>
              <li class="nav-main-item">
                <a class="nav-main-link {{ request()->routeIs('admin.guest-list.*') ? 'active' : '' }}" href="{{ route('admin.guest-list.index') }}">
                  <span class="nav-main-link-name">Guestlist</span>
                </a>
              </li>
            </ul>
          </li>
        @endif

        @if($canScanners)
          <li class="nav-main-item">
            <a class="nav-main-link {{ request()->routeIs('admin.scanners.*') ? 'active' : '' }}"
               href="{{ route('admin.scanners.index') }}">
              <i class="nav-main-link-icon fa fa-qrcode"></i>
              <span class="nav-main-link-name">Scanners</span>
            </a>
          </li>
        @endif
      @endif

      @if($showSystemSection)
        <li class="nav-main-heading">System</li>
        @if($canUsers || $canRoles || $canPermissions || $canActivityLogs)
          <li class="nav-main-item {{ $usersMenuOpen ? 'open' : '' }}">
            <a class="nav-main-link nav-main-link-submenu"
               data-toggle="submenu"
               aria-haspopup="true"
               aria-expanded="{{ $usersMenuOpen ? 'true' : 'false' }}"
               href="#">
              <i class="nav-main-link-icon fa fa-user-shield"></i>
              <span class="nav-main-link-name">Users</span>
            </a>
            <ul class="nav-main-submenu">
              @if($canUsers)
                <li class="nav-main-item">
                  <a class="nav-main-link {{ request()->routeIs('admin.users.*') ? 'active' : '' }}"
                     href="{{ route('admin.users.index') }}">
                    <span class="nav-main-link-name">All Users</span>
                  </a>
                </li>
              @endif
              @if($canRoles)
                <li class="nav-main-item">
                  <a class="nav-main-link {{ request()->routeIs('admin.roles.*') ? 'active' : '' }}"
                     href="{{ route('admin.roles.index') }}">
                    <span class="nav-main-link-name">Roles</span>
                  </a>
                </li>
              @endif

              @if($canPermissions)
                <li class="nav-main-item">
                  <a class="nav-main-link {{ request()->routeIs('admin.permissions.*') ? 'active' : '' }}"
                     href="{{ route('admin.permissions.index') }}">
                    <span class="nav-main-link-name">Permissions</span>
                  </a>
                </li>
              @endif

              @if($canActivityLogs)
                <li class="nav-main-item">
                  <a class="nav-main-link {{ request()->routeIs('admin.activity-logs.*') ? 'active' : '' }}"
                     href="{{ route('admin.activity-logs.index') }}">
                    <span class="nav-main-link-name">Activity Logs</span>
                  </a>
                </li>
              @endif
            </ul>
          </li>
        @endif

        @if($canSettings || $canPaymentMethods)
          <li class="nav-main-item {{ $settingsMenuOpen ? 'open' : '' }}">
            <a class="nav-main-link nav-main-link-submenu"
               data-toggle="submenu"
               aria-haspopup="true"
               aria-expanded="{{ $settingsMenuOpen ? 'true' : 'false' }}"
               href="#">
              <i class="nav-main-link-icon fa fa-gear"></i>
              <span class="nav-main-link-name">Settings</span>
            </a>
            <ul class="nav-main-submenu">
              @if($canSettings)
                <li class="nav-main-item">
                  <a class="nav-main-link {{ request()->routeIs('admin.settings.*') ? 'active' : '' }}"
                     href="{{ route('admin.settings.edit') }}">
                    <span class="nav-main-link-name">General Settings</span>
                  </a>
                </li>
              @endif

              @if($canPaymentMethods)
                <li class="nav-main-item">
                  <a class="nav-main-link {{ request()->routeIs('admin.payment-methods.*') ? 'active' : '' }}"
                     href="{{ route('admin.payment-methods.index') }}">
                    <span class="nav-main-link-name">Payment Methods</span>
                  </a>
                </li>
              @endif
            </ul>
          </li>
        @endif
      @endif
    </ul>
  </div>
</div>
