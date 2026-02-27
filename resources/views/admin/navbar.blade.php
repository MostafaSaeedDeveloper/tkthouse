{{-- ═══════════════════════════════════════════
     SIDEBAR — TKT House Admin
     Styling handled entirely by custom.css
     ═══════════════════════════════════════════ --}}

{{-- ── Logo bar ── --}}
<div class="bg-header-dark">
  <div class="content-header bg-white-5">
    <a href="{{ route('admin.dashboard') }}"
       style="font-family:'Syne',sans-serif;font-size:20px;font-weight:800;letter-spacing:-0.5px;text-decoration:none;line-height:1;">
                  <img style="height: 30px" src="{{asset('images/logo-light.png')}}" alt="">

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
  $usersMenuOpen = request()->routeIs('admin.users.*')
      || request()->routeIs('admin.roles.*')
      || request()->routeIs('admin.permissions.*')
      || request()->routeIs('admin.activity-logs.*');
@endphp

{{-- ── Nav links ── --}}
<div class="js-sidebar-scroll">
  <div class="content-side">
    <ul class="nav-main">

      {{-- Dashboard --}}
      <li class="nav-main-item">
        <a class="nav-main-link {{ request()->routeIs('admin.dashboard') ? 'active' : '' }}"
           href="{{ route('admin.dashboard') }}">
          <i class="nav-main-link-icon fa fa-gauge-high"></i>
          <span class="nav-main-link-name">Dashboard</span>
        </a>
      </li>

      {{-- Separator --}}
      <li class="nav-main-heading">Events & Sales</li>

      {{-- Events --}}
      <li class="nav-main-item">
        <a class="nav-main-link {{ request()->routeIs('admin.events.*') ? 'active' : '' }}"
           href="{{ route('admin.events.index') }}">
          <i class="nav-main-link-icon fa fa-calendar-alt"></i>
          <span class="nav-main-link-name">Events</span>
        </a>
      </li>

      {{-- Orders --}}
      <li class="nav-main-item">
        <a class="nav-main-link {{ request()->routeIs('admin.orders.*') ? 'active' : '' }}"
           href="{{ route('admin.orders.index') }}">
          <i class="nav-main-link-icon fa fa-receipt"></i>
          <span class="nav-main-link-name">Orders</span>
        </a>
      </li>

      {{-- Customers --}}
      <li class="nav-main-item">
        <a class="nav-main-link {{ request()->routeIs('admin.customers.*') ? 'active' : '' }}"
           href="{{ route('admin.customers.index') }}">
          <i class="nav-main-link-icon fa fa-users"></i>
          <span class="nav-main-link-name">Customers</span>
        </a>
      </li>

      {{-- Tickets --}}
      <li class="nav-main-item">
        <a class="nav-main-link {{ request()->routeIs('admin.tickets.*') ? 'active' : '' }}"
           href="{{ route('admin.tickets.index') }}">
          <i class="nav-main-link-icon fa fa-ticket"></i>
          <span class="nav-main-link-name">Tickets</span>
        </a>
      </li>

      {{-- Reports --}}
      <li class="nav-main-item">
        <a class="nav-main-link {{ request()->routeIs('admin.reports.*') ? 'active' : '' }}"
           href="{{ route('admin.reports.index') }}">
          <i class="nav-main-link-icon fa fa-chart-column"></i>
          <span class="nav-main-link-name">Reports</span>
        </a>
      </li>

      {{-- Separator --}}
      <li class="nav-main-heading">System</li>

      {{-- Users submenu --}}
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
          <li class="nav-main-item">
            <a class="nav-main-link {{ request()->routeIs('admin.users.*') ? 'active' : '' }}"
               href="{{ route('admin.users.index') }}">
              <span class="nav-main-link-name">All Users</span>
            </a>
          </li>
          <li class="nav-main-item">
            <a class="nav-main-link {{ request()->routeIs('admin.roles.*') ? 'active' : '' }}"
               href="{{ route('admin.roles.index') }}">
              <span class="nav-main-link-name">Roles</span>
            </a>
          </li>
          <li class="nav-main-item">
            <a class="nav-main-link {{ request()->routeIs('admin.permissions.*') ? 'active' : '' }}"
               href="{{ route('admin.permissions.index') }}">
              <span class="nav-main-link-name">Permissions</span>
            </a>
          </li>
          <li class="nav-main-item">
            <a class="nav-main-link {{ request()->routeIs('admin.activity-logs.*') ? 'active' : '' }}"
               href="{{ route('admin.activity-logs.index') }}">
              <span class="nav-main-link-name">Activity Logs</span>
            </a>
          </li>
        </ul>
      </li>

    </ul>
  </div>
</div>
