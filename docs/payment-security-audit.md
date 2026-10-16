# Payment Security Audit — final report

Scope: Paymob and Fawaterak card/wallet payments, the Fake gateway, settlement, ticket issuance and delivery for TKT House V2.
No real payment, e-mail or WhatsApp message was produced while preparing this report (Fake gateway, faked mail, `Http::preventStrayRequests`).

Legend — **A** verified by reading the code · **B** exercised by automated tests against mocks/fakes (`Http::fake`) · **C** needs a real sandbox / provider before it can be trusted.

## 1. Findings fixed during the audit
| # | Issue | Fix |
|---|---|---|
| 1 | A customer could mark their own order paid; success URLs/callback bodies were trusted | Payment is decided only by `PaymentService::settle` after a server-side check with the provider |
| 2 | Paymob webhook accepted without a signature in production | HMAC-SHA512 is mandatory in production (POST webhook and GET return); an unconfigured secret rejects everything; a valid HMAC alone still does not pay — the transaction is fetched from Paymob |
| 3 | Webhook route lacked a CSRF exemption (surfaced by production-mode tests) | `payments/paymob/callback` is exempt from CSRF only |
| 4 | Amount/currency/reference were not bound | Strict binding: attempt amount = order total = gateway amount (cents), currency, merchant reference, transaction id |
| 5 | One transaction id could pay two orders | Unique `(provider, transaction_id)` in the database plus an application check |
| 6 | Late payments (after expiry/cancel/event end) | Revived only while still fulfillable; otherwise recorded in `needs_review` with no issuance and listed by `payments:review-queue` for a manual refund |
| 7 | Fawaterak accepted on guesswork | Official `getInvoiceData/{id}` only, stored invoice id (never browser-supplied), disabled for production until `PAYMENTS_FAWATERAK_VERIFIED=true` |
| 8 | Fake gateway reachable outside tests | `PaymentEnvironment`: never in production even if switched on; simulator pages 404 in production; the simulator controller uses the same rule |
| 9 | Ticket issuance/delivery lost on a half failure | One transaction for the ticket set, per-order lock, per-channel delivery markers, `orders:retry-ticket-issuance` with an attempt cap |
| 10 | Staging copy of production data could hit live gateways or customers | Live gateways off outside production unless `PAYMENTS_LIVE_GATEWAYS`; mail/WhatsApp allow-lists (`NOTIFY_*`) |
| 11 | Express checkout: payment-link page printed the account e-mail to anyone holding the link | Only a masked address is rendered; the code is always sent to the order's own address |

## 2. What was actually tested (B) — by area
**Webhook/return authenticity** — `PaymentSecurityTest`, `CheckoutPaymobCallbackTest`: unsigned or mis-signed webhooks rejected and never looked up; unconfigured secret rejects all; valid signature alone is not proof; signed return URL required for the URL id in production; flat (GET) signature variant; forged success body; unknown transaction; someone else's successful transaction.
**Settlement rules** — amount must match attempt and order; voided/refunded transactions are not paid; replayed callbacks change nothing; a transaction can't settle two orders (also enforced by the DB); same id allowed at different providers; retry inside the same gateway session records the paying transaction.
**Lifecycle** — payment after the deadline reinstates once; cancelled-by-staff and ended-event orders are flagged, not fulfilled; a second payment on a paid order is recorded as a duplicate; double click/refresh reuses one gateway session; reconcile settles a payment whose callback never arrived; failed then successful payment still settles.
**Issuance/delivery** — tickets issued exactly once under duplicate callbacks and repeated calls; failure while issuing doesn't lose the payment and is repaired without duplicates; failed deliveries retried, successful ones never repeated; retry gives up after the cap.
**Environment safety** — `PaymentEnvironmentTest`: Fake gateway impossible in production, simulator 404, helper command refuses, staging scheduler and callbacks never contact a real gateway, services refuse when live gateways are off.
**Fawaterak** — settles only from a complete official-shaped `paid` response; anything else never pays; browser-supplied invoice ids ignored; attempt without stored invoice id can't settle; stays off in production until marked verified.
**Express checkout (identity, not gateway)** — `ExpressCheckoutTest`, `ExpressCheckoutIdentityTest`: see `docs/checkout-v2.md`.

Full suite at the time of writing: 253 tests passing.

## 3. Verified by code reading only (A)
* `PaymentService::settle` is the only code path that sets an order to `paid`; controllers, the simulator and admin screens call it rather than writing the status.
* `ticket issuance` runs only for `paid` orders and under `Cache::lock('issue-tickets:{id}')`.
* The CSRF exemption is limited to the Paymob server callback; Fawaterak callbacks require the per-order token.
* Scheduler entries: expire pending (1 min), reconcile (2 min), retry issuance (5 min); all are no-ops against live gateways outside production.

## 4. Needs a real sandbox / provider (C) — not claimed as working
1. **Fawaterak**: the status endpoint (`getInvoiceData/{id}`) and the `data.paid` / `data.total` fields are the documented defaults but were never run against the merchant's sandbox. Until confirmed, unverifiable payments stay *pending* (never wrongly paid) and admins approve manually. Do not set `PAYMENTS_FAWATERAK_VERIFIED=true` before one real sandbox payment (success, failure, wrong amount) is reconciled.
2. **Paymob live webhook delivery**: the dashboard "transaction processed callback" URL must point to `/payments/paymob/callback` and `PAYMOB_HMAC_SECRET` must match the dashboard HMAC. Our HMAC field order follows Paymob's documentation and is covered by tests built from that spec, but a real signed callback has not been replayed.
3. **Paymob 3-D Secure / wallet flows**: redirect behaviour, pending→success timing and `pending=true` responses with real cards/wallets.
4. **Paymob transaction inquiry** response shape against the live API (auth token, `/acceptance/transactions/{id}`), including rate limits.
5. **Refunds**: no automated refund path exists; refunds are manual in the provider dashboard (the review queue only lists them).
6. **Real mail/WhatsApp providers**: delivery retry logic is tested with fakes; provider rate limits (e.g. "too many emails per second") are handled by backoff but not load-tested.
7. **Production configuration**: confirm `APP_ENV=production`, `PAYMENTS_FAKE_ENABLED` absent/false, `PAYMOB_HMAC_SECRET` set, the scheduler (`schedule:run`) running.

## 5. Residual risks & decisions
* Late payments on cancelled/ended orders need a human refund decision.
* Express checkout reveals, to a client that probes many e-mails, that an address needs confirmation (throttled to 8 per hour per client; wording never says an account exists). A fully neutral flow would require not binding orders to accounts at purchase time — see `docs/checkout-v2.md`.
