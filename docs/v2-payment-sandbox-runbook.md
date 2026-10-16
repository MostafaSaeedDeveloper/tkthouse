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

## 3. Webhooks (Payments V2) — verify, do not assume
**Configured ≠ Verified.** A URL being pasted into a portal proves nothing. An account's webhook is "Verified" only after a real, authenticated event has been received for it (Settings → Payments → Webhooks shows the event log and the last verified event per account). "Mark configured" is a human statement, recorded with who/when; it never turns the account green.

| Provider | URL to register | Authentication | Notes |
|---|---|---|---|
| Paymob | `https://<host>/payments/paymob/callback` (processed + response callback) | HMAC-SHA512 over the documented field list, with **that account's** HMAC secret | The attempt's own account is used to verify; a body signed with another account's secret is rejected (P6). Event type is derived from `is_voided` / `is_refunded` / `has_parent_transaction` / `pending` / `success`. |
| Fawaterak | `https://<host>/payments/fawaterak/webhook_json` | Paid / cancel: HMAC-SHA256 hash with the account's **vendor key** (set the "Vendor key" secret on the account). Failed / refund webhooks carry **no documented hash** | Unauthenticated types are recorded as informational only. No webhook can mark an order paid by itself: authenticated or not, the server-to-server inquiry decides (`PaymentService::settle`). |

Cases to run per account and record:
| # | Case | Expected |
|---|---|---|
| W1 | Real success callback | event `processed`, signature `valid`, attempt settled, "Last verified webhook" set on the account |
| W2 | Replay the same body 3× | one stored event (unique dedupe), `duplicate` on replays, no second ticket |
| W3 | Wrong/missing signature | HTTP 403 (Paymob) / event stored as `rejected` (Fawaterak), nothing changes |
| W4 | Callback for a different account's attempt | rejected; inquiry uses the attempt's own account |
| W5 | Provider retries after our 5xx | still idempotent; Webhooks screen → Retry re-processes a stored event |
| W6 | Unknown reference | stored as `unmatched`, no state change |
Payloads are stored encrypted with a safe-key whitelist (no PAN/CVV/tokens); verify in the DB that no card data appears.

## 4. Refunds — sandbox only; production stays OFF until sign-off
Refund calls are blocked unless **all** are true: `PAYMENTS_REFUNDS_LIVE=true` (default false), the account is usable, and a person has recorded **"Confirm refunds verified"** on the account (Settings → Payments → Providers) with the sandbox run reference. Changing the account's credentials resets that confirmation.

**Paymob** (API refund/void, `POST /api/acceptance/void_refund/refund`, `Authorization: Token <secret_key>`): run on each account, with real sandbox card payments.
| # | Case | Expected |
|---|---|---|
| R1 | Full refund of a settled card payment | `requested → processing → succeeded` after the refund callback / inquiry; refund transaction id stored |
| R2 | Partial refund, then a second partial | balance decreases; sum can never exceed captured (minor units) |
| R3 | Try to refund more than the balance | refused before any API call |
| R4 | Two clicks / two tabs at once | one in-flight refund per payment; second refused |
| R5 | Same-day card payment | Void offered (card only, pre-settlement); void fails after settlement → `failed`, not silently converted |
| R6 | Wallet / non-card payment | Refund capability shown as manual/unsupported per the capabilities matrix — confirm against the provider's real response and update the matrix |
| R7 | Provider timeout / 5xx | status `unknown`, **never re-sent automatically**; staff use "Check status" (inquiry) or record evidence |
| R8 | Refund callback replay | no change after the first |
Record the exact request/response shapes (secrets redacted) and correct `PaymobRefundGateway` first if any field differs.

**Fawaterak**: no refund-creation API is documented, so it is a **manual / external** workflow only: staff refund in the Fawaterak dashboard, then record the refund with evidence (reference, who, when). The refund webhook is a hint only (no documented signature) and never completes a refund. If Fawaterak confirms a refund API in writing, add a gateway under the same interface and flag it per account — do not infer endpoints.

`payments:validate` (read-only) before and after any run reports orphaned/inconsistent payment rows; `--backfill-references` fills only missing `internal_reference` values.

## 5. Evidence & sign-off
Keep a table per provider (cases, result, evidence link, tester, date). Approver signs: ☐ Paymob payments ☐ Paymob webhooks ☐ Paymob refunds ☐ Fawaterak payments ☐ Fawaterak webhooks (☐ Fawaterak refunds = manual workflow only). Only then plan Production (see the rollout plan in `docs/v2-stage-e-report.md`).
