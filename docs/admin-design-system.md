# TKT Admin Design System

An independent, token-driven design system for the TKT House admin. Black sidebar + topbar, light content, TKT yellow (`#FFD200`) for primary actions only. Blade + the bundled Bootstrap 5 (grid / utilities / JS) + vanilla JS — no React, Vue, Tailwind or ready-made admin theme. It is separate from the frontend: it is loaded only by `resources/views/admin/master.blade.php` and `auth/login.blade.php`, and no frontend stylesheet is loaded in the admin (or vice-versa).

## 1. Audit (what was wrong, what stays)

| Finding | Decision |
|---|---|
| Admin screens were built on Dashmix (`dashmix.min.css`, 577 KB, Bootstrap 5 + its own colours). Its blue links/buttons, `html.dark` theme and `.block`/`.btn-alt-*` styles leaked through (e.g. a dark page + blue links when the OS prefers dark). | Dashmix is kept **only** for grid, utilities, modal/dropdown JS. Its colours are neutralised in `base.css` / `components.css`; `master.blade.php` removes the `dark` class it adds and declares `color-scheme: light`. |
| 4 competing CSS files (`admin-v2.css` + three per-page files) with oversized sizes (15 px body, 44 px controls, 14 px radius, 20 px paddings) and duplicated component rules. | Replaced by `public/admin-ds/css/` (below). One definition per component; per-page CSS reduced to 3 module files + dashboard. |
| `admin/assets/css/custom.css` (28 KB) and `dashmix.css` (700 KB, un-minified duplicate): not referenced by any view. | Deleted. |
| 66 inline `style=""` attributes, one `<style>` block in the dashboard (moved to `dashboard.css`); scanner pages (`scanner.blade.php`, `scanner-login.blade.php`, `pdf.blade.php`) are standalone documents with their own dark/camera UI. | Inline styles reduced to layout one-offs; scanner/pdf deliberately stay standalone (camera + print). |
| Ticket types rendered as saturated Bootstrap-blue pills in tables; 9-column orders table needing horizontal scroll. | `<x-admin.more>` (first value + "+N more" popover); columns merged by priority. |
| 202 static Dashmix demo files in `resources/views/admin/html template/`. | Not referenced by routes/controllers; safe to delete in a separate clean-up commit (left untouched here). |
| Status badges: raw `<span class="badge bg-…">` in places. | `<x-admin.status>` maps existing statuses → success/warning/danger/info only. |

## 2. Files

```
public/admin-ds/css/tokens.css      colours, type scale, spacing, radius, control heights, layout sizes
public/admin-ds/css/base.css        reset, typography, links, Bootstrap/Dashmix colour neutralisation
public/admin-ds/css/layout.css      shell, sidebar, topbar, page header, grids
public/admin-ds/css/components.css  buttons, badges, cards, KPI, forms, filter bar, tables, pagination, alerts, toast,
                                    empty/loading, tabs, dropdown, modal, select2/flatpickr skins
public/admin-ds/css/dashboard.css   dashboard home
public/admin-ds/css/module-*.css    module-only rules (events/tickets, orders/customers, settings/users/roles/reports/login)
public/admin-ds/admin.js            off-canvas sidebar, menus, floating popovers, confirm dialog, loading state, window.aToast()
resources/views/admin/master.blade.php, admin/navbar.blade.php   the single layout
resources/views/components/admin/*.blade.php                      the Blade components
```

## 3. Tokens (`tokens.css`)

* Colour: primary `#FFD200`, sidebar/topbar `#111111`, background `#F7F8FA`, card `#FFFFFF`, text `#171717` / secondary `#6B7280`, border `#E5E7EB` (rows `#EEF0F3`, controls `#D1D5DB`). Success / warning / danger / info exist **for meaning only**.
* Type: Inter. Body/inputs/buttons 14 px, table body 13 px, table head 11.5 px uppercase, labels/secondary 12.5 px, card title 15 px, page title 22 px, KPI 24 px. Money and counts use `font-variant-numeric: tabular-nums` and never wrap.
* Spacing scale `--a-s-1…8` (4 · 8 · 12 · 16 · 20 · 24 · 32). Card padding 16, page gap 16, table cell 10 × 16.
* Shape: card radius 12, control radius 8, one control height 36 px (sm 30, lg 42) for inputs, selects, buttons.

## 4. Layout

One layout: `@extends('admin.master')`, `@section('title', …)`, `@section('content')`. It provides sidebar (logo, Operations / System groups, permission-aware items, yellow active state), topbar (global search, *Create event* by permission, user menu), skip link, flash/validation toasts, confirm dialog, off-canvas sidebar < 992 px. Screens never re-implement these.

## 5. Components (`<x-admin.*>`)

| Component | Use |
|---|---|
| `page-header` (`title`, `subtitle`, `crumbs`, actions slot) / `breadcrumb` | top of every screen |
| `button` (`variant` primary·secondary·danger·dark·ghost·success, `size`, `href`, `icon`, `loading`) | all buttons (or the `a-btn a-btn--…` classes) |
| `input`, `select`, `search`, `date`, `field` (+ `form-section`) | labelled controls with error/hint; `a-form-grid--2/3`, sticky `a-form-actions` |
| `filters` (`action`, `reset`) | the one filter bar: Search · Status · Event · Payment method · Date range · Apply · Reset — each page includes only the fields that apply |
| `card` (`title`, `subtitle`, `flush`; slots `actions`, `footer`), `kpi` | content blocks |
| `table` (`stack`, `compact`, `caption`) + `pager` | data tables. Cells: `a-table__main` / `a-table__sub`, `a-num` for money, `a-trunc` for long single-line text, `a-wrap` to allow wrapping. `stack` turns rows into labelled blocks < 768 px (`data-label` on every `td`). |
| `more` | several values in one cell → first value + "+N more" popover (ticket types, events) — never a wall of pills |
| `dropdown` | row / toolbar action menus (`a-menu__item`, `--danger`); a "View" button stays visible, secondary actions go in the menu |
| `status`, `badge` | order/payment/ticket/event status pills |
| `tabs`, `modal`, `empty`, `loading`, `alert`, `flash`, `toast` (JS `aToast()`), `thumb` | the rest |

Confirmations: `data-a-confirm="…"` (+ `-title`, `-ok`) on a form or button; DELETE forms get one automatically. Loading: `data-a-loading` on a form.

## 6. Rules for screens

* UI only: no changes to routes, field names, validation, permissions (`@can` / middleware), business logic or the database.
* Use components and tokens. If a rule is missing, add it to the shared CSS, not the page. Module CSS only for genuinely page-specific layout.
* No default Bootstrap colours, no hex colours outside `tokens.css`, no demo data.
* Tables: first value + "+N more", full names on the details page; no horizontal page scroll; every icon button has `aria-label`.
* Keep text asserted by tests (`grep -rn "<text>" tests/`).

## 7. Delivery status

**Unified on the system (all use `admin.master`, the shared components and tokens):** Dashboard, Events (list, create/edit, details, event dashboard), Tickets (list, details, edit, create), Guest list (+ create), Orders (list, details, edit, deleted), Customers (list, details), Promo codes, Payments, Reports, Affiliates, Scanner staff, Team & access (users, roles, permissions), Settings, Payment methods, Activity logs, global Search, admin Login.

**Deliberately standalone:** `tickets/scanner.blade.php`, `scanner-login.blade.php` (camera UI, dark by design) and `tickets/pdf.blade.php` (print). They are not part of the shell.

**Verification:** 175 page × width combinations (360 / 390 / 768 / 1024 / 1440) — no horizontal page scroll and no console errors; a permission-limited role was checked (sidebar, Quick actions and Create event follow permissions, restricted routes still 403); `tests/Feature/AdminDesignSystemTest.php` locks in the layout, token file, no hard-coded colours, ticket-type collapsing, single pagination summary and permission-aware navigation. Screenshots: `docs/screenshots/admin-ds/` (desktop + mobile, `limited/` for the restricted role, `mobile-sidebar.png`, `mobile-orders-cards.png`).

**Still open / decisions:**
* Events list has no filter bar (the controller does no filtering; adding fields would be dead controls).
* Reports only query paid orders (controller untouched) — pending / failed / refunded are separated on Payments and linked from Reports.
* Ticket-type colour input default `#0d6efd` is stored form data, not styling.
* `resources/views/admin/html template/` (202 static Dashmix demo files) is unused and can be deleted in a separate commit.
* Dashmix (`dashmix.min.css`) is still loaded for grid/modal/dropdown behaviour; replacing it with plain Bootstrap 5 would be a separate, riskier step.
* The few scanner screens that stay dark/standalone could be aligned later if desired.
