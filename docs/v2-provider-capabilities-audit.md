# Provider capabilities audit — Paymob (Accept) and Fawaterak (what the official APIs really allow)

Audit date: 2026-10-17. Rule applied: **a capability is "supported" only when an official source shows it.** Everything else is labelled, never assumed. No scraping, no dashboard log-in simulation, no Payouts/Send APIs (out of scope).

## Official sources used
* **Paymob** — official Postman collections `PaymobAccept/API-Postman-Collections` on GitHub (`Transaction Inquiry API`, `Refund & Void & Capture APIs`; the repo also holds Intention, Saved-card, Subscription, QuickLink, Partner Onboarding and Payouts collections), and the Paymob developer docs "Managing payments" (refund / void / capture, API and dashboard variants).
* **Fawaterak** — official API reference `fawaterak-api.readme.io` (page index read in full, 20 pages: Getting started, Test account/cards, Payment methods, IFrame, *Create e-invoice link*, *Send payment*, *Gateway integration* steps 1–2, tokenization, **Get Transaction Data**, **Web Hook**, plugins, mobile).
* Third-party SDKs/wrappers (Laravel/Python packages, API catalogs) were read only as **hints**; nothing they claim is treated as supported.

## Vocabulary
**API** = supported by an official API · **ACT** = needs provider activation / account enablement · **MAN** = manual / external only (provider dashboard) · **NO** = not supported · **SBX** = needs sandbox verification before being relied on · **ND** = not documented in any official source (treated as unavailable).

## Capability matrix
| Capability | Paymob Accept | Fawaterak |
|---|---|---|
| **List transactions** | **ND** — none of the official collections lists transactions; every inquiry is a single lookup. A third-party wrapper advertises "get all transactions" but it is not in the official collections → **not used**. Dashboard "Transactions" screen = **MAN**. Ask Paymob support whether a listing API can be enabled (**ACT**). | **ND** — no list endpoint in the official reference. Dashboard = **MAN**. A third-party SDK claims list methods → **not used**. |
| **Search transactions** | **API (by key only)**: `POST /api/ecommerce/orders/transaction_inquiry` by `order_id` or `merchant_order_id` (returns the **latest** transaction of that order); `GET /api/acceptance/transactions/{id}`. No search by date/status/amount. **SBX** (response shape). | **API (by invoice id only)**: `GET /api/v2/getInvoiceData/{invoice_id}`. No search by date/status/reference. |
| **Transaction details** | **API** `GET /api/acceptance/transactions/{transaction_id}` (Bearer token from `POST /api/auth/tokens` with the API key, valid 60 min). **SBX** | **API** `getInvoiceData/{id}` → `invoice_id, invoice_key, due_date, invoice_created_at, paid_at, customer_email, payment_method, commission, currency, total, paid, invoice_transactions[ {payment_method, refrence_id, status, amount, transactionCurrency, paid_at} ]`. **SBX** (value formats are placeholders in the docs). |
| **Payment status inquiry** | **API** (the two calls above). Already used by `PaymobGateway::inquire`. | **API** (`getInvoiceData`). Already used by `FawaterakGateway`. |
| **Refund** | **API** `POST /api/acceptance/void_refund/refund`, `Authorization: Token <secret_key>`, body `{transaction_id:int, amount_cents:int}`; fees apply to refunds. **SBX** (response shape not in the collection). Dashboard variant also exists. Already implemented behind the production gates. | **MAN** — no refund-creation API in the official reference. Refund **webhook** exists (notification only). Refunds are made in the Fawaterak dashboard. (A third-party SDK exposes create/list refund calls; unverified → not used.) |
| **Partial refund** | **API** (`amount_cents` ≤ original; several partials allowed per callbacks docs). **SBX** | **MAN** |
| **Void** | **API** `POST /api/acceptance/void_refund/void` `{transaction_id}`; **card only, same business day, no fee**, no partial void. Dashboard variant exists. | **NO** (no API, no documented dashboard void) |
| **Cancel** | = void for Paymob (no separate cancel). Capture API exists for authorize-only flows (not used by TKT House). | **NO** API. A *cancel webhook* is sent when a Fawry/Aman/Masary reference **expires** (notification only). |
| **Payment link / invoice details** | QuickLink API collection exists (create payment links). Not needed for the checkout TKT House uses; **not evaluated further**. Invoice details = transaction details above. | **API** *Create e-invoice link*, *Send payment*; details via `getInvoiceData`. |
| **Transaction references** | `transaction_id` (int), `order.id`, `order.merchant_order_id` (= our payment reference), webhook `obj.id`. | `invoice_id`, `invoice_key`, `referenceNumber` / `refrence_id` (sic) per transaction. |
| **Fees** | Refund fee is stated; **no per-transaction fee field is documented** in the inquiry. **ND** → fees are not displayed for Paymob. | `commission` field in `getInvoiceData` (**SBX**: meaning/units to be confirmed; shown as "provider-reported commission" only when present). |
| **Webhooks** | **API** "transaction processed callback" (HMAC-SHA512 per account) incl. `is_refunded`, `is_voided`, `has_parent_transaction`. Implemented. | **API** four types — paid (hash), failed (no documented hash), cancel (hash), refund (no hash). Implemented as documented. |
| **Transaction history** | No history endpoint; the transaction carries flags (`is_voided`, `is_refunded`, `refunded_amount_cents`) and refund/void appear as **child transactions** (`parent_transaction`). History is therefore built from **our own** attempts, webhook log and status changes. | `invoice_transactions[]` = the payment attempts on the invoice (✔ API); no refund history API. |
| **Export / reports** | **MAN** (dashboard). No official export API. (Settlement/payout reports belong to Payouts — out of scope.) | **MAN** (dashboard). |

## What this means for TKT House (decisions)
1. **No "Sync all transactions" button for either provider** — there is no official API to list them. Showing one would be a fake feature.
2. What **is** implemented, because it is officially supported:
   * **Refresh known transactions** (re-inquire by id/order, per merchant account, paginated over *our* records, rate-limit aware, idempotent) — "Sync now" + a scheduled background job.
   * **Import by id**: an admin types a Paymob transaction id / Fawaterak invoice id and the system fetches it with the **chosen merchant account** (official single lookup).
   * **CSV import** of a provider dashboard export (column mapping, preview, idempotent upsert) — the supported alternative to listing. Rows are labelled "imported from CSV (unverified)" until verified by the lookup above.
3. **Imported/external transactions never create orders and never change an order or attempt to Paid.** They are stored separately (`external_transactions`) and linked to a local attempt only on an **exact** provider-id match under the same account.
4. Refund / Void keep using the **existing** refund workflow (permissions, second-person approval, original account, production gates). Void is offered only for Paymob card payments that are eligible. Fawaterak refund stays **manual with evidence**.
5. Fees: shown only where a documented field exists (Fawaterak `commission`).

## Things that will still need the provider's own dashboard (and why)
| Need | Provider | Reason |
|---|---|---|
| Browse/export the **complete** transaction list, date-range search | both | no official listing/search API |
| Settlement, payout and fee statements; disputes/chargebacks | both | no API in scope (Paymob Payouts is a different product) |
| **Refund** a Fawaterak payment | Fawaterak | no refund-creation API |
| Void a non-card Paymob payment / after the business day | Paymob | void is card-only and same-day by definition |
| Cancel a Fawaterak invoice or reference | Fawaterak | no cancel API (only an expiry webhook) |
| Register/rotate webhook URLs, view provider-side logs | both | portal-only configuration |
| Enable a listing/reporting API, if the provider offers one to your account | both | would need written confirmation from support (then implement + sandbox-verify) |

## Items that need **sandbox verification** before production reliance
Paymob: transaction-by-id response fields (incl. `is_refunded`, `refunded_amount_cents`, `source_data`), refund and void responses, behaviour of refund on wallets, rate limits (HTTP 429 behaviour). Fawaterak: `getInvoiceData` value formats, meaning of `commission`, rate limits, the refund webhook trigger.
