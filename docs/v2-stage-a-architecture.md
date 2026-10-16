# TKT House V2 — Stage A: Audit & Architecture

Status: **proposal for review — no application code has been changed.** Baseline: `main` @ `e10b76a`, 307 tests green (SQLite).
No unmerged "Orders V2" branch exists: `claude/jolly-goodall-ihj1ye` (Orders redesign) is already fully contained in `main` (0 commits ahead). Other remote branches are old Codex branches from March 2026 and carry nothing newer than `main`.

---

## 1. Admin V2 audit (Part 1)

All admin screens extend `admin.master` and use the Admin V2 components, **except** the items below.

| Area | Status |
|---|---|
| Dashboard, Events (index/show/create/edit/form/dashboard), Tickets (index/show/create/edit/form), Orders (index/show/edit/deleted/filters), Customers, Affiliates, Payments, Reports, Users, Roles, Permissions, Activity Logs, Search, Scanners (staff), Promo Codes, Payment Methods index, General Settings | **V2** |
| `settings/payment-methods/form` | V2 shell, one legacy control (`btn btn-alt-secondary` Copy button) |
| `tickets/guest-list` | V2 shell, 2 legacy elements to normalise |
| `orders/index` | V2, 1 legacy element to normalise |
| `tickets/scanner` (644 lines), `tickets/scanner-login` (617 lines) | **Standalone full-page screens** (own `<html>`, mobile scanner UI). Not on `admin.master`. Decision needed: restyle in place (recommended, keep as standalone mobile-first pages, align tokens/buttons only) vs. move into the admin shell (would hurt the door-scanning UX). |
| `tickets/pdf`, mail views | Not admin UI (printed ticket / e-mail) — out of scope |

Conclusion: Part 1 is ~90% done. Stage B = the four leftover items + modals/validation/empty-state consistency pass + browser QA at 360/768/1024/1440.

---

## 2. What exists today (facts the design builds on)

**Settings**
* `system_settings(key unique, value text)` + `App\Support\SystemSettings` (static, per-request cache only, **no** group/validation/encryption/audit/cross-request cache invalidation).
* Keys in use: `site_name`, `primary_color`, `secondary_color`, `site_logo_light|dark|footer`, `whatsapp_ticket_sending_enabled`, `pending_payment_timeout_minutes`.
* Maintenance mode is `.env` driven (`config('app.app_maintenance')`, `FrontendMaintenance`/`AppMaintenance` middleware).
* Permissions: `settings.view`, `settings.update`, `payment-methods.*`.

**Credentials (important finding)**
* Paymob/Fawaterak API keys live **in plaintext** in `payment_methods.config` (JSON) with `.env` fallback (`services.paymob.*`, `services.fawaterak.*`). The Paymob HMAC secret is **only** in `.env` (`PAYMOB_HMAC_SECRET`), global.
* UltraMsg (`services.ultramsg.*`) and SMTP (`MAIL_*`) are `.env` only.
* `payment_methods` rows are one per *method* (e.g. `paymob_card`, `paymob_wallet`, fawaterak methods) and carry the gateway credentials. There is no "merchant account" concept: the account is implicit in whichever `PaymentMethod` row the order's `payment_method` code points to.

**Payments**
* `PaymentService::start()` → `PaymentGateway::initiate()` creates a `PaymentAttempt(order_id, provider, method_code, reference, transaction_id, amount, currency, status, meta)`; all settlement goes through `settle()` (row locks, amount/currency/reference/transaction-reuse checks, unique `(provider, transaction_id)`, idempotent, ticket issuance after commit). **This core is sound and must not be rewritten**; routing must slot in underneath it.
* `inquire()` looks the account up **again** by `attempt.method_code` + provider (`PaymentMethod::where('code', …)`), not from anything pinned on the attempt. So today, editing a method's credentials or re-pointing it silently changes which account an old attempt is verified against. This is the gap Part 4 closes.
* Paymob callback `/payments/paymob/callback` (GET+POST) verifies HMAC with the single global secret; Fawaterak return uses `/payments/fawaterak/callback/{order}/{token}` and a server-side `getInvoiceData/{id}` inquiry (gated by `PAYMENTS_FAWATERAK_VERIFIED` in production).
* Guard rails already present and kept: fake gateway never in production, live gateways off outside production unless `PAYMENTS_LIVE_GATEWAYS=true`, Paymob HMAC mandatory in production.

**Orders / users**
* `orders.user_id` is the **customer's login account** (the `users` table holds both customers and staff). Staff assignment therefore needs a **new** column; `user_id` must not be reused.
* The order is created in `CheckoutController@store` / `OrderPlacementService::place()` *after* the buyer already picked `payment_method` from `PaymentGatewayManager::available()` (global list). Payment is opened later via `/orders/{order}/payment/{token}/gateway`.
* Admin order visibility today: `Order::scopeVisibleTo($user)` (hidden/"exclude from statistics" orders need `showing_orders`). Order queries in admin: `OrderController` (list, deleted, export, show/edit/update/status/notes/approve/reject), `DashboardController` + `DashboardMetrics`, `ReportController`, `EventInsightsController`, `CustomerController`, `Admin\PaymentController`, `SearchController`. **Every one needs scoping** when assignment is on.
* Roles/permissions: Spatie; no `Gate::before`. Protected-role logic in `UserController`/`RoleController`.

---

## 3. Settings architecture (Part 2)

### 3.1 Storage

New additive tables (existing `system_settings` is **kept and reused as the legacy store**, never dropped):

| Table | Purpose |
|---|---|
| `setting_definitions` is **code, not DB**: `App\Settings\Registry` (PHP classes/arrays) declares every key: group, type, default, validation rules, `secret` flag, required permission, `sensitive-in-audit` flag. | Single source of truth; nothing user-defined can introduce a key. |
| `system_settings` (existing) | Values for non-secret keys. Add nullable columns `group`, `updated_by`, keep `key/value`. |
| `integration_credentials` *(new)* | `integration`, `account_id?`, `field`, `ciphertext` (Laravel `Crypt`/`encrypted` cast, AES-256 with `APP_KEY`), `fingerprint` (last-4 hash only), `updated_by`. Secrets never live in `system_settings`. |
| `setting_audits` *(new)* | `key/group`, `old` , `new` (secrets recorded only as `changed`, never the value), `user_id`, `ip`, `created_at`. Activity-log entries use the same redaction. |

### 3.2 Service

`App\Settings\SettingsService` (bound as singleton; `App\Support\SystemSettings` becomes a thin facade over it so **every existing call site keeps working**):
* `get(key, default)` → registry default → DB value → legacy `system_settings` value → `.env`/config fallback (only for keys that declare one, e.g. mail, ultramsg).
* Cache: `Cache::rememberForever('settings.v1')` of the non-secret map; `forget` on every write (incl. from other workers, because it is the shared cache store, not a static property). Secrets are never cached in plaintext.
* `set()` validates against the registry rules, writes in a transaction, writes the audit row, busts the cache.
* Safe fallback: a missing/invalid/undecryptable value returns the default (decryption failure is logged without the value) and marks the integration "needs attention" instead of throwing in a request path.

### 3.3 Not manageable from the UI (stay in `.env`)
`APP_KEY`, DB connection, `APP_ENV`/`APP_DEBUG`, `PAYMENTS_LIVE_GATEWAYS`, `PAYMENTS_FAKE_ENABLED`, `PAYMENTS_FAWATERAK_VERIFIED` (the human "I ran a sandbox" attestation), Paymob HMAC *requirement* in production, queue/session drivers. The UI shows them read-only in a "Platform status" panel so staff can see *why* a gateway is unavailable, but cannot flip them. Maintenance mode: UI toggle writes a DB flag consumed by the maintenance middleware (the `.env` flag still wins when set) — **never** takes the dashboard itself offline for staff with `settings.update`.

### 3.4 UI structure (Admin V2, one page with an internal sub-nav, route per section)
General · Branding · Events & Tickets · Orders · Payment Gateways (accounts) · Integrations (Paymob, Fawaterak, UltraMsg, Email) · Notifications · Security & Access.
Permissions: `settings.view`/`settings.update` (existing, general) plus new `settings.orders`, `settings.integrations.view`, `settings.integrations.manage`, `settings.integrations.test`, `settings.payment-accounts.manage`, `settings.security.view`. Existing roles that hold `settings.update` keep General/Branding/Events access only; integrations/payment accounts require the new permissions (granted to super admin by migration seeder, **opt-in** for others).
Security & Access section is read/link-only for items that could bypass auth or payment verification (those toggles are intentionally not offered).

### 3.5 Integration management contract
Each integration implements `IntegrationDefinition`: fields (public vs secret), environment (sandbox/production), `testConnection()` (read-only API call — Paymob: auth-token request; Fawaterak: authenticated read-only call; UltraMsg: instance status; SMTP: connect/EHLO without sending). Stored state: `enabled`, `environment`, `status` (`unverified|verified|error`), `last_tested_at`, `last_success_at`, `last_error` (sanitised). **Enabling Production requires: status=verified for that exact credential fingerprint, platform env gates satisfied, and an explicit confirmation step.** Saving never enables. Changing any credential resets status to `unverified` and disables the account. Secret fields render as `••••1234` (fingerprint only), are never sent to the browser or logs, and are replaced only when a new value is typed.

### 3.6 Migration strategy (no production secrets exposed)
1. Additive migrations only; Settings keep reading legacy `system_settings` + `.env` until a value is saved in the new store.
2. Artisan command `settings:import-legacy-credentials --dry-run` (never run automatically, never by seeder): reads `payment_methods.config` and `.env`, creates **encrypted** accounts (disabled, unverified), prints a masked report. Ops runs it deliberately on production; plaintext in `payment_methods.config` is left untouched until the new path is verified, then cleared by a second explicit command.
3. Until an account is verified and enabled, runtime keeps using the existing method config → **checkout behaviour is unchanged on upgrade**.

---

## 4. Order assignment (Part 3)

### 4.1 Data model (additive)
* `orders.assigned_user_id` (nullable FK → users, `nullOnDelete`-style handling: a deleted/disabled staff member leaves the order *unassigned*, history keeps the name snapshot), `assigned_by_user_id`, `assigned_at`, **`sold_by_user_id` + `sold_at`** (see §6, set once at payment settlement, immutable).
* `order_assignments` (history, append-only): `order_id`, `from_user_id`, `to_user_id`, `changed_by_user_id`, `reason`, `source` (`manual|round_robin|default|import|system`), `created_at`. Names snapshotted in `meta`.
* `users.can_receive_orders` (bool, default false, opt-in per staff member). **Finding:** `users` has no `is_active`/soft-delete today (staff are removed by hard delete), so "active" cannot be inferred. Proposal: add `users.is_active` (default true) as part of Stage D; eligibility = `can_receive_orders ∧ is_active ∧ exists`. A hard-deleted assignee leaves the order unassigned (FK `nullOnDelete`) and the history snapshot keeps the name.
* Existing orders: all columns null = "Unassigned". Nothing backfilled, no data touched.

### 4.2 Toggle semantics
`orders.assignment_enabled` default **false** (no setting row ⇒ off ⇒ behaves exactly as today).
Off: no scope applied, no new column influences any query/permission, history rows are not written, existing assignments stay as data and are shown read-only to users who could already see the order. Off never widens access: `showing_orders`, `orders.deleted.view`, etc. still apply on top.
On: a single `Order::scopeAssignableVisibleTo($user)` is added to **every** admin order query listed in §2 (list, export, show, edit/update/status/notes/approve/reject, deleted, dashboard metrics, reports, event insights, customers' order lists, payments, global search, `RetryTicketIssuance` stays global because it is a system job). Enforcement is in query scopes + policy-style `abort_unless` in controllers, not in Blade. Customer-detail pages only expose customers/tickets reachable through visible orders.

### 4.3 Permissions (names to confirm; Spatie style used in the repo is `resource.action`)
`orders.assign`, `orders.reassign`, `orders.view_all`, `orders.view_assigned`, `orders.manage_assigned`, `orders.view_unassigned`, `orders.view_agent_stats` (manager dashboard). Seeded roles (idempotent seeders, **not** run on production automatically; delivered as a migration-time `Permission::findOrCreate` — safe, additive): `Order Manager` (view_all, assign, reassign, view_unassigned, view_agent_stats), `Order Agent` (view_assigned, manage_assigned). Super admin = all. With the feature on, a user having plain `orders.view` but neither `view_all` nor `view_assigned` sees **nothing** (fail closed); the settings page warns and offers a one-click "grant view_all to current holders of orders.view" so the rollout can't lock out existing staff by accident.

### 4.4 Modes
* **Manual**: new orders stay unassigned; managers assign/bulk-assign.
* **Round robin**: eligible = `can_receive_orders` ∧ not disabled/deleted ∧ holds `orders.view_assigned|manage_assigned`. Pointer kept in `assignment_state` (single row) advanced inside `DB::transaction` with `lockForUpdate` on that row (works on MySQL; on SQLite the transaction serialises writes) — a unique `order_assignments(order_id) where source != reassign` guard + `whereNull('assigned_user_id')` conditional update means an order can never be assigned twice by two concurrent requests. No eligible user ⇒ stays unassigned (queue).
* **Default assignee**: one eligible user; if they become ineligible ⇒ unassigned queue (never silently falls through to someone else).
Assignment is performed by `OrderAssignmentService::assignOnCreate()` called from the end of `OrderPlacementService::place()` **inside its transaction but wrapped so a failure can never fail checkout** (log + leave unassigned).

### 4.5 UI / stats
Orders list: Assigned To column, assignee + "Unassigned" filter, row action Assign/Reassign, bulk assign (checkbox selection; reuses V2 menu/modal). Order details: assignee card, assigned by/at, change-assignee modal with reason, history timeline. Agent dashboard = same Dashboard page scoped (My Orders / Pending / Paid / Canceled / My Sales / My Tickets Sold) computed from `DashboardMetrics` with the scope applied; Manager dashboard section (Unassigned, per-agent table: assigned, pending, paid, canceled, collected sales, tickets sold, **conversion = paid ÷ (paid+pending+canceled)**, so the "number of assigned orders" is never the sole KPI).

---

## 5. Merchant accounts & payment routing (Part 4) — design

### 5.1 Concepts
* `payment_gateway_accounts`: `id`, `name`, `provider` (`paymob|fawaterak`), `environment`, `enabled`, `status`, `last_verified_at`, `credential_fingerprint`, `supported_methods` (JSON of method codes), `config` (non-secret: iframe id, integration ids per method, provider keys per method, callback info), secrets in `integration_credentials` (encrypted): api key, secret key, public key, **Paymob HMAC secret per account**, Fawaterak bearer key.
* `payment_methods` stays as the *customer-facing method catalogue* (label, icon, code, provider). Per-account method availability is `supported_methods` ∩ active methods. Legacy rows keep working through a **virtual "Legacy / Default" account** that resolves to the current `payment_methods.config` + `.env` behaviour (zero behaviour change until the owner migrates).
* `users.payment_routing_mode` (`default|custom`), `users.payment_account_id` (FK, nullable), `users.allowed_payment_methods` (JSON, nullable ⇒ all the account supports). **No keys on the user row.**
* `orders.payment_account_id` (pinned account the checkout *offered*) and `payment_attempts.payment_account_id` + `payment_attempts.routing_snapshot` (JSON: policy used, resolved assignee at the time, account name/provider/environment — audit trail). Both nullable; old attempts have `NULL` ⇒ resolved via the legacy account exactly as today.

### 5.2 Resolution rules (the "single resolver": `PaymentAccountResolver`)
Order of precedence when an attempt is created, all server-side, never from request input:
1. Per-user routing feature off ⇒ **default account** (Settings → default merchant account, or legacy).
2. Order has `payment_account_id` pinned (set at order creation) ⇒ that account, if still enabled+verified; if it was disabled ⇒ **do not silently fall back for an open payment**: fail the *new* attempt with a clear message and let staff re-route (see 5.4).
3. Else assignee's custom account (if mode=custom, enabled, verified) ⇒ that.
4. Else policy `unassigned_order_payment_policy`: `default_account` (recommended) | `block_online_payment` (manual-confirmation only).
5. Methods offered = `account.supported_methods ∩ active ∩ user.allowed_payment_methods ∩ gateway.isConfigured`.

### 5.3 Checkout timing (Part 4 §14)
Today the method list is shown before the order exists. Proposed flow, no change to what the customer sees except the method list now comes from the resolved account:
1. When the checkout page renders (and on `/checkout/draft`), the server computes the **prospective account** (round-robin peek, default assignee, or default account — a *peek that does not advance the pointer*) and stores it in the **server session draft** (never a hidden form field).
2. On `store`, inside the order-placement transaction: assign (advancing round-robin) and pin `orders.payment_account_id`. If the actual assignee's account differs from what was offered in step 1 (race), the **pinned account is the one that was offered to the customer**, not the new assignee's — the customer's choice was made against that account's methods; the order is still assigned to the new agent for ownership/stats. That implements "arrival of a new owner must not change the gateway once the customer started paying."
3. Optional (Q2): assign *first*, then offer methods — only possible if the order exists before the method choice; rejected as default because it changes the checkout flow and creates orders for abandoned carts.

### 5.4 Reassignment, retry, disable
* Reassigning an order **never touches** existing `PaymentAttempt.payment_account_id`, `orders.payment_account_id`, or settlement of open attempts. Callbacks/inquiry/reconcile resolve credentials **only** from `attempt.payment_account_id` (NULL ⇒ legacy account).
* New attempt after reassignment: default = **keep the order's pinned account** (no surprise re-route). Re-routing to the new assignee's account is a deliberate staff action *"Switch payment account"* (permission `orders.reroute_payment`) which: (a) runs a server-side inquiry on every open attempt of the old account, (b) refuses if any is paid/pending-unknown, (c) otherwise marks them `expired` (reason `rerouted`), (d) pins the new account, (e) writes an audit entry. This is the double-charge guard.
* Paid-late / cancelled / expired behaviour stays exactly as in `PaymentService::settleInTransaction` (review-required, no duplicate ticket issuance).
* Account disabled/credentials invalid ⇒ new attempts refused with a clear error; open attempts keep being reconciled with the account's stored credentials **while the account row exists** (disable ≠ delete; deleting an account that has attempts is blocked).

### 5.5 Callback authenticity with multiple accounts
* **Paymob**: one public callback URL is kept (no change on the Paymob dashboard side). The handler finds the order/attempt from `merchant_order_id` (already how it works), loads **that attempt's account** and verifies the HMAC with **that account's** secret; an unverified request cannot change state (server-to-server inquiry with that account's API key still decides). Mismatch ⇒ 403/ignored, logged without payload secrets. Risk noted: choosing the secret from an unauthenticated field is safe only because a wrong choice fails verification — covered by tests ("Account A callback signed with B's secret is rejected").
* **Fawaterak**: callback route already carries `{order}/{token}`; inquiry uses the attempt's pinned account bearer key and the stored `invoice_id`; browser-supplied ids never select the account.
* Idempotency, amount/currency checks, transaction-reuse constraint, one-ticket-issuance remain in `settle()` untouched; a new check is added: the inquiring account must equal the attempt's account.

---

## 6. Reporting definitions (Part 5)

Three distinct, individually labelled numbers; they are never merged:
* **Assigned Sales** (pipeline): total of non-cancelled orders currently/at-time assigned to the agent, any status.
* **Collected Payments**: sum of `paid` orders (`paid_at` date, same basis as existing Revenue reports) attributed to the agent.
* **Merchant Settlement**: per merchant account, what the gateway collected — **informational**; no settlement/payout is computed or stored. **Refunds** are not modelled in the system today, so are listed as "not tracked" rather than guessed.

Attribution (Q3): recommend **"agent at time of sale"**: `orders.sold_by_user_id`/`sold_at` are written once, in the same transaction that marks the order paid (`settleInTransaction` and the manual approve/mark-paid paths), from the assignee at that instant, and are immutable. Pending/Canceled use the *current* assignee (they are workflow states). Reassigning later never moves paid history; the history table gives the full trail. Historical orders: `sold_by` stays NULL (reports show them under "Unattributed (before assignment)") — nothing is back-filled by guessing.

---

## 7. Decisions required before Stage D/E (these change who collects money)

| # | Question | Options | Recommendation |
|---|---|---|---|
| Q1 | Order without an assignee at payment time | (a) use **Default Merchant Account**; (b) **block** online payment (manual confirmation only) | (a), selectable in Settings, default (a) |
| Q2 | Method list vs. assignment timing | (a) account **pinned by what the customer was offered** (§5.3); (b) create the order first, then offer methods | (a) — no checkout flow change |
| Q3 | Report attribution | at-time-of-sale / current assignee / completer | at-time-of-sale for paid; current for open (§6) |
| Q4 | Retry after reassignment | (a) keep original account; (b) auto-switch to new assignee's | (a) + explicit audited "Switch payment account" action |
| Q5 | Migrating existing plaintext keys | (a) explicit one-off command, left in place until verified; (b) automatic migration | (a) |
| Q6 | Scanner screens + staff `is_active` flag | restyle scanner in place vs. into admin shell; add `users.is_active` (none exists today) | in place; add `is_active` |
| Q7 | Permission names | proposed in §4.3 | confirm |
| Q8 | Fawaterak multi-account | The `getInvoiceData` response semantics (`data.paid`) are still **unverified** upstream (see `FawaterakGateway` docblock). Per-account Fawaterak stays behind `PAYMENTS_FAWATERAK_VERIFIED`; please confirm you can run a sandbox transaction before Stage E go-live. | agree |

Real-payment enablement stays **off** until explicit approval after sandbox tests (fake gateway/mocked HTTP only in automated tests).

---

## 8. Plan after approval
* **B** Admin UI completion (4 leftovers + scanner restyle + consistency pass, Playwright screenshots at 360/768/1024/1440 → `docs/screenshots`).
* **C** Settings registry/service/audit/encryption, Settings UI sections, integration tests (mocked HTTP), legacy-import command.
* **D** Assignment (migrations, permissions, scopes everywhere, modes, UI, dashboards, tests incl. isolation).
* **E** Merchant accounts, resolver, attempt pinning, callback/inquiry per account, per-user configuration, reassign-during-payment tests.
* **F** Full regression (existing 307 + new), SQLite and MySQL run, security report.

Each stage lands as its own commit set on branch `claude/v2-admin-settings-assignment`; nothing merges to `main` without your review.
