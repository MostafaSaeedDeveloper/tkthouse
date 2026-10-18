# SPA-like navigation — Phase A audit

Scope of this change: **public frontend only**. Admin dashboard, scanner, checkout, payment, OTP, login/logout and the customer portal keep full page navigation. No business logic, schema or production data is touched.

Branch relationship: PR #147 is merged; `origin/main` (88334b1) is an ancestor of this branch, so the work is based on the latest `main`.

## 1. What the frontend looks like today

| Layout | Used by | Notes |
|---|---|---|
| `front/layout/app.blade.php` (`<body class="tkt-v2" data-layout="v2">`) | **every** live public page (home, events, event, about, contact, legal, auth, account, checkout) | Header and footer are partials. `<main id="tkt-main">` holds flash messages and `@yield('content')`. Scripts are at the end of `<body>`: jQuery, intl-tel-input, Bootstrap 3, `tkt-v2.js`, `auth-scripts`, then `@stack('scripts')`. |
| `front/layout/master.blade.php` + `header`/`footer` (legacy template) | **nothing** (no view extends it) | Still ships `js/spa-navigation.js`, a hand-rolled router that was never wired to the V2 layout. It is dead code. |

The header, the mobile menu panel and the auth modal are all **outside** `<main>`, so they naturally persist when only `<main>` is swapped.

## 2. Page inventory and JavaScript dependencies

| Page(s) | Page script | Risk for partial navigation |
|---|---|---|
| Home, Events listing, About, Contact, Terms/Privacy/Cookie | none (only `tkt-v2.js`, which is delegated or bound to the persistent header) | Low. `tkt-v2.js` binds tabs and `form[data-tkt-submit]` once at load, so those need re-initialisation after a swap. |
| Event details | `tkt-booking.js` (IIFE at load, finds `#tktBooking`; registers document-level `keydown` listeners and a `matchMedia` listener) | **Medium.** Needs init/cleanup, otherwise each visit leaks listeners and a second visit never initialises. |
| Express checkout, payment, confirmation, verify-link | `tkt-express.js`, `tkt-checkout.js`, `tkt-checkout-wizard.js`, `tkt-otp.js` (document/window listeners, `pageshow`, timers, `sessionStorage`) | **High.** Payment POSTs, OTP, auto-reload timers. Excluded on purpose. |
| Login, register, password reset, set-password | inline OTP / `auth-scripts` | Excluded (session changes, CSRF/session cookie rotation). |
| Customer portal (`/account/*`, `/tickets/*`) | inline script on profile | Excluded (authenticated, personal data). |
| Admin dashboard / scanner | separate layouts | Not touched. |

### Code that runs once at load (the risk list)

* `tkt-v2.js`: burger/header, account dropdown, `[data-auth-open]`, lightbox, toast, image fallbacks are **document-delegated or on persistent nodes**, so they survive a swap. `data-tkt-tabs` and `form[data-tkt-submit]` are bound to nodes inside `<main>` at load → need `init(root)`.
* `tkt-booking.js`: bound to nodes inside `<main>` plus `document` `keydown` x2 and a `matchMedia` `change` listener → needs a lifecycle (`init` / `destroy`).
* `auth-scripts.blade.php`: global listeners and a `MutationObserver` that already initialises late-added `[data-phone-intl]` inputs. Lives outside `<main>`, runs once. Safe.
* Inline GA / Meta Pixel snippets in `<head>`: run once; a virtual `page_view` must be sent on each soft navigation or analytics will undercount.
* Bootstrap 3 modal (`#login-register1`) is outside `<main>` → persists. A modal open during navigation must be closed first.
* No `DOMContentLoaded`/`onload` handlers inside the swapped public pages. `tkt-otp.js` and `auth-scripts` use `DOMContentLoaded` but are not on swapped pages.
* Flash messages live inside `<main>` → they are swapped with the page. Correct.
* SEO gap found: there is **no** meta description, canonical URL or Open Graph tag today. They will be added server-side (works with and without JavaScript).

## 3. Engine comparison (measured, not assumed)

Both engines were dropped into the real V2 layout and the same flow was clicked in headless Chromium (Home → Events → About → Home).

| | Hotwire Turbo Drive 8 | Swup 4 (+ head plugin) |
|---|---|---|
| Size (min+gzip) | 46 KB | 8.3 KB core + 2 KB head (+ ~3 KB a11y, ~2 KB progress) |
| What it replaces | whole `<body>` | only the configured container (`#tkt-main`) |
| Result with this layout, unmodified | **29 requests / 3 navigations**: jQuery, Bootstrap, intl-tel-input and `tkt-v2.js` re-downloaded and **re-executed on every visit** (`tkt-v2.js` ran 5 times); header node replaced, so burger/dropdown listeners are lost or duplicated | **3 requests / 3 navigations** (HTML only); `tkt-v2.js` ran once; header is the same DOM node; title updated |
| Opt-in model | everything is intercepted by default (links **and forms**), has to be opted out per element | links only; forms are untouched; an allow-list function decides each visit |
| Page cache | snapshot cache and preview of stale pages by default; must be disabled for checkout pages | in-memory cache, can be disabled per URL |
| Needed to adopt | move all scripts to `<head>`, `data-turbo-eval`, `data-turbo-permanent` on header/modal/toasts, disable drive for forms | wrap/identify the container, add a small lifecycle layer |

**Decision: Swup 4**, vendored as plain files under `public/js/vendor/` (the frontend does not use Vite, and the project already serves versioned static files). A custom AJAX router is not needed.

## 4. Baseline performance (before any change)

Local server, headless Chromium, external hosts (fonts, analytics, CDN) aborted so runs are deterministic; 5 runs after a warm-up; "ms" = click → URL changed, new `<title>` present and `readyState=complete`.

Desktop 1366x800:

| Navigation | ms | requests | KB | Full reload |
|---|---|---|---|---|
| Home → Events | 125 | 12 | 560 | yes |
| Events → Event details | 123 | 13 | 579 | yes |
| Event details → Home | 109 | 12 | 560 | yes |
| Home → About | 121 | 12 | 557 | yes |
| About → Contact | 111 | 12 | 559 | yes |

Mobile (390x844, via the mobile menu): 85–131 ms, 12–13 requests, same bytes, always a full reload.
Slow network (400 ms RTT, 200 KB/s): ~3.85–3.92 s per navigation.

## 5. Risks and mitigations

1. **Stale JS state** — add `TktPage` lifecycle: modules register `init(root)` and an optional `destroy()`; swapped pages call them on `content:replace`.
2. **Duplicate listeners/timers** — `tkt-booking.js` rewritten as an idempotent module with an `AbortController` for all listeners.
3. **Checkout / payment / OTP / auth** — never intercepted: allow-list of public paths only; forms are never touched; no caching of anything outside the allow-list.
4. **Session expiry / auth state** — pages are public and server-rendered; the header is persistent, so after login/logout (always a full navigation) it is rebuilt. Any non-HTML or redirect-to-other-path response falls back to a full load.
5. **Analytics** — send a virtual page view on each soft navigation.
6. **Accessibility** — focus management, route announcement and "skip to content" via the a11y plugin.
7. **Rapid clicks** — Swup aborts/ignores concurrent visits; verified in tests.
8. **Failure** — on request error show a retry toast and fall back to `location.href`.

Phases B–E proceed only when each previous phase's checks pass.

---

Results of phases B–E are in [`spa-navigation-report.md`](spa-navigation-report.md).
