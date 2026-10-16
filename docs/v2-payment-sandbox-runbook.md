# Payment sandbox runbook (multi-merchant) — sign-off required before Production

Nothing here has been executed: no sandbox credentials were available. Automated tests use mocked gateways only.
**Production enablement of a merchant account stays blocked until the matching section is signed off.** Never use live cards, live wallets or the production database.

## 0. Preconditions (Staging)
* Staging database is a copy **without** real customers' contact data, or mail/WhatsApp are blocked (`NOTIFY_MAIL_ALLOWLIST`, WhatsApp toggle off).
* `PAYMENTS_LIVE_GATEWAYS=true` **only** together with sandbox credentials. `PAYMENTS_FAKE_ENABLED=false`.
* Run `php artisan settings:import-legacy-credentials --dry-run`; do **not** import production keys into Staging.
* Create two sandbox merchant accounts per provider in Settings → Integrations (names: `Paymob Sandbox A/B`, `Fawaterak Sandbox A/B`), tick the payment methods each supports, **Test connection**, enable.
* Two agents with a custom account each (Users → Payment configuration); Settings → Payment Routing: routing ON, default account = a third sandbox account.

## 1. Paymob (per account A and B)
1. In each Paymob sandbox account's portal set the **transaction processed callback** and the **transaction response callback** to `https://<staging>/payments/paymob/callback` (same URL for both accounts). Copy that account's **HMAC secret** into the account's `HMAC secret` field (re-test the connection afterwards).
2. Cases, executed once per account and recorded (order #, attempt id, account, timestamps, screenshots):
   | # | Case | Expected |
   |---|---|---|
   | P1 | Card success without 3DS | attempt `paid`, order `paid`, tickets issued once, `routing_snapshot` names the account |
   | P2 | Card with 3DS success | same; callback HMAC verifies with **this** account's secret |
   | P3 | 3DS failure / declined | attempt `failed`, order stays payable, retry opens a new attempt on the **same** account |
   | P4 | Customer closes the tab after paying | reconcile job settles it using the attempt's account within ~2 minutes |
   | P5 | Replay the callback 3× (curl with the original body + hmac) | no second ticket, outcome `already_paid` |
   | P6 | Send A's callback body signed with B's secret | HTTP 403, nothing changes |
   | P7 | Tamper `amount_cents` in a signed body | rejected; the server-to-server inquiry decides, amount mismatch ⇒ no payment |
   | P8 | Reassign the order to the other agent during payment, then pay | still settled by the original account; order keeps its pin |
   | P9 | Switch merchant account while a payment is in progress (<60 min) | refused with the in-progress message |
   | P10 | Switch after the first session was abandoned >60 min, then pay the NEW session | order paid via B; old A session still reconciles; if paid late: flagged `duplicate_payment`, no double tickets |
   | P11 | Disable account A with an open attempt, then pay it | settles (disabled ≠ unreconcilable); no new attempts on A |
   | P12 | Pay after the order expired / was cancelled | recorded for refund review, no tickets |
3. Confirm in the portal that each payment appears under the intended merchant and that the amount/currency match.

## 2. Fawaterak (per account A and B) — nothing may be assumed
1. From the sandbox, capture a real `invoiceInitPay` response and a real `getInvoiceData/{id}` response **before and after** paying (store them, secrets redacted). Confirm: the endpoint path, auth (Bearer), that `data.invoice_id`, `data.total`, `data.currency`, `data.paid`, `data.paid_at` exist, and exactly which values `paid` takes for paid / unpaid / failed / refunded.
2. If any field differs from `FawaterakGateway`, fix the gateway **first** (add a mapping test from the captured JSON), then continue.
3. Run cases P1, P3, P4, P5, P8–P12 with the Fawaterak equivalents (there is no HMAC: authenticity is the server-to-server inquiry + the order/token in the return URL). Add: **F1** an invoice paid under account B must never settle an attempt of account A (inquiry with A's key returns not found).
4. On success a person records "Sandbox verification" on each account (Integrations → Record sandbox verification, with the run reference). Production additionally requires `PAYMENTS_FAWATERAK_VERIFIED=true` set by an operator.

## 3. Evidence & sign-off
Keep a table per provider (cases, result, evidence link, tester, date). Approver signs: ☐ Paymob ☐ Fawaterak. Only then plan Production (see the rollout plan in `docs/v2-stage-e-report.md`).
