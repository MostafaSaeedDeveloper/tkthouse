# Final Payment QA matrix (after review) — evidence per checklist item

Legend: **A** automated (SQLite) · **M** automated on MariaDB with real parallel processes · **B** browser run (Chromium, Fake gateway / seeded test data) · nothing here touched a real provider or real money.

| # | Checklist item | Evidence |
|---|---|---|
| 1 | Refund approval permissions | A `RefundTest`: policy ON by default; create-only users cannot send; approve needs `payments.refunds.approve` (HTTP 403 + service refusal); Order Manager policy can request, cannot approve; role sync tests in `PaymentPermissionSyncTest`. B: manager forging `POST /approve` → **HTTP 403**. |
| 2 | No self approval | A: requester who also holds approve still waits (service + HTTP); explicit-grant manager cannot approve own request. B: approver's own request shows "You requested this refund, so you cannot approve it", no Approve button (desktop+mobile). M: only one approval wins. |
| 3 | Duplicate refund protection | A: same idempotency key → one refund/one provider call; one in-flight refund per payment. M: 8 parallel full refunds → 1; parallel partials never exceed captured; same form ×8 → 1; **parallel approvals+submits → exactly one provider send, never before approval**. |
| 4 | Timeout / unknown | A: provider timeout → `unknown`, never auto re-sent, resolved only by inquiry/evidence; reconciliation command is a no-op for requested/awaiting refunds. |
| 5 | Manual Fawaterak refund evidence | A: evidence + provider reference mandatory; Fawaterak refund webhook is only an unauthenticated hint. **B (new): full manual workflow** — manager requests → waits → approver approves with note → *Mark as submitted* → confirm with 2-char evidence is **refused** → confirm with provider reference + evidence → *Succeeded*; reference visible (desktop+mobile). |
| 6 | Payment attempt ↔ merchant account | A: refunds/inquiries/webhooks always use the attempt's own account; reassignment never moves it; Fawaterak invoice of account B cannot settle account A. B: Order Details shows the account only to permitted users (manager: not shown). |
| 7 | Invalid webhook signatures | A: Paymob invalid HMAC → 403 + event logged as `invalid`; Fawaterak bad hash → informational/rejected, no state change; wrong-account signature rejected. |
| 8 | Legacy transactions | A: orders without attempts / attempts without account or `internal_reference` render and use the manual workflow; `payments:validate` is read-only; backfill touches only the new column; seeders no longer delete legacy codes (`SecretHygieneTest`). |
| 9 | No double ticket issuance | A: duplicate callbacks issue tickets once; refund never changes order status or tickets; failed issuance repaired without duplicates. |
| 10 | No excess refund | A: balance rules, minor-unit exactness, malformed amounts refused. B: refunding 999 on 150 refused with the balance message. M: parallel races. |
| 11 | Agent order scoping | A: Order Agent policy sees only assigned orders' transactions (404 elsewhere), no refund rights until granted, cannot refund another agent's order; assignment scoping on transactions/refunds/reports. |
| 12 | Audit logs | A: `payments` activity per transition with `requested_by`, `requested_at`, `approved_by`, `approved_at`, `reason`, `note`, `approval_required`; settings changes audited by the settings service. |
| 13 | Production refund gate disabled | A: config default `PAYMENTS_REFUNDS_LIVE=false`, `.env.example`/`phpunit.xml` do not enable it; with the gate off, a fully approved Paymob refund stays a **manual** refund and **no API call** is made. |
| 14 | Payments screens on mobile (360 px) | B: refunds list/detail, transaction, overview, providers, methods, webhooks, routing — no horizontal scroll; screenshots in `docs/screenshots/payments-v2/approval-manual/`. Earlier 4-width pass in `docs/screenshots/payments-v2/`. |

## Other fixes verified
* Secrets: `SecretHygieneTest` (leaked-key fingerprint absent everywhere; seeders have no secret-looking literals; seeders never overwrite/delete).
* MariaDB `CompactTicketSelectorTest › long names`: root cause = the test used a 271-char ticket name, but ticket names are limited to 255 (admin validation + `varchar(255)`). Test now uses the longest legal name. A **real latent bug** was found alongside: the order-item snapshot `"Event - Ticket"` (up to 513 chars) would be rejected by MySQL; it is now capped at 255 (`OrderPlacementService::snapshotName`, unit-tested). **No column was widened.**

## Results
| Suite | Result |
|---|---|
| SQLite (in-memory) | **458 passed, 8 skipped** (the skips are the MySQL-only concurrency tests) |
| MariaDB 10.x `tkt_test` (includes real parallel-process tests) | **466 passed, 0 failed** |

## Not verified (needs real sandbox — see runbook)
Real Paymob/Fawaterak payment, signed webhook delivery, real refund/void/inquiry responses, merchant-account routing against real portals. Refund live stays disabled.
