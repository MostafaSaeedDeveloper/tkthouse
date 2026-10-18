# Soft-navigation browser checks

Headless-Chromium checks for `public/js/tkt-spa.js` (Swup). They run against a **local** app with the demo events
(`php artisan db:seed --class=EventsSeeder`, events `pulsed` and `sideral`) — never against production, and no real payment is made.

```bash
php artisan serve --port=8000            # separate terminal, SQLite/local .env
cd tests/browser/soft-navigation && npm install
export CHROMIUM_PATH=/path/to/chromium   # optional, default /opt/pw-browsers/chromium
node functional.mjs            # navigation, history, scroll, SEO tags, a11y, rapid clicks, failures, search, widgets
node functional.mjs mobile     # same on a 390x844 touch viewport (mobile menu)
node checkout.mjs              # checkout stays a full navigation; back/forward across the hard/soft boundary
node leak.mjs                  # 100+ navigations: listeners / DOM nodes / heap must not grow
node measure.mjs run-label     # latency, requests, bytes per navigation (add `mobile` and/or `slow`)
```

`auth.mjs` additionally needs a local customer: `spa-test@example.test` / `Passw0rd!spa` (create it with `php artisan tinker`,
`is_active = true`). It covers login/logout and a session that changes under a soft navigation.

External hosts (fonts, analytics, CDN) are aborted on purpose so timings are deterministic.
