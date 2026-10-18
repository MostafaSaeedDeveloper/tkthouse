# Payments workspace — Paymob & Fawaterak inside TKT House

Everything here is limited to what the **official** APIs allow (evidence and per-function status: `docs/v2-provider-capabilities-audit.md`). No scraping, no dashboard log-in simulation, no Payouts.

## Pages (Admin V2, existing theme)
| Page | What it is |
|---|---|
| **Overview** | KPIs (collected, refunded, net, pending/failed), **comparison with the previous equal period**, provider / method / merchant-account breakdowns, and a **separate** "Imported from the provider" block. |
| **Transactions → Created by TKT House** | Every local payment attempt: payment & order reference, customer, provider transaction + reference, provider & method, merchant account (permitted staff), amount/currency, status, **refund state**, created / paid. Filters: provider, merchant account, status, payment method, transaction id, order reference, date range, free search. |
| **Transactions → Imported from provider** | Provider-side transactions TKT House did not create (lookup by id or dashboard CSV). Filters add link state (linked / not linked) and source. Contains the capabilities table, **Provider sync** (last sync time/status/errors, **Sync now**), "Import by transaction id" and "Import CSV". |
| **Transaction details** | Overview, references (with copy buttons), provider & merchant account, related order and other attempts of the order, webhook history, **status history** (built from our own records), refund history, what can/can't be done, matching provider records. Actions: **Check payment status**, **Refund / Record manual refund** (the existing modal), view order. |
| **Imported details** | Overview, provider info, link state, verification state, "Check with provider". No refund/void here (no local order) — stated plainly. |
| **Refunds**, **Providers & Accounts**, **Webhooks** | Existing pages, now reachable from the same Payments tab bar. |

## Where each provider data comes from
* **Local payments** — created by TKT House; settlement only through `PaymentService` (signed webhook / server-side inquiry with the payment's own account).
* **Imported by id** — one official lookup (`GET /api/acceptance/transactions/{id}` / `getInvoiceData/{id}`) made with **the merchant account the admin selects**. If that account does not own the id the provider answers 404 and **nothing is stored**.
* **Imported from CSV** — a dashboard export uploaded by a person: column mapping + preview, then idempotent upsert. Rows are marked **unverified**; "Check with provider" verifies them through the official lookup. A CSV row never overwrites a row already verified by the API.
* **Provider sync** — re-checks *known* records only: still-open local attempts of the account (existing settlement rules) and the account's imported records. Bounded per run (100), oldest-checked first, ~6 requests/s, **HTTP 429 stops the run cleanly** ("partial", rate limited) and the next run continues; every run is logged (`provider_syncs`: time, status, examined/updated/errors, message). Background: `payments:sync` every 15 minutes. **There is no "sync everything" because no official listing API exists**; the UI says so and offers no such button.

## Guarantees
* Imported/external data **never creates an order**, never sets an order or attempt to Paid, never changes a refund. (Tested: orders/attempt status unchanged after import and sync.)
* **Linking** an imported record to a local attempt happens only on an **exact** provider-id (or provider order id / merchant reference) match **under the same merchant account** and only when exactly one attempt matches. Ambiguous → not linked and flagged; amount differs → flagged, nothing changed.
* **Reports never double count**: imported records linked to a local payment are only counted as "already in the local figures"; provider-only records are shown in a separate block and never added to collected/refunded/net.
* **Multi-merchant**: every lookup, sync, filter and refund uses the account of the record; reassigning an order never moves a payment between accounts (unchanged from earlier work).
* **Scoping**: agents/managers see local payments of their orders only (existing rules); imported/provider-only data needs `payments.accounts.view` (view) / `payments.transactions.import` (import) / `payments.transactions.inquire` (verify, sync). The new `payments.transactions.import` permission is copied by the migration only from `payments.accounts.manage` holders.
* **Refund / void** keep the existing workflow unchanged: admin permissions, second-person approval (default ON), provider API only for Paymob when the account is refund-verified and `PAYMENTS_REFUNDS_LIVE` is on, original account, idempotency, no auto-resend, audit. Void is offered only for eligible Paymob card payments; Fawaterak refunds stay manual with evidence. **No real financial operation was performed.**

## Cannot be done from inside TKT House (needs the provider's own dashboard) and why
| Need | Provider | Why |
|---|---|---|
| Browse/export the complete transaction list, search by date/status/amount | Paymob, Fawaterak | no official listing/search API (only single lookups) |
| Settlement / payout / fee statements, disputes | both | no API in scope |
| Refund a Fawaterak payment | Fawaterak | no refund-creation API (refund webhook only) |
| Void a non-card Paymob payment, or after the business day | Paymob | void is card-only and same-day |
| Cancel a Fawaterak invoice/reference | Fawaterak | no cancel API (expiry webhook only) |
| Per-transaction fee for Paymob | Paymob | no documented fee field (Fawaterak `commission` is shown when present) |
| Webhook URL registration, provider-side logs | both | portal-only |
| Refund/void a provider-only (imported) payment | both | there is no local order/attempt; the existing workflow is attempt-based and intentionally not duplicated |
If either provider confirms in writing that a listing/reporting API can be enabled for the account, implement it behind the same `ProviderCapabilities` switch and verify it in the sandbox first.

## Tests
`ExternalTransactionTest` (21): Paymob A/B and Fawaterak A/B isolation, wrong-account lookup, status mapping (voided/refunded/partial/pending/failed), duplicate lookup, rate limit, exact/ambiguous/wrong-amount/missing-reference linking, transaction without local order, CSV (mapping, locale amounts, duplicates, bad rows, idempotent re-import, never overriding verified rows, size limit, upload→preview→confirm, permissions), agent scoping, sync (refresh, idempotent, settlement rules, wrong amount not settled, 429 partial + resume, skipped, failing row, Sync now + command), capabilities/no "Sync all", reports separation and previous-period comparison, legacy payments. Existing suites keep covering refund permissions/approval/partial/void eligibility, webhook replay/signatures, wrong account/amount. MariaDB: 8 parallel imports of one transaction → one row. Browser journey (Fake gateway, dev data): transactions list → details → capabilities → import by id → verify → CSV preview/import/re-import → sync now → overview report → mobile; screenshots in `docs/screenshots/payments-workspace/`.
