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

## Express checkout (implemented)
One page at `/checkout` (no login, no wizard, no review step; `/checkout/review` redirects). Event page *Continue* goes straight there.
* **New e-mail** → passwordless account (`users.passwordless_since`, random unknown password, never mailed). No login session is created;
  the order is bound to the account and the buying browser gets session access to its order/confirmation/payment pages (`GuestOrderAccess`)
  plus short-lived signed PDF links. After buying, the customer can claim the account with an e-mail code and optionally set a password
  (`/account/password`, needs a code verified in the last 15 minutes).
* **Existing e-mail** → HTTP 409 `needs_verification`; a modal asks for a 6-digit code (single use, 10 min, bound to the browser session,
  5 attempts, 30 s cooldown, 5/hour per address). After verification the same form is resubmitted as the verified owner. Nothing is created,
  linked or edited before the code. Typed e-mail is never proof of ownership.
* **Signed-in** users buy directly; the contact on the order may differ and never changes the account.
* **Staff accounts** (admin/staff roles) cannot be used through this flow (checkout refuses the e-mail; codes never log them in).
* Unpaid orders reached from an emailed payment link by someone without a session get a verification page (code goes to the order's
  account address; the address is only shown masked).
* Idempotency: selection id + done-record, per-session lock, per-email account lock with unique-violation fallback, recent-order redirect,
  `expected_total` price check performed *before* any account is created.

### Environment flags to review before go-live
`PAYMENTS_LIVE_GATEWAYS`, `PAYMENTS_FAWATERAK_VERIFIED`, `PAYMOB_HMAC_SECRET` (webhook `payments/paymob/callback` is CSRF-exempt and rejected
in production without a valid HMAC), `PAYMENTS_FAKE_ENABLED` (never in production), `NOTIFY_MAIL_ALLOWLIST` / `NOTIFY_WHATSAPP_ENABLED` on staging.

### Decisions needing approval
1. Account enumeration is reduced, not eliminated: the code-send endpoint answers identically for every address, and the checkout 409 says only "confirm this email with a code" (masked address, throttled to 8/hour per client), but the *existence* of the extra step still leaks. Full neutrality would require not binding orders to accounts at purchase time.

### Typos and resending codes
* Before paying: fix the e-mail field and resubmit — the account this browser just created is renamed (no orphan account on the typo). In the code modal, "Edit my email" returns to the form with everything kept.
* After ordering: "Wrong email? Fix it" on the confirmation page changes the address of the still-unconfirmed account (buying browser only, never to an existing/staff address — one generic refusal), then the code is sent to the corrected address. Resend is rate limited (30 s cooldown, 5/hour per address) and a new code invalidates the previous one.
2. (Mitigated) Orders placed by a guest are flagged `owner_unverified`: they stay out of account pages, ticket pages and account-owner access until the mailbox is proven *by the browser that placed them* (or via the order's payment link + code). Someone typing a victim's e-mail therefore cannot push orders/tickets into the victim's account. Trade-off: an owner who bought on one device and confirms on another sees that order only after using its payment link or confirmation email.
3. Staff e-mails are blocked from the flow (they sign in with a password).
4. Guests reach orders via the browser session only; a different browser needs the e-mail code.
