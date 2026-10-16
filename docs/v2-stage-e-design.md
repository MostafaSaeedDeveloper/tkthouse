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

## 3. Automatic assignment before methods are shown — PROVISIONAL reservation (approved revision)
Goal: the customer is shown one agent's account and methods and keeps them; abandoned carts never consume a final round-robin turn.
* `checkout_routings` row = **provisional** reservation (token, hashed session id, assignee, account, source, expires_at default 30 min, consumed_order_id, released_at). It does **not** move the final pointer `assignment_state.last_assigned_user_id`.
* **reserve()** (when the payment step renders; idempotent per session — refresh/back return the same live reservation): one transaction that locks the `assignment_state` row, then picks the next eligible agent after the pointer **skipping agents currently held by other live reservations** (so concurrent sessions are spread, not stacked; if all are held, the first in rotation is used). The account is resolved from that agent (§2). Reservation stores both.
* **consume()** (inside `OrderPlacementService::place()`'s transaction): lock the state row, then re-validate — not expired, not consumed, belongs to this session, agent still eligible, account still enabled + verified, chosen method still offered by that account/user. Valid ⇒ the order is created assigned to the reserved agent and pinned to the reserved account; **only now** the pointer advances (to the reserved agent) and the reservation is marked consumed. Row locks + a unique `consumed_order_id` make a double consume impossible.
* Invalid/expired/conflicting ⇒ **no order is created**, nothing is silently re-routed: the customer is sent back to the payment step with a clear message ("Your payment options changed or expired — please choose again") and **keeps buyer/attendee data** (the session draft is untouched); a fresh reservation is made on that render.
* Abandoned reservations simply expire (a scheduled prune releases them); they never touch the pointer, so they cost no turns. Because the pointer follows *completed* orders, two sessions that finish out of order can reorder the rotation slightly; fairness is preserved in aggregate and documented.
* Reservations exist only while routing is enabled. Routing off ⇒ today's flow plus `assignOnCreate` (which already advances the pointer on order creation).
* Manual mode: no agent ⇒ default merchant account (policy `default_account`) / online payment blocked (policy `block_online`), order lands in the Unassigned queue.

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

## 8. Decisions (all approved)
1. Provisional reservation (revised as §3: final pointer advances at order creation only). 2. Invalid reservation ⇒ customer re-chooses, data kept. 3. Disabled account keeps reconciling its attempts. 4. Legacy attempts stay `NULL` and use the legacy path; legacy keys in `payment_methods.config`/`.env` are **not** removed until a confirmed migration plan and tests of open historical operations. 5. Fawaterak multi-merchant stays blocked in production until a real sandbox run. 6. Merchant account visibility needs `settings.payment-accounts.view`.

## 9. Added by the approval
* **Account lifecycle**: accounts referenced by any attempt, order or user cannot be deleted (DB `RESTRICT` + controller guard); they can be *archived* (disabled, hidden from selection, still used for historical inquiry/callbacks/reconcile). Secrets stay encrypted in the account row, readable only by server code that verifies an attempt of that account.
* **Immutable binding**: `payment_attempts.integration_account_id`/`routing_snapshot` and `orders.integration_account_id` can be set once; any later change throws (model guard) — except through the audited switch service.
* **No session re-use across accounts**: an open attempt is only re-used if its account equals the order's pinned account; an unpinned order is sticky to the account of its latest attempt; a different account is refused unless switched explicitly.
* **Switch merchant account** (`orders.reroute_payment`): live gateway inquiry on every open/failed-but-eligible attempt; refuse on PAID (and settle it), on UNKNOWN/error, and on PENDING younger than 60 minutes (a payment may be in progress, incl. 3DS). Older pending attempts are marked `expired` with `meta.rerouted` but stay reconcile-eligible, so a late payment on the old session is still matched (and flagged as a duplicate for refund review if the order is already paid). Full audit row.
* **Fake provider for journeys**: a `fake` integration provider (non-production only) so full browser journeys with two merchant accounts run with no real gateway.
* **Paymob attempt lookup** by the unique `merchant_order_id` (`attempt.reference`), then that attempt's account/HMAC; settlement refreshes only that attempt.
* **Finding**: `FawaterakPaymentMethodsSeeder` contains a hard-coded default Fawaterak API key in source control — treat as exposed and rotate it.
* **Provider verification runbook** (`docs/v2-payment-sandbox-runbook.md`): Paymob sandbox (HMAC, processed callback, GET return, 3DS, two accounts); Fawaterak sandbox (inquiry endpoint, `paid` semantics, account binding). Production enablement for either stays blocked until the runbook sign-off is recorded.
