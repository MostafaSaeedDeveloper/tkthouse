# Admin V2 design system

Black sidebar + topbar, light content, TKT yellow (`#FFD200`) primary actions. Blade + Bootstrap 5 (bundled with the existing admin assets) + vanilla JS. No third-party admin theme.

Files: `public/admin-v2/admin-v2.css`, `public/admin-v2/admin-v2.js`, `resources/views/admin/master.blade.php` (shell), `resources/views/admin/navbar.blade.php` (sidebar), `resources/views/components/admin/*` (components).
Screens `@extends('admin.master')`, set `@section('title', '…')` and put their markup in `@section('content')`. Page-specific scripts go in `@push('scripts')`, page-specific CSS in `@push('styles')` (prefix your classes, e.g. `a-ev-…`).

## Page skeleton
```blade
@extends('admin.master')
@section('title', 'Orders')
@section('content')
<x-admin.page-header title="Orders" subtitle="…" :crumbs="['Dashboard' => route('admin.dashboard'), 'Orders']">
    <a class="a-btn a-btn--primary" href="…"><i class="fa fa-plus"></i> New</a>      {{-- header actions slot --}}
</x-admin.page-header>
<x-admin.flash />                                   {{-- success / error / validation summary --}}
<x-admin.filters :action="route('admin.orders.index')" :reset="route('admin.orders.index')"> …fields… </x-admin.filters>
<x-admin.card flush>
    <x-admin.table stack caption="Orders"> <thead>…</thead> <tbody>… <td data-label="Order">…</td> …</tbody> </x-admin.table>
    <x-admin.pager :paginator="$orders" />
</x-admin.card>
@endsection
```

## Components (`<x-admin.*>`)
| Component | Props / slots | Use |
|---|---|---|
| `page-header` | `title`, `subtitle`, `crumbs` (`['Label' => url, 'Current']`); default slot = action buttons | top of every screen |
| `card` | `title`, `subtitle`, `flush`, `id`; slots `actions`, `footer` | any block; `flush` for tables |
| `kpi` | `label`, `value`, `icon` (FontAwesome name), `hint` | KPI tiles inside `.a-grid.a-grid--4` |
| `status` | `status`, `kind` = order\|payment\|ticket\|event | status pills for the statuses that already exist (never invent new ones) |
| `badge` | `variant` = success\|warning\|danger\|info\|dark\|primary, `dot` | generic pill |
| `empty` | `title`, `text`, `icon`; default slot = action | empty states |
| `alert` / `flash` | `type` | messages |
| `filters` | `action`, `reset`; fields are `<div class="a-field"><label class="form-label">…` | GET filter bar (search + selects + dates) |
| `table` | `stack`, `compact`, `caption` | responsive table wrapper (`stack` turns rows into labelled blocks < 768px; give every `td` a `data-label`) |
| `pager` | `paginator` | "Showing x–y of z" + pagination |
| `field` | `label`, `name`, `hint`, `for`, `required` | labelled form control + error message |
| `form-section` | `title`, `description`, `cols` (1–3) | groups of fields inside a form card |
| `thumb` | `src`, `alt` | 44px image/thumbnail cell |

Layout helpers: `.a-grid` (`--2`, `--3`, `--4`, `--main-side` = content + 360px sidebar), `.a-form-grid` (`--2`, `--3`; `.is-full` spans), `.a-kv` (label/value list), `.a-btn-group`, `.a-chips/.a-chip`, `.a-tabs`, `.a-form-actions` (sticky save bar at the bottom of a form card), `.a-progress`.
Buttons: `a-btn a-btn--primary|dark|outline|ghost|danger|success`, sizes `--sm`/`--lg`; loading state: put `data-a-loading` on a `<form>` (its submit button shows a spinner and is disabled once).
Confirmations: `<form data-a-confirm="Message" data-a-confirm-title="Title" data-a-confirm-ok="Delete">` or the same attributes on a `<button>`; DELETE forms get a default confirmation automatically (`admin/master.blade.php`).
Tables: cells are `nowrap`; use `class="a-wrap"` on a cell that may wrap, `.a-trunc` for long single-line text, `.a-num` for right-aligned numbers, `.a-table__main` / `.a-table__sub` for primary/secondary text, `.a-table__actions` for row buttons.
Legacy Bootstrap classes (`.btn`, `.form-control`, `.form-select`, `.badge`, `.table`, `.modal`, `.alert`, `.pagination`, `.nav-tabs`) are already styled to match; prefer the `a-*` components in new markup.

## Rules for screens
* UI only: never change business logic, routes, route names, form field names, permission checks (`@can`/middleware stay as they are — the server enforces them), validation, or database.
* Keep every element id/class that JavaScript or tests rely on, and keep text that existing tests assert (`grep -rn "<text>" tests/` before removing copy).
* No demo numbers, no placeholder images: show real data or an empty state.
* Accessible: labels on every control, `aria-label` on icon buttons, visible focus (provided), keyboard-operable menus/modals, table captions.
* Responsive 360 → 1440, no horizontal page scroll (tables scroll inside `.a-table-wrap` or use `stack`).
