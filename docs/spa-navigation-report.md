# Soft (SPA-like) navigation — implementation & validation report

Engine: **Swup 4.8.2** (+ head 2.3.1, a11y 5.2.1, scroll 4.0.0, progress 3.2.0 plugins), vendored under `public/js/vendor/`.
Audit, engine comparison and baseline: [`spa-navigation-audit.md`](spa-navigation-audit.md).
Server-side rendering is unchanged: every URL is still a complete Laravel/Blade document; the router only enhances links.

## What changed

| File | Change |
|---|---|
| `public/js/tkt-spa.js` (new) | Swup wiring: allow-list, hooks, session/layout validation, GET-search interception, analytics page views, retry bar |
| `public/js/tkt-page.js` (new) | `TktPage` lifecycle: `register(name, init(root) -> cleanup)`, `mount`, `unmount` |
| `public/js/tkt-v2.js` | Tabs, submit guards and image fallbacks re-initialise per page; menus/lightbox close on navigation |
| `public/js/tkt-booking.js` | Ticket selector is now `init()` + cleanup (document `keydown` x2, media-query listener, `tkt-has-bar` class removed on leave) |
| `resources/views/front/layout/app.blade.php` | Loads the scripts in order; adds `meta description`, `canonical`, Open Graph and Twitter card (server-rendered) |
| `resources/views/front/events/show.blade.php` | Provides description / OG image; booking script moved to the layout |
| `public/css/tkt-v2.css` | Thin progress line + dismissible error/retry bar (no full-screen loader) |
| `tests/Feature/SoftNavigationTest.php` | SEO tags, canonical rules, script order, allow-list guard |
| `tests/browser/soft-navigation/*` | Browser regression, leak and performance scripts (see its README) |

No business logic, database schema, payment code or Admin/Scanner layout was changed.

## Pages and behaviour

**Soft navigation (no full reload)** — between any two of: Home `/`, Events `/events` (incl. search, All/Upcoming/Previous filters, pagination), Event details `/events/{slug}`, About, Contact, Terms, Privacy, Cookie policy. Includes header search, mobile-menu search and the home hero search (GET `role="search"` forms only), Back/Forward, scroll restoration, title, meta, canonical, active menu item, focus and screen-reader announcement ("Navigated to: …").

**Deliberate exceptions (always a full navigation)**

* Checkout (express, payment, confirmation, verify link), Paymob/Fawaterak redirects, provider callbacks/webhooks, 3DS, `t/…` ticket downloads.
* Login, register, OTP, password reset, logout, customer account area, `/tickets/*`.
* Every form submission other than GET search (contact form, payment POST, refunds, logout…).
* Admin dashboard and scanner (separate layouts, untouched).
* Links with `target`, `download`, `data-toggle`, `data-auth-open`, `data-express-checkout`, `data-no-swup`, and external/other-origin links.
* If the page you are on is not on the allow-list (for example `/checkout`), links in the header are plain navigations too, so a page with its own scripts is never mixed with a swapped one.
* A response that is not a swappable public page (other layout, maintenance page, redirect to a non-public URL, **changed login state or CSRF token**) triggers a normal full load of the clicked URL.
* No page cache (`cache: false`): ticket availability and prices always come from the server, and checkout/customer data is never stored by the router.

**Failure handling**: server `500`, an unreachable server or an unexpected response falls back to a normal page load. Offline: the page stays, a bar says so and "Try again" retries as a soft navigation. A second click on a link that is already loading is ignored (one request).

## Test results (headless Chromium 1194, local app, demo data)

| Suite | Result |
|---|---|
| PHP full suite | 525 passed, 12 risky, 12 skipped (the same risky/skipped as before), including 6 new tests |
| `functional.mjs` desktop 1366x800 | 70 / 70 |
| `functional.mjs mobile` 390x844 touch | 71 / 71 |
| `auth.mjs` (login via modal, logout, session lost under a soft nav) | 11 / 11 |
| `checkout.mjs` (checkout stays full; Back across hard/soft boundary) | 12 / 12 |
| `leak.mjs` (104 navigations) | listeners +0, DOM nodes +0, JS heap +222 KB (≈2 KB/navigation) |

Covered: Home→Events, Events→Event details, Event details→Home, About, Contact, legal pages; search + filters + Back; Back x5 / Forward x2; scroll restoration; direct URL and refresh (server-rendered, SEO tags present); slow network; navigation errors (500, offline, 503/maintenance-like page, redirect to login); mobile menu; 6 rapid clicks on one link and bursts across links; session change under a soft navigation; login/logout; checkout hand-off; tabs, keyboard arrows and ticket selector after soft visits; no console errors in any scenario.

### Performance (same machine, external hosts blocked, 5 runs after warm-up; click → new title + `complete`)

| Navigation | Before: ms / requests / KB | After: ms / requests / KB |
|---|---|---|
| Home → Events | 125 / 12 / 560 | 66 / 2 / 61 |
| Events → Event details | 123 / 13 / 579 | 81 / 2 / 68 |
| Event details → Home | 109 / 12 / 560 | 71 / 2 / 61 |
| Home → About | 121 / 12 / 557 | 71 / 2 / 58 |
| About → Contact | 111 / 12 / 559 | 58 / 2 / 60 |
| Slow network (400 ms RTT, 200 KB/s), typical step | ≈3 870 ms | ≈760 ms |
| Mobile viewport, typical step | 85–131 ms | 46–56 ms |

The comparison is deliberately honest about the cost: the first page load now ships about **28 KB gzip** of extra JavaScript (Swup 8.3 + plugins 11.3 + `tkt-spa.js` 3.7 + `tkt-page.js` 0.8 + `tkt-booking.js` 3.7, which used to load only on the event page). It is downloaded once and cached; a visitor who opens a single page and leaves is slightly worse off, one who browses is clearly better. Local timings exclude real network latency of the HTML itself, so absolute numbers on production will be higher, but the saving (no re-parse of 5 scripts and 2 stylesheets, no re-request of images, no white flash) is structural.

## Defects found by the tests while building this (all fixed)

1. Validation added with `hooks.on('page:load')` only logged errors — Swup swallows errors from non-replacing hooks, so a changed session or a redirect to a non-public page did **not** fall back. Now implemented with `hooks.replace` and covered by `auth.mjs` / `functional.mjs`.
2. Swup's Scroll plugin adds two `{once}` listeners per navigation that never fire when the page is already at the top (+2 window listeners per navigation). Replaced with a plain `scrollFunction`; verified by `leak.mjs`.
3. The new meta description used `strip_tags` on the raw event description, which leaked the text of a `<script>` (existing PHP test `FrontendV2Test` caught it). It now sanitises with `SafeHtml::clean` first and has its own test.

## Known limits / decisions for review

* Verified in Chromium only (no Safari/Firefox here). Swup 4 targets all current browsers; please click through once on iOS Safari.
* On a deliberate fallback Swup itself writes one `console.error` line before the full load (session changed / non-public response). It is expected, and only happens in those cases.
* Offline retry is a soft retry; for other network failures the fallback is the browser's normal full navigation (its own error page and reload button).
* Analytics: GA `page_view` and Meta Pixel `PageView` are sent on every soft navigation (the first page still sends its own). Please confirm in the GA/Pixel dashboards that page views are not double counted for your setup.
* Checkout/auth partial navigation was **not** enabled (Phase D outcome): the audit and tests show the checkout scripts rely on `load`/`pageshow`, timers, `sessionStorage` and a full session lifecycle; enabling soft navigation there needs its own review with real sandbox payments.
* `public/js/spa-navigation.js` and the `master/header/footer` legacy layout are unused (nothing extends them). They were left alone; they can be deleted in a separate cleanup.
* Rollback: remove the six script tags/`tkt-spa.js` from `app.blade.php` — the site returns to full-page navigation with no other change (the lifecycle code degrades to running once at load).
