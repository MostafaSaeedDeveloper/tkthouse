# Stages B–D report (branch `claude/v2-admin-settings-assignment`)

Tests: **347 passing** on SQLite (307 baseline + 40 new). MySQL was not available in this environment, so MySQL-specific behaviour
(row locks on `assignment_state`, FK `nullOnDelete`) is reviewed but **not executed** — run the suite on a MySQL test DB before release.
Browser QA: 11 screens × 4 widths (360/768/1024/1440) rendered with real data on a throw-away SQLite DB; no horizontal scroll on any. Screens in `docs/screenshots/v2/`.
No real payment, gateway call, e-mail or WhatsApp message was made; nothing touched production data; no seeder was run outside the throw-away DB.

## Stage B — Admin V2 completion
Audit showed ~90% already on V2. Done: scanner + scanner login keep their standalone dark door-side layout but use V2 tokens (#FFD200, Inter);
last legacy button (payment-method form) replaced; Settings pages rebuilt on V2 components. Remaining legacy markup is only Bootstrap's own modal close button (styled by the V2 CSS).

## Stage C — Settings Center
* `Registry` (code) declares every manageable key; `SettingsService` = defaults → DB → fallback, validation, audit (`setting_audits`), shared-cache invalidation. `SystemSettings` is now a facade, so all existing call sites work unchanged.
* Sections: General, Branding, Events & Tickets, Orders, Notifications, Security & Access (+ Integrations, Payment methods). Per-section permissions (`settings.view/update/orders/integrations.*/security.view`).
* Wired to real consumers: site name/colours/logos/favicon, booking expiration, default max tickets (ticket's own limit wins), WhatsApp toggle, **frontend maintenance flag** (public site only; admin, scanner, checkout and payment callbacks stay online).
* Read-only on purpose: timezone, locale, live-gateway flags, Fawaterak verification, Paymob signature requirement (environment-owned; shown so staff can see *why* something is unavailable).
* Integrations (Paymob, Fawaterak, UltraMsg, SMTP): encrypted at rest, masked, never re-sent to the browser, never logged; blank secret keeps the old one; any credential change disables + unverifies; saving never enables; enabling needs a passing test of the *current* credentials, production confirmation and the platform gates. Test Connection is read-only: Paymob auth-token, UltraMsg instance status, SMTP greeting (no mail). Fawaterak has no confirmed read-only endpoint, so it can be stored but **cannot be verified or enabled** until the Stage E sandbox run.
* Runtime use today: a verified+enabled UltraMsg or SMTP account overrides `.env`; Paymob/Fawaterak accounts are stored only — checkout still uses the existing payment-method configuration until Stage E.
* Migration of legacy secrets: `php artisan settings:import-legacy-credentials --dry-run` → run → verify → (rollback available: `--rollback`). It copies plaintext keys from `payment_methods.config`/`.env` into encrypted, **disabled, unverified** accounts and never modifies or deletes the legacy values.
* **Not done** (no backing feature exists, so no fake UI): e-mail/WhatsApp *templates* and per-trigger toggles, OTP policy editing, QR settings. Listed rather than invented.

## Stage D — Order assignment
* Additive migration; feature **off** by default. Orders: `assigned_user_id`, `assigned_by_user_id`, `assigned_at`, `sold_by_user_id`, `sold_at`; `order_assignments` history; `users.is_active` (default true), `users.can_receive_orders` (default false).
* Access: `OrderAccess` (one choke point). Off ⇒ identical to today. On ⇒ super admin / `orders.view_all` see all; `orders.view_assigned` sees own (+ unassigned with `orders.view_unassigned`); anyone else sees nothing. Enforced in query scopes and controllers for orders (list, show, edit, update, status, notes, approve, reject, delete/restore, export, deleted list), customers, tickets (list/show/edit/send/download), search, payments, and the global dashboard/reports/guest-list/event-insights (closed to scoped users — they get **My dashboard / My report** instead). Acting on an order additionally needs `orders.manage_assigned` and ownership.
* Modes: manual, round robin (row-locked state, one turn per order, never double-assigns), default assignee (ineligible default ⇒ Unassigned queue, never someone else). Auto-assignment can never fail checkout.
* Rollout safety: enabling requires the actor to keep `orders.view_all`; staff who would be locked out are listed and need acknowledgement or a one-click `orders.view_all` grant. Disabling never removes assignees or history.
* Reporting definitions (never blended): **workflow counts** by current assignee; **collected sales / tickets sold** by the immutable `sold_by_user_id` snapshot written when payment is confirmed (gateway settlement and manual status change); **win rate** = paid ÷ (paid + canceled). Orders paid before attribution existed show as **Unattributed**. Refunds and merchant settlement are not modelled in the system and are not shown.
* Users: inactive accounts cannot sign in (password, scanner login, OTP) and are signed out on their next request; super admin, yourself and customer accounts (no role/permission) cannot be deactivated from the Users screen; flags are only changed when the form sends them. Customer/passwordless flows are unaffected (staff are already excluded from OTP login).

## Findings you should know
1. `RolesAndPermissionsSeeder` **truncates all permission tables** (including users' direct permissions). It was never changed to be safe; I added the new permissions/roles to it so a re-run does not drop them. Do not run it on production.
2. New permissions are granted automatically only to super-admin-named roles; every other role is opt-in (the two new roles `Order Manager`/`Order Agent` are created).
3. UltraMsg test uses `GET /{instance}/instance/status` and treats `authenticated` as healthy — verify against your real instance once.
4. Not implemented yet: payment routing (Stage E, design awaiting approval in `docs/v2-stage-e-design.md`).
