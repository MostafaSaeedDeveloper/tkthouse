# Payments V2 — audit (before building)

Branch `claude/v2-admin-settings-assignment`. Read first: `v2-stage-e-design.md`, `v2-stage-e-report.md`, `v2-payment-sandbox-runbook.md`.

## What already exists and is reused
| Area | Existing | Decision |
|---|---|---|
| Provider accounts | `integration_accounts` (encrypted secrets, masked, test, enable gates, archive, `supported_methods`, RESTRICT FKs from attempts/orders/users) | **Reused** — no second table. UI moved from Integrations to Settings → Payments → Providers & Merchant Accounts (old URLs redirect; nothing deleted). Messaging/SMTP stay in Integrations. |
| Methods | `payment_methods` (code, provider, label, icon, active, legacy plaintext `config` with provider ids/keys) | **Kept**; added `sort_order`; provider id shown from legacy config; per-account availability = account `supported_methods`; legacy keys never rendered, blank keeps; used codes cannot be deleted; duplicates refused. |
| Attempts | `payment_attempts` (provider, method_code, reference, transaction_id, amount, currency, status, meta, settled_at, integration_account_id, routing_snapshot; unique `(provider, transaction_id)`) | **Extended additively** in Phase 2 (internal reference, provider order/invoice ids, paid/last-checked timestamps). Old rows stay valid (NULLs). |
| Settlement | `PaymentService::settle()` (locks, amount/currency/reference/transaction-reuse, idempotent, one-time tickets), `refreshAttempt`, `reconcile` | **Unchanged**; webhooks feed it, they never decide "paid" by themselves. |
| Callbacks | `POST/GET /payments/paymob/callback` (HMAC per account, attempt by `merchant_order_id`); `GET /payments/fawaterak/callback/{order}/{token}` (redirect hint only) | Kept; Phase 2 adds event logging and a Fawaterak webhook endpoint. |
| Routing / assignment | Stage E resolver, reservations, switcher | Untouched; refunds use the attempt's account. |
| Refund | none (order statuses `refunded`/`partially_refunded` are manual labels only; Admin Payments page is read-only) | New financial refund records, separate from order status. |
| Permissions | `settings.payment-accounts.*`, `orders.*`, `payment-methods.*` | New `payments.*` set; copied only to holders of the equivalent old permission. |

## Findings
* Legacy payment-method form rendered stored API keys in HTML and overwrote them with blank on save → fixed.
* Methods could be deleted although orders reference their code → blocked.
* `RolesAndPermissionsSeeder` truncates permission tables (documented earlier); new permissions added to it.
* Fawaterak needs a **vendor key** (webhook hash) distinct from the API bearer token — a new secret field on Fawaterak accounts.

## Provider capabilities
See `v2-payment-capabilities-matrix.md` (sources, what is confirmed, what is not).
