# Digital Tickets — QA report

Environment: local Laravel app (sqlite, seeded demo data), real browser (Playwright/Chromium; mobile = iPhone 13 emulation, desktop = 1280px). Nothing touched production or real tickets. Not tested on physical iPhone/Android devices.

## Automated tests (PHPUnit)
- New: `DigitalTicketsTest` (29) and `DigitalTicketsAccessTest` (14), all passing.
- Updated because behaviour intentionally changed (no ticket PDF; canceled tickets refused at the gate): `CustomerPortalTest`, `RefundEndToEndJourneyTest`, `RefundTicketIntegrationTest`, `OrderInvoiceTest`.
- Full suite on this branch: **724 passed, 58 failed, 11 risky, 19 skipped**.
- Baseline (parent commit, same machine): **58 failed**. Failing lists diffed test by test: **0 new failures**. The 58 are pre-existing (e.g. express-checkout tests still expect the old flow and now hit "Please confirm this email with a code, or use your account email"); unrelated and untouched.

## Browser QA (`docs/screenshots/digital-tickets/qa-results.json`)
77 checks, all passing. One (duplicate check-in POST) failed in the first full run because of a bug in the QA script, and was re-run separately and passes (details in the JSON).

| Area | Check | Result |
|---|---|---|
| desktop | Paid order: My Tickets shows all 4 groups | ✅ |
| desktop | My Tickets lists never contain a QR image or token | ✅ |
| desktop | Used tab shows the used ticket | ✅ |
| desktop | Past tab shows the ended event | ✅ |
| desktop | Canceled/Refunded tab shows full + partial refunds as Refunded | ✅ |
| desktop | Hidden QR: locked placeholder with QR LOCKED + reveal time | ✅ |
| desktop | Hidden QR: real token / real QR svg absent from HTML | ✅ |
| desktop | Hidden QR: ticket still shows valid + attendee + event | ✅ |
| desktop | Order tickets can be paged (Ticket 1 of 3) | ✅ |
| desktop | Countdown renders | ✅ |
| desktop | No horizontal scroll on the ticket page | ✅ |
| desktop | QR endpoint while hidden: state=locked, svg=null | ✅ |
| desktop | Old PDF link of a digital ticket redirects (no PDF) | ✅ |
| desktop | Public short link for a digital ticket is gone | ✅ |
| desktop | Visible QR: full QR shown, no lock | ✅ |
| desktop | Legacy ticket opens digitally with its QR | ✅ |
| desktop | Full refund: Refunded, no QR | ✅ |
| desktop | Partial refund: refunded ticket has no QR | ✅ |
| desktop | Partial refund: remaining ticket still valid with QR | ✅ |
| desktop | Used ticket shows Checked in, no QR | ✅ |
| desktop | Another customer's guest-checkout ticket link is forbidden | ✅ |
| desktop | Another person's guest-list QR endpoint is forbidden | ✅ |
| desktop | Invoice PDF unaffected | ✅ |
| desktop | Guest checkout: signed-out link lands on the access page | ✅ |
| desktop | Wrong code is rejected | ✅ |
| desktop | Guest checkout: code opens the ticket they were sent | ✅ |
| desktop | Gifted ticket: holder without an account opens their own ticket | ✅ |
| desktop | Gifted: cannot open the buyer's other ticket | ✅ |
| desktop | Guest list: holder opens the digital ticket after verifying | ✅ |
| desktop | Guest list holder cannot open a customer ticket | ✅ |
| mobile | Paid order: My Tickets shows all 4 groups | ✅ |
| mobile | My Tickets lists never contain a QR image or token | ✅ |
| mobile | Used tab shows the used ticket | ✅ |
| mobile | Past tab shows the ended event | ✅ |
| mobile | Canceled/Refunded tab shows full + partial refunds as Refunded | ✅ |
| mobile | Hidden QR: locked placeholder with QR LOCKED + reveal time | ✅ |
| mobile | Hidden QR: real token / real QR svg absent from HTML | ✅ |
| mobile | Hidden QR: ticket still shows valid + attendee + event | ✅ |
| mobile | Order tickets can be paged (Ticket 1 of 3) | ✅ |
| mobile | Countdown renders | ✅ |
| mobile | No horizontal scroll on the ticket page | ✅ |
| mobile | QR endpoint while hidden: state=locked, svg=null | ✅ |
| mobile | Old PDF link of a digital ticket redirects (no PDF) | ✅ |
| mobile | Public short link for a digital ticket is gone | ✅ |
| mobile | Visible QR: full QR shown, no lock | ✅ |
| mobile | Legacy ticket opens digitally with its QR | ✅ |
| mobile | Full refund: Refunded, no QR | ✅ |
| mobile | Partial refund: refunded ticket has no QR | ✅ |
| mobile | Partial refund: remaining ticket still valid with QR | ✅ |
| mobile | Used ticket shows Checked in, no QR | ✅ |
| mobile | Another customer's guest-checkout ticket link is forbidden | ✅ |
| mobile | Another person's guest-list QR endpoint is forbidden | ✅ |
| mobile | Invoice PDF unaffected | ✅ |
| mobile | Guest checkout: signed-out link lands on the access page | ✅ |
| mobile | Wrong code is rejected | ✅ |
| mobile | Guest checkout: code opens the ticket they were sent | ✅ |
| mobile | Gifted ticket: holder without an account opens their own ticket | ✅ |
| mobile | Gifted: cannot open the buyer's other ticket | ✅ |
| mobile | Guest list: holder opens the digital ticket after verifying | ✅ |
| mobile | Guest list holder cannot open a customer ticket | ✅ |
| admin | Admin "Show QR": QR appears on the open ticket page (no reload, same place) | ✅ |
| admin | Admin "Hide QR": ticket locked again | ✅ |
| admin | Edit Event shows QR Visibility + Entry Availability with who/when | ✅ |
| reveal | Scheduled reveal: starts locked with a countdown | ✅ |
| reveal | Scheduled reveal: QR appears by itself at the set time (no reload) | ✅ |
| scanner | Gates closed (scheduled entry): lookup is refused with GATES NOT OPEN | ✅ |
| scanner | Admin opens gates: ticket is found and can be checked in | ✅ |
| scanner | Check-in succeeds | ✅ |
| scanner | Duplicate scan: shown as already Checked In, no check-in button | ✅ |
| scanner | Duplicate check-in POST is refused (ALREADY CHECKED IN) | ✅ |
| scanner | Fully refunded ticket (by its QR) is refused | ✅ |
| scanner | Partially-refunded order: the refunded ticket is refused | ✅ |
| scanner | Partially-refunded order: the valid ticket is admitted | ✅ |
| scanner | Legacy ticket (old bare-number QR) is admitted | ✅ |
| scanner | Guest-list ticket is admitted | ✅ |
| scanner | Forged QR (a guessable ticket number scanned as a QR) is rejected | ✅ |
| scanner | A real token QR scans (camera path) | ✅ |

## E-mail code security (existing `EmailOtpService`, used by the new ticket-access page)
- 6 digits, stored hashed; **expires after 10 min**; **single use**; **max 5 wrong tries per code** (then dead, even for the right code); **bound to the browser session that asked for it**; 30 s resend cooldown; 5 codes/hour per address, 30/hour per IP; 10 sends/min/IP on the access endpoint. All covered by tests.
- The send endpoint answers identically whether or not the address has an account or a ticket. A holder account is created only for an address that really holds a ticket, and nothing is granted until the code is entered.
- A signed-in person sees only tickets of their own orders or issued to their *verified* address; another ticket's page, `/qr` and download are 403 (orders, gifted, guest list); an unverified address sees nothing.

## Ways to a hidden QR (all tested)
Ticket page, `/qr` JSON, old `/download` link, `/t/{number}` short link, signed public PDF link, My Tickets, order page and list: none returns the token or a QR image while hidden. A guessable ticket number never leads to a QR for a new ticket. The locked placeholder is one fixed picture (constant seed), identical for every ticket and not decodable.

## Residual notes
- Tickets issued before this change keep their bare-number QR (already in PDFs/WhatsApp). They still scan and are affected only by the gate and canceled/refunded/used rules. Their old public PDF link still works but respects QR visibility.
- Staff-only backend pages (admin ticket view / PDF download) still render the QR for authorised staff.
