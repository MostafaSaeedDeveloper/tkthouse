# PR #147 — Deployment Checklist

Scope: Admin V2, unified payments, refunds (second-person approval), event ticket integrity, event archive/delete protection, Payments workspace (local + imported transactions, provider inquiry, known-transaction sync).
Live refunds stay **off**. No automatic merge.

## 0. Before anything (Production blockers, not Staging)
- [ ] Rotate/revoke the Fawaterak key that was committed earlier (still in git history) — see `docs/security-secret-rotation.md`.
- [ ] Sandbox verification with real Paymob/Fawaterak sandboxes: payment, signed webhooks, lookup/refund/void response shapes.
- [ ] Merchant routing verified account by account (`docs/v2-payments-deployment.md`, gradual activation runbook).
- [ ] Refund permissions and approver roles signed off.
- [ ] `events:audit-tickets` run on an **isolated Production copy** and reviewed. Legacy backfill is NOT part of this release.

## 1. Environment (.env)
```
PAYMENTS_FAKE_ENABLED=false
PAYMENTS_REFUNDS_LIVE=false
PAYMENTS_LIVE_GATEWAYS=false      # true only per-account during activation
PAYMENTS_FAWATERAK_VERIFIED=false
```
Mail/queue/cache as today. Do not use `MAIL_MAILER=log` in tests.

## 2. Release steps (Staging first, then Production)
1. Backup the database (required — migrations are additive but irreversible in data terms).
2. Maintenance mode on; `git pull` the merged commit; `composer install --no-dev -o`.
3. `php artisan migrate --force` — new migrations, in order:
   - `2026_12_04_000001_refund_approval_audit`
   - `2026_12_06_000001_ticket_type_integrity`
   - `2026_12_07_000001_event_archiving`
   - `2026_12_08_000001_external_transactions_and_sync`
   (all additive; each has a tested `down()`).
4. `php artisan payments:sync-permissions` (additive; never run the destructive seeder on Production).
5. `php artisan payments:validate` — must report no blocking issue.
6. `php artisan config:cache && php artisan route:cache && php artisan view:cache`.
7. Scheduler running (`schedule:run` every minute): `payments:sync` (15 min), `payments:reconcile-refunds`, order expiry.
8. Maintenance mode off.

## 3. Smoke tests
- [ ] Admin login; Events list (Archived filter), create/edit event keeps existing ticket ids.
- [ ] Archive an event on Staging: gone from public site (404), no checkout; paid orders/tickets/QR/check-in unaffected; Restore requires readiness.
- [ ] Delete blocked for an event with any order/ticket history.
- [ ] Fake gateway payment (Staging only) → Paid → ticket issued; refund request needs a second approver.
- [ ] Payments → Transactions, Imported (CSV upload of a sample), Capabilities page; CSV never changes an order/attempt status.
- [ ] Webhook replay is idempotent (no duplicate settlement).
- [ ] Agent sees only own-scoped transactions.

## 4. Rollback
- Code: redeploy previous release; `php artisan migrate:rollback --step=4` only if no new data must be kept
  (rollback drops `external_transactions`/`provider_syncs` and archive columns). Prefer restoring the backup if orders were taken meanwhile.
- Keep `PAYMENTS_REFUNDS_LIVE=false`; disable the scheduler entries to stop provider sync.
