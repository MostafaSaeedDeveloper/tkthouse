# Stage E report — Multi-Merchant Payment Routing

Branch `claude/v2-admin-settings-assignment` (nothing merged to `main`). Design of record: `docs/v2-stage-e-design.md` (updated with your approvals). Sandbox procedure: `docs/v2-payment-sandbox-runbook.md`.

## Verification
| Suite | Result |
|---|---|
| Full suite, SQLite (in-memory) | **401 tests** (397 run + 4 MySQL-only skipped) green |
| Full suite, MariaDB 10.11 (isolated local instance) | all green **except 1 pre-existing test** (`CompactTicketSelectorTest::long names…` inserts a 300-char name into a `varchar(255)` column; passes on SQLite only) |
| Concurrency on MariaDB (separate PHP processes, committed rows) | 12 parallel checkouts ⇒ exactly 4/4/4 per agent, one assignment row per order, one consumption per reservation; 9 parallel reservations spread over 3 agents with the final pointer untouched; 8 racers on one reservation ⇒ 1 order; 6 racers on one gateway transaction id ⇒ 1 attempt (unique index) |
| New tests | PaymentRoutingTest (24), PaymentAccountSwitchTest (9), CheckoutReservationTest (11), PaymentConfigurationUiTest (6), ConcurrencyMysqlTest (4) |
| Browser journey (Fake gateway only) | customer 1 → agent Ahmed → "Account A (cards only)" → paid; customer 2 (mobile) → agent Mohamed → "Account B (wallets only)" → paid; customer 3: reserved agent deactivated after the page was shown ⇒ no order, message shown after reload, buyer + attendee data kept, fresh options. Admin screens at 360/768/1024/1440: all HTTP 200, no horizontal scroll. Screens: `docs/screenshots/stage-e/` |

No real payment, gateway call, e-mail or WhatsApp message was made; nothing touched production data.

## What was built
* **Merchant account** = `integration_accounts` row (Paymob / Fawaterak / Fake-for-tests), several per provider, `supported_methods`, archive instead of delete (FK `RESTRICT` + guard).
* **Binding**: `payment_attempts.integration_account_id` + `routing_snapshot`, `orders.integration_account_id` (pinned at the first attempt/reservation). Both immutable in code (model guard); the only way to change an order's account is the audited switch.
* **Resolver** (server-side only): pinned → latest attempt's account (sticky; legacy `NULL` stays legacy) → agent's custom account → default policy. Routing off ⇒ only the default merchant account matters. Unusable agent account ⇒ visible fallback in the snapshot; unusable pinned account ⇒ new attempts refused.
* **Settlement**: gateways take credentials from the attempt's account only (Paymob create/inquiry, Fawaterak create/inquiry). Paymob callback: attempt found by our unique `merchant_order_id`, HMAC verified with **that account's** secret, only that attempt is settled. Settlement code (`settle()`: locks, amount/currency/reference/transaction-reuse, one-time ticket issuance) is unchanged.
* **Provisional reservations**: `checkout_routings` (TTL 30 min). Reserve on the payment step (idempotent per session), spread concurrent sessions by fewest live holds, never touch the final pointer; consume inside the order transaction under the `assignment_state` row lock (order created with the reserved agent + account, pointer advanced, reservation bound once via a unique column). Invalid/expired/conflicting ⇒ 409 "options changed", draft kept, flash message after reload, nothing created.
* **Switch merchant account** (`orders.reroute_payment`): live inquiry on every open/eligible attempt; refuses on paid (and settles it), unknown/error, PENDING younger than 60 min; closes older sessions as `expired` + `rerouted` but keeps them reconcilable so a late payment is matched (duplicate flagged for refund review); audit row.
* **UI**: Settings → Payment Routing (toggle, default account, unassigned policy, account status table), Users → Payment configuration (default/custom, account, allowed methods, routing flag — only for super admin / `settings.payment-accounts.manage`; no keys shown), Order → Merchant account card (+ switch form), Integrations (methods checklist, archive, Fawaterak sandbox attestation), Agent report (merchant accounts column for permitted viewers).
* Everything off by default: routing flag off, no default account, no custom accounts ⇒ **today's payment behaviour is unchanged** (the 307 original tests are untouched and pass).

## Security matrix (all covered by automated tests unless noted)
Immutable attempt↔merchant binding ✔ · callback authenticity per account ✔ (wrong/global/other-account secret ⇒ 403) · transaction ownership (another merchant's transaction is invisible/rejected) ✔ · amount & currency ✔ · no double charge (live-session reuse only on the same account; no parallel account; switch guard) ✔ · idempotency & duplicate callbacks ✔ · no duplicate tickets ✔ · safe retry (same account) ✔ · safe reassignment ✔ · safe switching ✔ · audit (snapshot, activity log, settings audit) ✔ · secrets never in pages/logs ✔ · Fawaterak locked in production without `PAYMENTS_FAWATERAK_VERIFIED` ✔ · **real Paymob/Fawaterak behaviour (HMAC portal setup, 3DS, `data.paid` semantics) — NOT verified: runbook required**.

## Findings / decisions to be aware of
1. **Paymob callback secret selection**: the account is chosen from the unauthenticated `merchant_order_id`; safe because a wrong choice fails the HMAC and settlement still needs the inquiry with that account's API key (tested).
2. **Manual methods** (Visa/Wallet staff-confirmed) are offered regardless of the account, even when online payment is blocked — as before.
3. **Round-robin fairness**: the final pointer follows completed orders, so rotation can interleave slightly; under 12 parallel checkouts it stayed perfectly balanced.
4. `RolesAndPermissionsSeeder` truncates permission tables (now MySQL-safe and includes the new permissions) — never run on production. `FawaterakPaymentMethodsSeeder` **contains a hard-coded default Fawaterak API key**: treat it as exposed and rotate it.
5. Migration rollback of Stage E drops the account-binding columns (take a backup first); the application itself never deletes them.
6. Legacy keys in `payment_methods.config`/`.env` are untouched and still used by legacy attempts; remove them only after the migration plan below is confirmed.

## Rollout plan (nothing below has been run)
**Phase 0 — before any deploy**: backup; deploy with all flags off; run migrations (additive); verify checkout + one fake/sandbox payment behave exactly as before; grant `settings.payment-accounts.*` / `orders.reroute_payment` only to the few operators.
**Phase 1 — Staging, fake**: `PAYMENTS_FAKE_ENABLED=true`; create Fake accounts; turn routing on; repeat the browser journeys (two agents, reassignment, switch, expiry).
**Phase 2 — Staging, sandbox**: sandbox credentials + `PAYMENTS_LIVE_GATEWAYS=true`; execute the runbook (Paymob, then Fawaterak); record sign-offs; keep Fawaterak production locked until then.
**Phase 3 — Production, dark**: `settings:import-legacy-credentials --dry-run`, then import (accounts arrive disabled + unverified); Test connection each; **do not** enable routing.
**Phase 4 — Production, default account only**: set one verified account as *Default merchant account* (routing still off); watch a day of real payments and the reconcile job; rollback = clear the default account (new attempts return to the legacy path; existing attempts keep their account).
**Phase 5 — Production, per-user routing**: configure 1 agent, enable routing, monitor, then widen. Rollback = switch routing off (new reservations stop; pinned orders keep their account).
**Cleanup (only after Phase 5 is stable and every open historical attempt has been checked)**: plan the removal of legacy plaintext keys separately; not part of this change.
