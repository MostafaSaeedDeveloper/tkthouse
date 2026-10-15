# Checkout V2 — audit, flow and business-logic changes (Phase 2)

## Booking cycle (after this phase)
1. **Tickets** (event page, unchanged) → `Continue` sends `?event=&cart=` (base64 of `[{ticket_id, qty}]`).
2. **Your details** `GET /checkout` — the cart is turned into a *selection* by the server (`CheckoutSelection`), the URL is
   cleaned (`/checkout`), buyer + one form per ticket. `POST /checkout/details` stores the input in the session.
3. **Review & payment** `GET /checkout/review` — server quote (`CheckoutPricing`), promo preview (`POST /checkout/promo`),
   payment methods, `POST /checkout` places the order (`OrderPlacementService`) and redirects to the gateway.
4. **Confirmation** `GET /checkout/confirmation/{order}` — rendered from the order's real status only.
Payment retry: `/orders/{order}/payment/{token}` (also used by approval e-mails).

## Weaknesses found in the previous implementation (all fixed)
| Finding | Fix |
|---|---|
| Paymob callback marked an order **paid from the request body** (no signature, no lookup) | settled only after a server-to-server transaction lookup (+ HMAC check when `PAYMOB_HMAC_SECRET` is set) |
| Fawaterak redirect trusted `?result=success` (token is known to the customer) | invoice looked up at Fawaterak; `result` ignored |
| `POST /orders/{id}/payment/{token}` let a customer **mark their own order paid** | route removed |
| Anyone with a callback URL could re-trigger issuance / duplicate e-mails | one idempotent settlement path + per-order lock + atomic "claim" of unsent tickets |
| Every active promo code (value, limits) was printed into the checkout page JS | promo checked on the server only |
| `thank-you?flow=payment_success` rendered a success screen from the URL | status-driven confirmation, owner only |
| Double click / refresh / second tab could create 2 orders or 2 gateway sessions | submit lock + idempotency record; gateway session reused for 30 min |
| Late payment (after the deadline) left money taken and the order canceled | honoured when the order was auto-expired; flagged for review otherwise |
| `Customer::updateOrCreate(email)` let any user overwrite another customer's name/phone | only the account owner's own record is updated |

## Business-logic changes (intended)
* Order total unchanged: `(subtotal − discount) + fees`, fees on the undiscounted subtotal, fixed fees once per order.
* New `payment_attempts` table (additive migration). Orders/tickets schema untouched.
* A price change between review and payment aborts the order and sends the customer back to the review step.
* Online methods that are not fully configured are no longer offered. Methods with an unknown provider remain "manual".
* Paid orders are fulfilled by `PaymentService::settle` (status → paid, `paid_at`, tickets issued once).
* `orders:reconcile-payments` (every 2 min) settles attempts whose callback never arrived.

## Staging (https://dev.tkthouse.com)
```
APP_ENV=staging            MAIL_MAILER=log (or Mailtrap)
NOTIFY_MAIL_ALLOWLIST=none NOTIFY_WHATSAPP_ENABLED=false NOTIFY_PHONE_ALLOWLIST=none
PAYMENTS_FAKE_ENABLED=true            # then: php artisan payments:setup-fake-method
```
The simulator is refused in production; sandbox credentials for Paymob/Fawaterak can be used instead of the simulator.
Never run seeders against the copied data.

## Needs a decision
1. **Fawaterak status endpoint** — `getInvoiceData/{id}` and fields `data.paid` / `data.total` are the documented defaults but
   must be confirmed against the merchant's sandbox (configurable via `payments.fawaterak.*`). Until confirmed, unverifiable
   payments stay *pending* (never wrongly paid) and admins can approve them manually.
2. **Late payments on admin-canceled orders** are only flagged ("Payment received on a closed order"); refunds are manual.
3. **Guest checkout** — see proposal below.

## Guest checkout proposal (not implemented)
* `orders.user_id` and the customer account pages assume a user. Safest path: create a *passwordless account* from the buyer e-mail
  (email-verified by a magic link or OTP, reusing `RegisterOtpMail`) at the review step, so orders, tickets and the dashboard keep working.
* Alternative: nullable `user_id` + signed order links — larger change to scanner/dashboard/permissions; not recommended first.
* Risks to design for: e-mail ownership (account takeover via guessed e-mail), duplicate customers, ticket access links.
