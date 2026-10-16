# Stage E — Merchant Accounts & Payment Routing: final design (for approval)

**Nothing in this document is implemented.** Stage E starts only after explicit approval. It changes who collects money, so every
choice that touches settlement is listed in §8.

Builds on what Stage C already ships: `integration_accounts` (encrypted credentials, several accounts per provider, test
connection, enable gates). **A merchant account = an `integration_accounts` row with provider `paymob` or `fawaterak`** — no
parallel table.

## 1. Data (additive migrations only)
| Change | Purpose |
|---|---|
| `payment_attempts.integration_account_id` (nullable FK), `payment_attempts.routing_snapshot` (json) | Immutable binding of an attempt to the account it was opened with + audit of how it was resolved (policy, assignee at that time, account name/provider/environment). `NULL` = legacy attempt ⇒ legacy credential path exactly as today. |
| `orders.integration_account_id` (nullable FK) | The account the customer was *offered* at checkout (pinned once, never changed by reassignment). |
| `users.payment_routing_mode` (`default`/`custom`), `users.integration_account_id`, `users.allowed_payment_methods` (json) | Per-user configuration. **No keys on the user.** |
| `checkout_routings` (token, session_id, assignee_id, integration_account_id, expires_at, consumed_order_id) | The reservation that makes automatic assignment safe before methods are shown (§3). |
| settings (registry): `payment_routing_enabled` (default off), `default_payment_account_id`, `unassigned_order_payment_policy` = `default_account` (default) \| `block_online` | Feature flags. Disabling routing returns *new* attempts to the default account; old attempts keep their accounts. |
| account `config.supported_methods` (+ per-method ids: Paymob integration id, Fawaterak provider key) | Methods offered = account.supported ∩ active catalogue ∩ user.allowed ∩ gateway configured. |
| permission `orders.reroute_payment`, `settings.payment-accounts.manage/view` | Explicit, separate. |

## 2. Resolver (`PaymentAccountResolver`, server-side only; browser input is never an input)
1. Routing disabled → default account (or legacy path when none configured).
2. Order already has `integration_account_id` → that account (must be enabled + verified, else the **new** attempt is refused with a clear message; staff re-route explicitly, §5).
3. Else assignee's custom account (mode `custom`, enabled, verified).
4. Else policy: `default_account` (**your decision**) or `block_online`.
5. Methods = intersection described above.

## 3. Automatic assignment before methods are shown (your requirement)
Today the order does not exist when the customer picks a method. Design:
1. When the checkout payment step is rendered, `CheckoutRoutingService::reserve(session)` runs **once per session** (refresh/back are idempotent: same reservation returned):
   inside one DB transaction it locks the `assignment_state` row, picks the next eligible agent (round robin / default assignee / none for manual), resolves the account, stores the reservation (TTL 30 min) and advances the pointer. Concurrent customers serialise on that lock, so each gets a distinct turn and nobody is assigned twice.
2. The methods list shown to the customer comes from the reserved account. The session holds only the reservation token.
3. `OrderPlacementService::place()` **consumes** the reservation: the order is created assigned to the reserved agent and pinned to the reserved account (exactly what the customer saw). If the reservation expired or the agent/account became unavailable, the order is not silently re-routed: checkout returns "payment options changed, please choose again" and a fresh reservation is made.
4. Manual mode: no agent ⇒ default merchant account (policy), order lands in the Unassigned queue.
5. Abandoned carts consume a round-robin turn (documented trade-off; avoids creating orders before payment choice). Alternative in §8.

## 4. Attempt lifecycle (settlement code untouched)
* `PaymentService::start()` resolves the account (§2), creates the attempt with `integration_account_id` + snapshot **before** calling the gateway, and passes the account to the gateway. Gateways read credentials from the account, never from `payment_methods.config`/`.env` when an account is bound.
* `inquire()`, callbacks and `reconcile()` always load credentials from **`attempt.integration_account_id`**. A guard asserts the inquiring account equals the attempt's account. NULL ⇒ legacy account (current behaviour, so existing attempts keep working).
* Disabling an account blocks *new* attempts only; open attempts keep reconciling while the row exists. Deleting an account that has attempts is blocked.
* Unchanged and relied upon: row locks, amount/currency/reference checks, unique `(provider, transaction_id)`, one-time ticket issuance, late-payment review rules.

## 5. Reassignment, retry, switching accounts
* Reassigning an order never touches `orders.integration_account_id` or any attempt.
* Retry after a failed attempt: same pinned account.
* **Switch payment account** (`orders.reroute_payment`, audited): runs a server-side inquiry on every open attempt of the old account; refuses if any is paid or its state is unknown; otherwise marks them `expired (rerouted)`, pins the new account, writes the audit row. This is the double-charge guard.

## 6. Callback authenticity with several accounts
* **Paymob**: one callback URL stays. Each account must point its Paymob integration's callback at that URL in the Paymob portal (operational step, listed in the runbook). The handler finds the **attempt** by the unique `merchant_order_id` we generated (`attempt.reference`), loads that attempt's account and verifies HMAC with **that account's** `hmac_secret`. The field used to pick the secret is unauthenticated, which is safe only because a wrong pick fails verification and settlement still requires the server-to-server inquiry with the account's own API key. Tests: account A payload signed with B's secret is rejected; unknown reference never changes state.
* **Fawaterak**: route already carries `{order}/{token}`; inquiry uses the attempt's account and stored invoice id. **Multi-merchant stays blocked in production** until a real sandbox run confirms inquiry (`data.paid` semantics), callbacks and transaction↔account binding (`PAYMENTS_FAWATERAK_VERIFIED` plus a per-account `sandbox_verified_at`). No endpoint or field is guessed.

## 7. Tests planned
Paymob A vs B, Fawaterak A vs B (HTTP faked), mixed gateways, duplicate callback, reassignment during payment, retry after failure, late payment, old orders/attempts without account, integration disabled, account disabled, missing/invalid credentials, concurrent reservations (distinct round-robin turns), reservation reuse on refresh, reservation expiry, switch-account refusal with an open/paid attempt, secrets never in logs/JSON. Payment fakes and mocked HTTP only; no real charge, no real notification.

## 8. Decisions I need from you before coding
1. **Reservation at payment step** (consumes a round-robin turn even if the cart is abandoned) vs creating the order first. *Recommended: reservation.*
2. If the reserved agent/account is no longer valid when the customer submits: **ask the customer to choose again** (recommended) vs silently fall back to the default account.
3. A disabled account with **open attempts**: keep reconciling them (recommended) vs stop.
4. Backfill: leave old attempts `NULL` (recommended; legacy path) vs attach them to the imported legacy account.
5. Fawaterak: confirm you will run the sandbox verification before any production enablement.
6. Visibility: should the merchant account name appear in orders/reports only for `settings.payment-accounts.view`? (recommended: yes)
