# Scanner audit, atomic check-in and UI rebuild — Fracture Halloween (31 Oct 2026)

Branch `claude/tkthouse-scanner-audit-9kft56`, stacked on the refund commit `efd169f` (PR #160 itself is **not** touched). Nothing here was merged, deployed, or run against production data.

## 1. How scanning worked before (lifecycle map)

`GET /scanner` (login) → `GET /dashboard/scanner` → camera (html5-qrcode from **unpkg.com**) → `POST scanner/lookup` (read + `lookup_success` log) → server-rendered card with **Check In / Cancel** buttons → `POST scanner/{ticket}/status` (full form post + redirect) → reload.

### Issues found

| # | Severity | Finding |
|---|---|---|
| 1 | **Critical** | `scannerStatus` was a blind `UPDATE` with no transaction, no lock, no "already checked in" test. Two devices could both "succeed"; the second silently overwrote `checked_in_at` and wrote a second `status_update` log (inflating dashboard check-in counts). |
| 2 | **Critical** | Any scanner could set status `canceled` or `not_checked_in` on any ticket in its scope (un-check-in, cancel, or **re-open a non-refund-canceled ticket straight to checked-in**). The seeded `scanner` role also holds `tickets.update`, `tickets.view`, `orders.view`. |
| 3 | **Critical** | Check-in never looked at order/payment state. A ticket whose order was `pending_payment`, `on_hold`, `rejected`, `canceled` or hand-edited to `refunded` (without a refund record) could be admitted. |
| 4 | High | Event authorisation was by **title prefix** (`name LIKE 'X - %'`). "Fracture Halloween" also matched "Fracture Halloween - After Party". Scanners with no assignment could scan anything. Issued tickets (`TicketIssuanceService`) carry **no `event_id`** — the real id lives on `order_items.event_id`. |
| 5 | High | Frontend decided the flow: lookup showed the ticket, but the "success" came from a redirect-and-flash. No concept of an unknown outcome; a lost response looked like a failure and was retried by hand. |
| 6 | Medium | Lookup query ORs `ticket_number` with the **un-indexed** `qr_payload` (full table scan). One insert per lookup + an `order` eager-load query. |
| 7 | Medium | Result card exposed holder e-mail, phone and order number to every scanner. |
| 8 | Medium | Runtime CDNs on the gate: unpkg (QR library), cdnjs (Font Awesome), Google Fonts, `tkthouse.com` logo on the login page. |
| 9 | Low | No keep-alive / session-expiry handling; a 401/419 on the fetch rendered "Lookup failed". |
| 10 | Info | **No rate limit on the scanner login form** (left unchanged — see risks). |

## 2. What changed

**Backend** (`app/Services/Scanner/CheckInService.php`, `TicketController`, routes)

* `POST dashboard/scanner/check-in` — the only path that admits anyone. One transaction: `SELECT … FOR UPDATE` on the ticket row (same lock `RefundService` takes), then checks in order: refund-cancelled → canceled → **event (by id)** → **order state (`paid` / `partially_refunded` only, soft-deleted orders refused)** → already checked in → conditional `UPDATE … WHERE status='not_checked_in'` (exactly-once even without the lock) → `ScanLog` in the **same** transaction. "approved" is returned only after commit.
* Event scope by id: `tickets.event_id`, else `order_items.event_id`, else (legacy rows with neither) the title fallback — only for scanners that are assigned/selected. The device sends the event the operator picked; the server verifies it is one the account may scan. Scanner assigned to Event A cannot target Event B (`event_not_allowed`) and a ticket of B is `WRONG EVENT` even if the title looks similar.
* Scan history: original success stays `status_update/checked_in` (what dashboards already count). Later attempts → `checkin_duplicate`; refusals → `checkin_rejected_{refunded,canceled,wrong_event,order,status}`; `lookup_success`/`lookup_failed`/`lookup_rejected_refunded` kept. Duplicate response says who/when the original check-in was.
* `scannerStatus` (old form route, kept): `checked_in` is routed through the same service. `canceled` / `not_checked_in` now require `tickets.update` **and** a non-scanner-only account, run under a row lock, and still can never touch a refund-cancelled ticket.
* `scannerLookup` is strictly read-only (`admit:false`). `GET scanner/ping` = keep-alive + fresh CSRF token. Check-in is `throttle:600,1` per user.
* Responses contain no e-mail, phone, order number or amounts. A rejected ticket reveals only number, type and event.
* Ticket lookup uses the unique `ticket_number` index first; the un-indexed `qr_payload` is touched only on a miss. **8 queries** for approve/duplicate, 5–6 for rejections (in-process, excluding session/auth middleware).
* Stats page for scanner users gains Duplicate / Rejected counters.

**Frontend** (`scanner.blade.php`, fully rewritten; `scanner-login` made CDN-free)

* html5-qrcode 2.3.8 vendored at `public/vendor/html5-qrcode/` (+ Apache-2.0 licence). No CDN, no icon font, no web font on either scanner page. Camera is started **once** and kept running; no rebuild per scan.
* Full-screen result states, text + icon + colour: green `ENTRY APPROVED`; red `ALREADY USED`, `REFUNDED`, `CANCELED`, `WRONG EVENT`, `INVALID TICKET`; amber `NEEDS REVIEW`, `UNVERIFIED`, `NO CONNECTION`, `SESSION EXPIRED`, `RESULT UNKNOWN`. Green appears only from a server `approved`.
* **Fast check-in** switch (default **OFF**, remembered per device). Off: scan → look-up → big CHECK IN button. On: scan → server check-in → result → camera already running. Manual ticket-number box works in both modes and without a camera.
* Debounce: the same QR staying in view = one request; it re-arms 1.5 s after the code leaves view. A different code scanned during a request is queued (one slot), not dropped.
* Never auto-retries a check-in. Timeout/network error → amber **UNVERIFIED** with a **Verify** button (read-only lookup); if the first attempt actually landed, Verify says "checked in by YOU moments ago".
* Event name prominent; staff name + assignment; online/camera pills; today's check-ins plus per-session Duplicate / Invalid / Refunded counters; torch + camera switch where the browser exposes them; sound/vibration toggle; safe-area insets; portrait and landscape layouts; 44 px+ targets; last-5 history list only (no unbounded DOM growth); camera watchdog + `visibilitychange`/`pageshow` recovery; 20 s ping.

**Dependencies / migrations:** none new. No migration. (An index on `tickets.qr_payload` would help misses; deliberately **not** added today.)

## 3. Verification

### Automated (all green)

* `tests/Feature/ScannerCheckInTest.php` — 16 tests / 140 assertions: valid → success; repeat → duplicate with original scanner/time; manual & URL-style payload; invalid codes; other event incl. similar title and forged event id; legacy rows; refunded / canceled / `pending_payment`/`on_hold`/`canceled`/`rejected`/`refunded` / soft-deleted order refused, partially-refunded order's remaining tickets admitted; retries never convert a refusal; guest list; no PII in responses/page; lookup read-only; old status form uses the atomic rules; scanner-only cannot cancel/re-open; logged-out → 401, role-less → 403; **CSRF 419**; ping; no CDN in page.
* `RefundTicketIntegrationTest` (PR #160 behaviour) updated only where it asserted the old unsafe behaviour (scanner cancelling tickets, html payload); refund rejection contract (`409`, `code: refunded`, `lookup_rejected_refunded` audit, no financial leakage) preserved. 27/27 pass.
* Full PHP suite: **59 failures, identical list on the untouched base commit** (environmental: no outbound network to api.qrserver.com etc.) — no new failures, 0 regressions.
* Browser (`tests/browser/scanner/scanner-e2e.mjs`, Chromium, fake camera streaming real QR codes): **50/50** — camera start, confirm vs fast mode, one request while a QR stays in view, already-used / refunded / other-event / invalid / unpaid, network failure (no false green; DB untouched; Verify → explicit re-check → exactly once), response lost after server commit (UNVERIFIED → Verify reports own scan), real expired session (419 → SESSION EXPIRED, login link), camera denied → manual works, re-enter → consistent ALREADY USED, no external requests, 320 px / landscape / Pixel layouts, no horizontal scroll, targets ≥ 40 px. Screenshots in `docs/scanner-audit/screenshots/`.
  * The browser run found and I fixed three real bugs the unit tests could not: html5-qrcode 2.3.8 rejects `{facingMode:{ideal}}` (camera never started); `hidden` buttons still rendered on the approved screen (CSS override); camera box overflowed on narrow screens.

### Concurrency & load — isolated MariaDB 10.11 on `127.0.0.1:3307`, generated data only

`tests/Support/scanner_load.php` (refuses to run unless DB is localhost, name contains `test`, `SCANNER_LOAD_OK=1`). 4,660 generated tickets (4,000 valid Fracture; 60 each refunded/canceled/unpaid/partial; 300 other-event; 60 pre-checked-in), 21 scanner accounts, real login + CSRF, real HTTP via `php -S` with 32 workers, **everything on one 4-vCPU box** (client + server + DB). Full results: `docs/scanner-audit/load-test-results.json`.

| Scenario | Result | p50 / p95 / p99 (ms, client-observed HTTP) |
|---|---|---|
| 1 scanner sequential (150) | 150/150 approved | 47 / 65 / 72 |
| Same ticket, **2** scanners ×60 rounds | exactly 1 approval, 1 duplicate in **60/60** rounds | 48 / 68 / 108 |
| Same ticket, **5** ×60 | 60/60 rounds exactly 1 approval | 53 / 109 / 184 |
| Same ticket, **10** ×60 | 60/60 | 100 / 185 / 287 |
| Same ticket, **20** ×60 | 60/60 (60 approvals, 1,140 duplicates) | 165 / 306 / 540 |
| Different tickets, 5 / 10 / 20 scanners (200/400/800) | 100 % approved; 45 / 59 / 64 check-ins·s⁻¹ | 20 scanners: 157 / 286 / 416 |
| Rapid repeat, one scanner ×20 | 1 approved, 19 duplicate | 175 / 267 / 294 |
| Mixed refused cases, 20 in flight (300) | **0 wrong verdicts**, none admitted | 171 / 453 / 758 |
| Row locked 1.5 s by another transaction | waits, then approves | 1,561 ms total |
| Row locked past `innodb_lock_wait_timeout` | HTTP 500 `server_error`, **not approved**, ticket unchanged; later re-check approves | — |
| 60 requests abandoned after 2 ms | 24 had committed, 36 had not; every ticket consistent (status ⇔ exactly 0/1 success log); re-check + second pass → exactly once | — |
| Overall (3,920 requests) | invariants: 0 tickets with >1 success log, 0 checked-in without log | **123 / 273 / 456** |

Honest reading: **p95 < 500 ms was met in every sustained scenario at ≤20 concurrent requests (worst: mixed refused cases, 453 ms)**, but p99 exceeds 500 ms under 20-way contention and one scenario's p95 sits near the limit. These figures include loopback HTTP, a framework boot on every request (`php -S`, no opcache tuning) and a shared 4-vCPU machine; production PHP-FPM/opcache should be faster, but **that is unmeasured**. A single scanner hardly ever exceeds 1–2 scans/s, far below the 45–64/s sustained here. The load harness's first run was throttling itself (240/min per user) — the limit was raised to 600/min and the 429 case is handled in the UI.

## 4. What I could NOT verify (be aware)

* **Real iOS Safari and real Android Chrome were not tested.** Only Chromium with iPhone 13 / Pixel emulation and a fake camera device. No WebKit build is available in this environment. Rear-camera selection, autofocus, torch, camera permission prompt, tab backgrounding and printed-ticket scanning **must be checked on real phones** (checklist below). Torch/switch buttons appear only if the browser exposes them.
* Scanning from physical phone screens / printed paper (glare, density) — only generated QR frames were decoded.
* Production data volume, production PHP/DB configuration, production session driver, venue Wi-Fi/4G.
* Camera recovery after tab backgrounding, the torch and camera-switch buttons, and stream loss are **code-reviewed, not exercised** (Chromium's fake device has no rear/front pair, torch, or OS-level suspension).

## 5. Risks remaining

1. **Production role permissions (data, not code — I did not touch it).** The seeded `scanner` role has `tickets.update`, `tickets.view`, `orders.view`, `dashboard.view`. Code now prevents scanner-only accounts from cancelling/re-opening tickets via the scanner, but those permissions still open other admin screens (e.g. ticket edit, orders list). Recommend trimming the `scanner` role to `scanner.access` (plus whatever the dashboard link needs) via the admin UI before doors.
2. Scanner accounts **with no assigned event can scan any event** (unchanged legacy semantics). For Fracture, assign every gate account to that event; the UI then locks to it. Unassigned accounts must pick an event in the dropdown, and the server enforces that choice.
3. QR codes are the plain ticket number (not signed) — authenticity = "exists in the database". Existing QR format intentionally preserved; ticket numbers are `order(6)+item(3)+seat(2)` digits, i.e. guessable. A signed-QR scheme is the right fix but is a format change — not for event day.
4. No rate limit on the scanner **login** form (adding one risks locking out 20 devices behind one venue IP). Use strong gate passwords.
5. Pings every 20 s keep a scanner session alive indefinitely while the page is open (good for shifts, but an unattended logged-in phone does not expire).
6. `lookup_success` is logged on every lookup (kept for history/stat compatibility) → in confirm mode 2 log rows per admitted guest.
7. Landscape on a phone leaves a small camera window (header/stats take ~190 px). Portrait is the intended orientation.
8. Offline admission is **not** implemented (by design).
9. This branch is stacked on PR #160's commits; merge order: #160 first.

## 6. Recommendation: **GO — with conditions**

Server-side admission is atomic and exactly-once under 20-way contention against MariaDB; refunded/canceled/unpaid/other-event/invalid tickets were refused in every test; false greens were not reproducible (network drop, lost response, lock timeout, expired session). **Conditions before doors open:** (a) run the phone checklist below on at least one real iPhone and one Android device on venue network; (b) assign gate accounts to Fracture Halloween and trim the `scanner` role (risk 1); (c) keep **Fast check-in OFF** until step 8 of the checklist has passed on your devices; (d) keep the old process as fallback: manual number entry works without the camera. If (a) fails on iOS, it is a **NO-GO for the camera path only** — manual entry is still safe.

## 7. Manual event-day checklist (two phones: A and B, both logged in as different gate accounts)

1. On A: open `/scanner`, sign in. Page shows **Fracture Halloween**, your name, a green **Online** pill and **Camera ready**. Allow the camera.
2. Point A at a paid Fracture test ticket's QR → **VALID TICKET** → tap **CHECK IN** → green **ENTRY APPROVED**.
3. Scan the same ticket again on A → red **ALREADY USED**, with time and "by you".
4. Scan that same ticket on B → red **ALREADY USED**, "by <A's name>".
5. Scan a **refunded** ticket → red **REFUNDED**. Scan a ticket from another event → red **WRONG EVENT**. Scan any random QR (e.g. a website) → red **INVALID TICKET**.
6. Switch on **Fast check-in** on A, scan a *new* valid ticket and **hold the phone still for ~5 s** → one approval only, no repeat beeps; the counters show 1 more.
7. Put A in airplane mode, scan a valid ticket → amber **NO CONNECTION / UNVERIFIED**, never green. Turn the network back on, tap **Verify ticket status** → "VALID TICKET" (not yet in).
8. Try to *race*: point A and B at the **same unused ticket** at the same moment (count to three) → exactly one green, one red. (Do this ≥3 times with different tickets.) Only if this passes keep Fast mode ON.
9. Deny camera permission (iOS: Settings → Safari → Camera → Ask/Deny; Android: site settings) and reload → "Camera unavailable" + manual box works: type a ticket number → look-up → CHECK IN.
10. Lock the phone for 30 s / switch apps and come back → camera resumes by itself within a few seconds (tap *Try camera again* otherwise).
11. Tap the speaker button → sounds/vibration off; scan a ticket → silent. Tap again → on.
12. Rotate the phone; zoom to a printed ticket and a ticket on a phone screen — both should scan within ~1–2 s. Try the torch/switch-camera buttons if they appear.
13. Refresh the page after a check-in → "Checked in" counter is unchanged and re-scanning that ticket is still **ALREADY USED**.
14. Log in on a third device with an account assigned to a **different** event → scanning Fracture tickets there gives **WRONG EVENT**.
15. In the admin dashboard, open Scanners → your account: check-ins, duplicates and rejected counters match what you just did.
