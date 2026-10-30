# Refund management: behaviour, deployment and checks

## How it works
- **Request** (`payments.refunds.create`) → **Approve** (`payments.refunds.approve`, a different person while `refund_approval_required` is on) → **Processing** (submitted) → **Succeeded** (explicit confirmation with reference, evidence note and real refund date). Also rejected / failed / unknown. Requesting, approving, processing, failing, rejecting or an unknown outcome never changes tickets or the order.
- **Legacy paid orders** (no payment attempt) are refunded manually against the order (`payment_attempt_id` is NULL, provider `manual`). No gateway transaction is invented and the original payment data is untouched.
- **On confirmed success only**, in the same DB transaction: the covered tickets become `canceled` (`cancellation_source=refund`, reason, `canceled_by_refund_id`; never deleted; `checked_in_at` and scan logs kept; checked-in tickets get `refund_flag=refunded_after_check_in`), and the order becomes `refunded` (confirmed refunds >= order total) or `partially_refunded`. If anything fails the whole confirmation rolls back and can be retried. Repeats find a final refund and do nothing.
- **Full / void** refund: all still-valid tickets. **Partial**: only the selected tickets (amount <= their paid share), or an explicit "money only". Orders with more than one paid payment, or refunds created before this release, are flagged `needs_review`; an approver selects tickets on the refund page.
- **Scanner**: a refund-canceled ticket is rejected server-side on lookup and on any status change (HTTP 409, `REFUNDED / CANCELED`, "Refund confirmed — <reason>", cancel date). Audit rows: `lookup_rejected_refunded`, `status_update_rejected_refunded`. Admin ticket edit/delete cannot revive or remove such a ticket. Amounts, references and internal notes are never shown to scanners. Scanner lookup is now also limited to the scanner user's managed events (previously it read a non-existent relation and was unscoped).
- **Order edits**: "refunded"/"partially refunded" cannot be typed in; an order with confirmed refunds keeps its status; quantities, prices, promo and fees are locked while a refund exists.

## Financial definitions
- Captured payments: orders with status `paid`, plus `refunded`/`partially_refunded` orders that have a confirmed refund record (a refund never erases the sale; a hand-edited "refunded" order without a record stays out, as before).
- Refunded money: only `succeeded` refunds, dated by `completed_at`.
- **Net Collected Revenue = Captured payments − Confirmed refunds** (each by its own date, same event / statistics-exclusion / role scope). Pending requests are shown separately.
- Dashboard cards: *Total Refunded* (sum) and *Refunded Orders* (distinct orders); both link to `Orders?refunded=1&stats=1&refunded_from&refunded_to[&refund_event_id]`.
- Not changed: tickets-sold counts stay gross; event check-in reports count `checked_in` tickets, so a ticket refunded after check-in leaves that count (its scan history remains).

## Migration (additive only)
`2026_12_11_000001_refund_order_and_ticket_integration`: new nullable columns on `payment_refunds` and `tickets`, new table `payment_refund_tickets`, `payment_refunds.payment_attempt_id` made nullable. No data is backfilled; no existing refund, order or ticket is modified.

## Deployment steps
1. Back up the database. Deploy the branch. No env changes; do NOT enable `payments.refunds.live_api_enabled`, the scheduler or workers.
2. `php artisan migrate --force`, then `php artisan view:clear` (and `route:clear` if routes are cached).
3. Any refund still *requested/processing* at deploy time: confirming it afterwards marks it `needs_review` (never auto-cancels tickets); use "Cancel selected tickets" on the refund page.
4. Smoke test on staging with a test order: manual refund → approve (second user) → confirm → tickets canceled, scanner shows REFUNDED / CANCELED, dashboard cards update.

## Manual checks before real refunds
- Confirm who holds `payments.refunds.create` / `.approve`; keep `refund_approval_required` ON.
- One test refund per payment type (Fawaterak order, legacy order) on a staging copy, including a partial refund with ticket selection.
- Brief the scanner team on the refused-entry screen; reasons are shown to scanners, so keep them factual.
- Provider refunds are still done in the provider portal; enter the real reference and date when confirming.

## Tests
`tests/Feature/RefundTicketIntegrationTest.php` (new), `RefundTest.php` (updated).
