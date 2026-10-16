# TKT House V2 — Design System

Files: `public/css/tkt-v2.css` (tokens + components), `public/css/tkt-v2-pages.css` (page layouts),
`public/js/tkt-v2.js` (shared UI), `public/js/tkt-booking.js` (event ticket selection).
Layout: `resources/views/front/layout/app.blade.php` (Home, Events, Event details use it; legacy pages still use `master`).

## Tokens (`:root` in tkt-v2.css)
Change the brand in one place: `--tkt-primary` (default `#FFD200`; the admin "primary colour" setting overrides it when set),
`--tkt-ink`, `--tkt-surface`, `--tkt-dark`, feedback colours (`--tkt-success|danger|warning|info`),
type scale `--tkt-fs-*`, radii `--tkt-radius*`, spacing `--tkt-gutter|section-y|gap` (responsive).

## Components (all prefixed `tkt-`)
Buttons `.tkt-btn --primary|dark|outline|outline-light|ghost --sm|lg|block`, `.is-loading` · Inputs `.tkt-input|select|textarea`, `.is-invalid`,
`.tkt-field__error|hint` · Card `.tkt-card` · Badge `.tkt-badge --primary|dark|success|danger|warning|glass` · Tabs `data-tkt-tabs` + `.tkt-tab` ·
Alerts `.tkt-alert --success|danger|warning|info` · Modal skin `.tkt-modal` (Bootstrap modal) · Empty `.tkt-empty` · Loading `.tkt-spinner`, `.tkt-skeleton` ·
Pagination `vendor.pagination.tkt`.

Blade components: `<x-tkt.event-card :event :past>`, `<x-tkt.badge variant>`, `<x-tkt.empty-state title text icon>`, `<x-tkt.section-head title sub href>`.
