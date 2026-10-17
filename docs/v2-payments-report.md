# Unified Payment Management, Provider Webhooks & Refunds — final report (rev. 2 after review)

> **Rev. 2 changes (review follow-up):** second-person refund approval is now **ON by default** with no self-approval and a gate evaluated at send/confirm time; additive production permission policy (`payments:sync-permissions`); the leaked Fawaterak key was removed from source and seeders made non-destructive (`docs/security-secret-rotation.md` — **rotation is still required**); the MariaDB long-name failure is fixed; manual-refund workflow and mobile QA were run in the browser. Deployment/rollback: `docs/v2-payments-deployment.md`. Sections 3, 6 and 8 below are superseded by `docs/v2-payments-qa-matrix.md`.

Branch `claude/v2-admin-settings-assignment` · five phases, one commit each · **nothing merged to main, no production credential touched, no real refund performed.**

## 1. What shipped (by phase)
| Phase | Delivered |
|---|---|
| 1 Settings | Settings → Payments: **Payment Methods**, **Providers & Merchant Accounts** (reuses `integration_accounts`), **Webhooks**, **Payment Routing**. Old URLs (`/dashboard/payment-methods`, `integrations?provider=`) keep working. Integrations is now non-financial services only. Method sort order, duplicate prevention, masked legacy keys, "default account" badge, per-account "refunds verified" control. Audit: `v2-payments-audit.md`. |
| 2 Tracking & webhooks | Payments → **Overview / Transactions / Refunds**. `internal_reference` per attempt (separate from provider ids), provider order/reference ids, `paid_at`, `last_checked_at`. `webhook_events` (encrypted, whitelist, unique dedupe, signature + processing status), Webhooks screen with retry, "Configured" vs "Verified" per account. Paymob HMAC per attempt's own account; Fawaterak hash rules exactly as documented. Webhooks never decide "paid" — only `PaymentService::settle()` after server-side inquiry. |
| 3 Order Details | **Payment Information** card (provider, merchant account for permitted users, method, order vs payment vs provider references with copy, paid amount/date, status, last verification, *Check status*), **Payment Attempts History**, **Refund History**. Agents only see their own orders; merchant account hidden without permission. |
| 4 Refunds | `payment_refunds` (minor units, unique idempotency key, mode api/manual, type full/partial/void, statuses requested/processing/succeeded/failed/rejected/unknown, optional second-person approval). Paymob via API; Fawaterak **manual/external only** (no refund-creation API documented). Atomic claim before sending, row-locked balance, one in-flight refund per payment, **never auto-resent after timeout/unknown**, reconciliation job every 10 min, refund state kept separate from order/ticket state. Capabilities: `v2-payment-capabilities-matrix.md`. |
| 5 Reports & QA | Overview report (collected, refunded, net, pending, failed, partial, in-progress; by provider / method / merchant account), agent report refunded/net columns (attributed by `sold_by` snapshot), `payments:validate` (read-only; `--backfill-references` fills only missing `internal_reference`), runbook updated, browser QA. |

Also: dashboard button-colour bug fixed (`Create event` / `Export CSV` contrast), table `sr-only` overflow and mobile KPI overflow fixed.

## 2. Permissions (server-side enforced)
`payments.transactions.view|inquire`, `payments.refunds.view|create|approve`, `payments.accounts.view|manage`, `payments.webhooks.view`. The migration copies them only to holders of the equivalent old permission; super-admin gets all. Agents are additionally scoped by order assignment (`OrderAccess`) and cannot refund others' payments. Optional setting *Require a second person to approve refunds*.

## 3. Verification performed
| Check | Result |
|---|---|
| Full suite, SQLite | **439 passed, 7 skipped** (before the last UI-only commit; no PHP logic changed since) |
| Full suite, MariaDB (`tkt_test`, includes real parallel-process refund/routing concurrency) | **445 passed, 1 failed** — `CompactTicketSelectorTest › long names` (varchar(255) on MySQL), pre-existing and unrelated to payments |
| Browser journey (Fake gateway, Chromium): customer pays → Order Details Payment Information → full UI refund → processing → *Check status* → succeeded; over-refund (999) refused with balance message; balance 100.00 left | ✅ |
| Layout at 360 / 768 / 1024 / 1440 for 9 payment screens, horizontal scroll | none |
| HTTP: fakes only; Paymob/Fawaterak never contacted | ✅ |
Screenshots: `docs/screenshots/payments-v2/` (40 files).

## 4. Security matrix
| Concern | Control |
|---|---|
| Secrets | encrypted at rest, masked after save, blank field keeps stored value, never on `users`, never logged; webhook payloads stored encrypted with a key whitelist (no PAN/CVV) |
| Forged webhook | Paymob HMAC with the attempt's own account secret; Fawaterak documented hash; unauthenticated types informational only |
| Webhook ≠ payment | only `settle()` after server-to-server inquiry marks paid |
| Replay | unique `(provider, dedupe_key)`; settle idempotent |
| Double refund | row lock + one in-flight + unique idempotency key + atomic claim `WHERE status='requested'` + minor-unit integer balance; proven with real concurrent processes on MariaDB |
| Over-refund | refused before any provider call |
| Timeout | `unknown`; never auto-resent; staff inquiry/evidence only |
| Wrong account | refunds and inquiries always use the original attempt's account |
| Production safety | `PAYMENTS_REFUNDS_LIVE` default **off**; per-account "refunds verified" attestation (reset when credentials change); live gateways off outside production unless explicitly enabled |
| Authorisation | per-action permissions + assignment scope; audit log on every refund transition |

## 5. Legacy data plan
Additive migrations only (`2026_12_01…03`); no table/column dropped, no method renamed/deleted (used codes cannot be deleted). Old attempts keep their gateway data; `internal_reference` is NULL for them until backfilled.
1. `php artisan payments:validate` (read-only dry run) on a production copy; review output.
2. Optional: `php artisan payments:validate --backfill-references`.
3. Legacy orders show Payment Information with whatever was stored; no refund is possible on them unless the original account is identifiable and verified.

## 6. Sandbox Verification & Production Readiness
| Area | Status |
|---|---|
| Paymob payments + HMAC | code + mocks verified; **real sandbox run not yet done** (runbook §1) |
| Paymob webhooks | implemented per documented spec; **not Verified** on any real account |
| Paymob refund / void API | implemented against the documented endpoint; **never called for real**; refund-creation response shape must be confirmed in sandbox (runbook §4) |
| Fawaterak payments/webhooks | implemented per documented hash rules; still gated by `PAYMENTS_FAWATERAK_VERIFIED` in production |
| Fawaterak refunds | **manual workflow only** by design |
| Known issue | a Fawaterak key is hard-coded in `FawaterakPaymentMethodsSeeder` — **rotate it** and remove from source |
| Known issue | 1 pre-existing MySQL-only test failure (see above) |

**Not ready for Production refunds.** Production readiness requires: permissions approved by the business, sandbox runbook §1–§4 executed and signed, Fawaterak key rotated.

## 7. Rollout
1. Staging with Fake gateway (done locally) → 2. Staging with provider **sandbox** accounts, `PAYMENTS_LIVE_GATEWAYS=true`, run the runbook, mark webhooks configured, record verification → 3. Production deploy with refunds **off** (`PAYMENTS_REFUNDS_LIVE` unset) → 4. Production: default account only → 5. per-user routing → 6. enable refunds only after §4 sign-off, one account at a time (attest on the account, then set the env flag).
Rollback: all migrations have `down()`; turning `PAYMENTS_REFUNDS_LIVE` off immediately blocks the API path (manual workflow remains).

## 8. Decisions needed from you
1. Should refunds require second-person approval in production (setting exists, default off)?
2. Who gets `payments.refunds.create` / `approve` / `accounts.manage` on production roles?
3. Approve a Pull Request into `main`? (I have **not** opened one.)
