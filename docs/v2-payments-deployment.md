# Deployment, migration and rollback — Payments V2 (PR from `claude/v2-admin-settings-assignment`)

**Nothing in this PR enables real refunds.** `PAYMENTS_REFUNDS_LIVE` stays unset/false; real gateways, credentials and customer data are not touched.

## 0. Before deploy
1. Back up the database and `.env`.
2. **Rotate the Fawaterak API key** (`docs/security-secret-rotation.md`).
3. Ensure `.env` does **not** set `PAYMENTS_FAKE_ENABLED`, and sets `PAYMENTS_REFUNDS_LIVE=false` (or leaves it unset). See `.env.example`.
4. Do **not** run `db:seed`, `RolesAndPermissionsSeeder` or any seeder on production (the permissions seeder truncates permission tables).

## 1. Deploy steps
```
git pull / deploy the merged commit
composer install --no-dev --optimize-autoloader
php artisan migrate --force            # additive only, see list below
php artisan payments:sync-permissions  # DRY RUN: shows exactly what would be granted
php artisan payments:sync-permissions --apply   # after you reviewed the dry run
php artisan payments:validate          # read-only data check on legacy payments; review the output
php artisan optimize:clear && php artisan config:cache && php artisan route:cache
```
Scheduler: the existing cron (`schedule:run`) now also runs `payments:reconcile-refunds` every 10 minutes (a no-op while no API refund is in flight).
Optional later: `php artisan payments:validate --backfill-references` fills only missing `internal_reference` values for old attempts.

## 2. Migrations (all additive; none drops or rewrites existing data)
| Migration | What it does |
|---|---|
| 2026_11_01 settings center | settings, integration accounts (encrypted), audit tables |
| 2026_11_02 order assignment | assignment tables/columns, `users.is_active`, sales attribution snapshot |
| 2026_11_10 payment routing | merchant-account pointers, provisional reservations; legacy payments keep `integration_account_id = NULL` |
| 2026_12_01 payment foundation | `payment_methods.sort_order`; creates the `payments.*` permissions; copies only from equivalent payment-account permissions; Super Admin gets all |
| 2026_12_02 tracking & webhooks | `payment_attempts` reference columns, `webhook_events`, account webhook-configured stamps |
| 2026_12_03 refunds | `payment_refunds` (FK RESTRICT), account refund-verified stamps |
| 2026_12_04 refund approval audit | `payment_refunds.approved_at` |
| 2026_12_07 event archiving | `events.archived_at/archived_by/status_before_archive`; creates `events.archive` / `events.restore` and copies them only from `events.delete` holders — see `docs/event-deletion-protection.md` |
| 2026_12_06 ticket-type integrity | `order_items.event_ticket_id` (nullable, indexed, no FK), `event_tickets.sort_order` — see `docs/event-ticket-integrity.md`; afterwards run `php artisan events:audit-tickets` (read-only) |

Existing orders, payment attempts, method codes, transaction numbers and stored gateway data are untouched.

## 3. Post-deploy smoke test (≈10 min, no money moves)
* Checkout still opens and lists the same payment methods (Visa/Wallet legacy codes intact).
* Settings → Payments: Methods, Providers, Webhooks, Routing load; secrets show masked.
* Payments → Overview/Transactions/Refunds load; an old order's Order Details shows Payment Information without errors.
* Log in as an Order Agent: only own orders' transactions; no refund buttons. As Order Manager: can request a refund; cannot approve; no merchant-account menu.
* *Do not* press Refund on a real payment. Refund remains blocked for API refunds by the environment gate.

## 4. Rollback
* **Preferred:** redeploy the previous application release and leave the schema in place — the new columns are nullable or defaulted and the new tables are unused by the old code (this was reviewed, not executed against the old release: rehearse it on staging first), and `payments:sync-permissions` only added permissions (harmless).
* Turn the feature off without redeploying: `PAYMENTS_REFUNDS_LIVE=false`; disable *Confirm refunds verified* on accounts; set *Require a second person…* ON (default).
* Schema rollback (`php artisan migrate:rollback --step=N`) is possible (`down()` implemented) but **drops the new tables/columns and their data** (refund history, webhook events): export them first. The 2026_12_01 permission migration intentionally keeps permissions and `sort_order` on rollback.
* Provider webhook URLs registered during rollout (`/payments/fawaterak/webhook_json`, `/payments/paymob/callback`) would not exist in the previous release: note the previous URLs before changing anything in a provider portal, and revert them if you roll back.

## 5. Environment flags
| Flag | Default | Meaning |
|---|---|---|
| `PAYMENTS_REFUNDS_LIVE` | false | enables Paymob refund/void API calls (after per-account attestation) |
| `PAYMENTS_LIVE_GATEWAYS` | production: true; elsewhere false | real Paymob/Fawaterak calls outside production only with sandbox credentials |
| `PAYMENTS_FAWATERAK_VERIFIED` | false | production Fawaterak payments gate until sandbox verification |
| `PAYMENTS_FAKE_ENABLED` | false | fake gateway; ignored in production |
