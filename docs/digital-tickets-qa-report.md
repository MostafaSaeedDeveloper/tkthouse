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


---

# Part 2 — Buyer vs ticket holder (multi-ticket ownership)

Scenario run end to end: Ahmed (buyer, account) pays for 3 tickets — Ahmed, Mohamed, Omar, three different e-mails.

## What changed
- **Buyer** keeps the order: order page lists every ticket with its holder, e-mail, status and invitation status, **View ticket**, **Resend link**, **Edit holder**; invoice and payment details are visible only to the buyer.
- **Holders** get their own e-mail ("View my ticket"), open it after an e-mail code (no account needed, no buyer login) and see **only their ticket**: no order number, payment, buyer or other holders; the buyer's order/invoice do not exist for them.
- **My Tickets** = tickets assigned to you (never listed twice). Tickets you bought for others are listed under My Orders; My Tickets shows a pointer ("You also bought N tickets for other people"). The buyer's e-mail button opens the order's own list (all its tickets).
- **E-mails:** buyer = one confirmation (+ invoice PDF only); each other holder = one private e-mail (same address on several tickets = one e-mail); a buyer who is also a holder gets no duplicate. No PDF, no QR, no order/payment data in holder e-mails.
- **Edit holder / Resend** (buyer only): name change (free), e-mail change = *move* (max 3 per ticket): the old address loses access immediately, the new one is invited, the **QR code is not regenerated**, everything is logged (who, when, old/new). Refused for used, canceled and refunded tickets and for guest-list tickets. Resend: 3 per ticket per hour.
- Checkout now says each attendee receives their own ticket at their e-mail.
- Migration `2026_12_23_000001` adds `issued_tickets.holder_email_changes`.

## Backend tests
New `MultiTicketOwnershipTest` (16 tests, 182 assertions): e-mail recipients and content, same-address grouping, buyer vs holder views, isolation (links, `/qr`, order, invoice, resend/edit), two orders for one holder, guest buyer buying only for others, e-mail move (old address revoked incl. an open session, new invited, QR unchanged, audit), name-only change, move limit, used/canceled/refunded blocks, resend limit, validation, first check-in wins, partial refund, QR visibility for both, invoice PDF.
Full suite: **742 passed, 58 failed (the same 58 as the baseline), 0 new failures.** Tests updated for the new My Tickets meaning: `CustomerPortalTest`, `DigitalTicketsTest`, `DigitalTicketsAccessTest` (fixtures now use "holder = buyer").

## Browser QA (desktop + mobile emulation) — `docs/screenshots/digital-tickets/qa-multi-results.json`: 44/44 passed
| Area | Check | Result |
|---|---|---|
| desktop | Buyer: order page lists all 3 holders with names + e-mails | ✅ |
| desktop | Buyer: each ticket has View ticket, Resend link, Edit holder and an invitation status | ✅ |
| desktop | Buyer: My Tickets shows only their own ticket (no duplicates) + pointer to the 2 bought for others | ✅ |
| desktop | Buyer: the e-mail's order list shows all 3 tickets | ✅ |
| desktop | Holder: his private link asks him to confirm his e-mail | ✅ |
| desktop | Holder: after the code his own ticket opens with the QR | ✅ |
| desktop | Holder: no order number, payment, buyer name or other holders on his ticket | ✅ |
| desktop | Holder: another holder's ticket (by link) is forbidden | ✅ |
| desktop | Holder: buyer's order and invoice do not exist for him | ✅ |
| desktop | Holder: My Tickets lists only his ticket | ✅ |
| desktop | Holder 2: Omar opens his ticket | ✅ |
| desktop | Buyer: moving the ticket shows a clear confirmation and the new e-mail | ✅ |
| desktop | New holder e-mail was sent an invitation; the old address was not re-mailed | ✅ |
| desktop | Old address loses access right away (403) | ✅ |
| desktop | New address opens the ticket after its own code; same QR (not regenerated) | ✅ |
| desktop | Buyer: Resend link confirms | ✅ |
| desktop | QR hidden by the event: holder and buyer both see QR LOCKED, token absent | ✅ |
| scanner | Mohamed's ticket is found and admitted | ✅ |
| scanner | Check-in succeeds | ✅ |
| scanner | Second scan: already Checked In, cannot be checked in again | ✅ |
| scanner | Holder and buyer both see the ticket as Used (no QR) | ✅ |
| scanner | Used ticket cannot be edited or re-sent | ✅ |
| refund | Partial refund: the refunded holder sees Refunded, no QR | ✅ |
| refund | Partial refund: the buyer's own ticket keeps working | ✅ |
| refund | Order page shows the refunded ticket as Refunded with no edit tools | ✅ |
| scanner | Refunded ticket is refused at the gate | ✅ |
| invoice | Invoice PDF still downloads for the buyer | ✅ |
| mobile | Buyer: order page lists all 3 holders with names + e-mails | ✅ |
| mobile | Buyer: each ticket has View ticket, Resend link, Edit holder and an invitation status | ✅ |
| mobile | Buyer: My Tickets shows only their own ticket (no duplicates) + pointer to the 2 bought for others | ✅ |
| mobile | Buyer: the e-mail's order list shows all 3 tickets | ✅ |
| mobile | Holder: his private link asks him to confirm his e-mail | ✅ |
| mobile | Holder: after the code his own ticket opens with the QR | ✅ |
| mobile | Holder: no order number, payment, buyer name or other holders on his ticket | ✅ |
| mobile | Holder: another holder's ticket (by link) is forbidden | ✅ |
| mobile | Holder: buyer's order and invoice do not exist for him | ✅ |
| mobile | Holder: My Tickets lists only his ticket | ✅ |
| mobile | Holder 2: Omar opens his ticket | ✅ |
| mobile | Buyer: moving the ticket shows a clear confirmation and the new e-mail | ✅ |
| mobile | New holder e-mail was sent an invitation; the old address was not re-mailed | ✅ |
| mobile | Old address loses access right away (403) | ✅ |
| mobile | New address opens the ticket after its own code; same QR (not regenerated) | ✅ |
| mobile | Buyer: Resend link confirms | ✅ |
| mobile | QR hidden by the event: holder and buyer both see QR LOCKED, token absent | ✅ |

## Remaining notes / risks
- **A moved ticket keeps the same QR** (by requirement). Someone who already saw the QR (the previous holder) could still present a screenshot; the first successful check-in wins and then it is Used for everyone. If you want moves to invalidate previous screenshots, the QR token would have to be rotated on move; that was deliberately not done.
- "Link sent" for a holder whose e-mail equals the buyer's means "covered by the booking-confirmation e-mail".
- Mail delivery in QA was verified by recipient through the log mailer, not by a real inbox; content is covered by PHPUnit.
- Not tested on physical iPhone/Android devices.


---

# Part 3 — QR Release e-mails (Test / Live), holders managed by staff only

> Note: `QR RELEASE.pdf` was not available in the working environment, so this was built from the written brief. If the PDF contains extra scenarios, send it and they will be checked against this implementation.

## Behaviour
- **Who is notified:** only tickets whose QR was **locked when issued** (`qr_locked_at_issue`) and later became unlocked, once. Tickets unlocked from the start (incl. bought after the QR opened) get the normal booking e-mail only. Tickets that existed before this release are never eligible (default `false`), so deploying sends nothing.
- **Per-ticket state** (`issued_tickets.qr_notice_*`): `not_eligible` (still waiting, or unlocked from the start, or skipped) → `pending` → `sent` | `failed`. The admin summary separates *waiting for reveal* from *failed*. `sent` is final: scheduler ticks, Show/Hide/Show, re-activation never notify it again; only `failed` can be retried (admin, one button, failed only).
- **Recipient = ticket holder e-mail.** One e-mail per recipient per event with all of *their* tickets (a QR PNG each, holder name, type), event details and VIEW YOUR TICKETS. No order, payment or other people's tickets. A buyer who bought for other people gets nothing extra for those tickets (they are in My Orders). *(Decision to confirm.)*
- **Performance:** Show / the scheduler only mark tickets and queue `SendQrReleaseBatch`; the job handles 50 recipients per run and chains itself. Checkout, scanner and the Show action never wait on e-mail. Scheduler: `tickets:release-qr` every minute (needs the normal `schedule:run` cron and a queue worker).
- **No duplicates / crashes:** tickets are *claimed* atomically before sending (two jobs can't mail the same ticket), a lock per recipient prevents parallel sends, and a claim left by a dead worker becomes `failed — delivery unknown` after 10 min instead of being re-sent blindly (the e-mail may already have left); an admin retries after checking. A message blocked by the staging allowlist is recorded as failed, not sent.
- **Scanner unchanged:** an e-mailed QR is still refused if refunded / canceled / used, and before Entry Availability opens (tested).
- **Test vs Live (per event, default TEST):**
  - TEST: nothing reaches customers; real QR visibility keeps working for customers as before. A **sandbox** (3 sample tickets stored on the event) runs the same rule: Show/Hide/"ticket bought now"/Retry/Reset, and can send **[TEST]** e-mails (sample data, QR not valid for entry) only to an address you type. Real attendee addresses are refused (your own address is allowed). Real tickets and the real QR/gate are never touched.
  - LIVE: activated deliberately: **preview** of tickets/recipients that would be e-mailed now, a **choice** — *Skip* the already-eligible tickets (only future unlocks are notified) or *Notify* them in the background — plus a confirmation tick. Going back to TEST pauses sending; going Live again shows the preview and asks again, so old notifications never go out suddenly.
  - Permission `events.qr-notifications.manage` (migration grants it to roles that had `events.qr-visibility.manage`, and super admins). Changes are logged (who/when/choice/preview).
- **Holder changes are staff-only now.** The buyer's Edit holder / Resend link were removed (routes gone). Staff change name/e-mail from the admin ticket page: the old e-mail loses access, the new one is invited, the QR is unchanged, logged; canceled/refunded tickets cannot be reassigned; online tickets must keep an e-mail. The buyer's order page says "contact support".

## Tests
New `QrReleaseNotificationsTest` (33 tests): eligibility, tickets bought after opening, old tickets, Test mode sends nothing, queued (not inline) sending, grouping, Hide/Show/Hide/Show, scheduled reveal + repeated scheduler, **1,000 tickets with 250 already notified** (750 notified once each, one e-mail per recipient, originals untouched), summary counts, failures + retry-failed-only, dead worker / live worker claims, two workers, allowlist, full & partial refunds / canceled / used, refund after queueing, per-holder content (no order/payment/others), QR PNG per ticket, scanner rules for e-mailed QRs, Go Live permission/confirmation/choice, skip / notify / hidden activation, pause & re-activate, admin UI, sandbox (never touches real data), sample e-mail safety, controller validation, buyer-bought-for-others. `MultiTicketOwnershipTest` reworked for staff-only edits.
Full suite: **0 new failures** vs the baseline (the same 58 pre-existing failures).

## Browser QA — `docs/screenshots/digital-tickets/qa-release-results.json`: 18/18 passed
| Area | Check | Result |
|---|---|---|
| test | New events start in TEST MODE; summary + Go Live preview visible | ✅ |
| test | Preview: QR hidden now, tickets wait for the reveal (nothing sent at activation) | ✅ |
| test | Sample e-mail goes only to the test address, marked [TEST] | ✅ |
| test | No customer received a QR e-mail | ✅ |
| sandbox | Show QR (test): sample tickets become Sent, grouped per recipient | ✅ |
| sandbox | Hide then Show (test): nothing is sent again | ✅ |
| sandbox | Ticket bought after the QR opened gets no release e-mail | ✅ |
| sandbox | Real tickets and real QR visibility are untouched by the sandbox | ✅ |
| test | Still no real customer e-mail after all the sandbox actions | ✅ |
| test | Real Show QR in Test mode shows the QR to customers but e-mails nobody | ✅ |
| live | Preview shows the recipients that WOULD be e-mailed (3 tickets / 3 recipients) | ✅ |
| live | Going Live needs the confirmation checkbox | ✅ |
| live | Live with "Skip": no old notification is sent, tickets are shown as Skipped | ✅ |
| live | Show QR in Live mode e-mails the newly eligible recipient ONCE with both tickets in one message | ✅ |
| live | Skipped tickets were still not e-mailed | ✅ |
| live | Hide / Show twice: no e-mail on Hide and no repeat on Show | ✅ |
| live | Summary shows Sent = 2 and Failed = 0 | ✅ |
| live | No bulk-send button anywhere | ✅ |

## Remaining notes / risks
- Production needs `php artisan schedule:run` every minute and a running `queue:work` (the app already uses the database queue and schedule); `gd` is needed for the QR images (without it the e-mail still carries the VIEW YOUR TICKETS button).
- Delivery is at-least-once only for the "claimed but result not recorded" window: that case is surfaced as *failed – delivery unknown* rather than re-sent.
- A moved ticket keeps the same QR (as before), and an e-mailed QR can be forwarded by its recipient; the first successful scan wins.
- Real inbox rendering (Gmail/Outlook/Apple Mail) was not tested; the layout reuses the existing e-mail template and PNG images are embedded as CID attachments.
- Not tested on physical devices; WhatsApp is out of scope in this phase.

## Defaults ("as if nothing changed")
Every event — new, existing (migrations default the new columns) or duplicated — starts with: QR visibility **Show Immediately** (QR visible), Entry Availability **Always open**, no manual overrides, QR release e-mails in **Test mode (nothing is sent to customers)**, empty sandbox. Tickets are only ever marked as release-eligible if the QR was hidden when they were issued, so with the defaults nobody is ever e-mailed. Tested in `test_a_new_event_behaves_exactly_as_before_until_an_admin_changes_the_settings`, `test_even_live_mode_sends_nothing_for_an_event_whose_qr_was_never_hidden` and `test_a_duplicated_event_starts_with_the_defaults_not_the_live_settings_of_the_original`.
