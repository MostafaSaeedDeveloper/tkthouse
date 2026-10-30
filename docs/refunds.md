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

## Production-readiness review notes
- **Migration name**: `2026_12_11_000001_...` is intentionally kept. It must sort after `2026_12_03_000001_create_payment_refunds` and `2026_12_10_000004_add_created_by_to_tickets`; an October-dated name would run BEFORE the table it alters and break fresh installs. Verified on MariaDB against a database holding pre-existing refund/order/ticket rows (untouched, FK kept), plus rollback and re-migrate.
- **Permissions**: the dashboard refund cards and the `Orders?refunded=1` list require `payments.refunds.view` (plus `orders.view` for the link).
- **MySQL/MariaDB**: `DB_CONNECTION=mysql DB_HOST=127.0.0.1 DB_PORT=<port> DB_DATABASE=<scratch db> php artisan test tests/Feature/Mysql` runs the multi-process concurrency suite (confirmation races, ticket-claim races, real rollback).
- **Known gap**: the customer ticket download pages still serve the PDF/QR of a refunded ticket; the scanner rejects it server-side.

## Order Assignment isolation (audit result)
Refunds use the EXISTING access model only (`OrderAccess` / `visibleTo()` / `assignedScope()`); no second assignment system. A refund is reachable if and only if its order is: the refunds list, refund pages, every refund action, the order page panel, transaction pages, the refunded-orders list, dashboard figures and KPI links all derive from `Order::visibleTo()` / `assignedScope()`, so multiple assignees, `view_unassigned`, super/`orders.view_all` and reassignment behave exactly as for orders. `payments.refunds.view` does not grant access to any order. Acting needs the refund permission AND `orders.manage_assigned` on that order. Defect found and fixed: the webhook event list and the imported provider-transaction screens were provider-wide; they now use the existing `orders.global` middleware (closed to order-scoped users).

## Fawaterak refunds
No official Fawaterak refund-creation API could be confirmed. The official reference (fawaterak-api.readme.io, indexed pages) documents invoice/payment/tokenization endpoints and a refund *webhook body* only (no hash documented, status "approved" = approval, not money returned). A community SDK mentions refund requests but gives no endpoints and is not official. The system therefore treats Fawaterak refunds as **Manual / External**: nothing is sent to Fawaterak; a person refunds in the Fawaterak dashboard and records the real reference and date. No speculative API integration was added. To re-verify, the session needs network access to fawaterak-api.readme.io / the Fawaterak dashboard docs, or ask Fawaterak support for the refund API reference and merchant enablement.

## Fawaterak external refund tracking (manual refund, tracked in TKT House)
- **Webhook URL to configure in Fawaterak**: `POST https://<your-domain>/payments/fawaterak/webhook_json` (route `front.fawaterak.webhook`; the path must end in `_json`; throttled 240/min).
- **Direct merchant link**: Fawaterak documents none, so none is invented. Optional `FAWATERAK_DASHBOARD_URL` (https only, no credentials/query) opens YOUR Fawaterak dashboard; the modal always offers *Copy invoice / transaction ID*. The customer payment URL is never used.
- **Reference**: invoice id / reference number come from the stored payment attempt; for legacy orders a person records the original Fawaterak id (audited, never overwrites a stored one).
- **Refund webhook** (documented body: transactionId, amount, currency, status "approved", reason, approvedAt; no hash): validated, recorded idempotently, matched by exact original id → the outstanding manual request of THAT payment/merchant account with the exact amount and currency. Ambiguous, mismatching, unknown-status, pre-approval or unmatched events are kept as `needs_review` and only authorized staff are alerted. A match only marks the refund *Notification received — needs verification*; it never completes it.
- **Completion**: Fawaterak documents no refund-status API (and the payment inquiry does not describe refunds), so TKT House cannot verify a refund itself. A person with the confirm permission records the real reference and date (existing audited `confirmSucceeded`), which atomically cancels tickets, updates the order, dashboards and scanner. Unsigned webhooks, opening the dashboard, or screenshots alone never confirm.
- Tracking is stored in the database (no queue/scheduler). `GET payments/refunds/{refund}/tracking` is a read-only snapshot used by the modal's 5-second poll; it follows the same order-assignment access as the refund page.

## Switching OFF the second-person approval (one team member runs the whole refund)
**How (existing Settings UI, audited):** sign in as staff with `settings.view`, `settings.update` and `settings.payment-accounts.manage` → *Settings → Payments → Payment Routing* → untick **"Require a second person to approve refunds"** → Save. The change is written to the settings audit log and applies immediately. Re-tick it to restore approval; nothing is deleted (approval code, `payments.refunds.approve`, audit logs all stay). The code default is still ON, so nothing changes at deployment until someone saves this setting.

**With approval OFF:** an agent opens a paid order they manage → Refund (full/partial, tickets, amount, reason) → a manual (Fawaterak) refund is immediately **Processing externally** (no second user) → *Open Transaction in Fawaterak* / copy the invoice id → the refund is carried out in the Fawaterak dashboard → the Fawaterak webhook only adds "notification received — needs verification" → a person with refund rights on that order records the real reference, date and evidence (*Confirm completed*) → the system marks it succeeded and atomically cancels the covered tickets, updates the order, scanner, dashboards. Opening Fawaterak, the unsigned webhook, or removing the approval requirement never marks anything completed.

**Requests that were created while approval was ON and still wait:** turning the setting off does **not** approve or change them. Each shows a notice on its refund page and an explicit **"Release for external processing"** action (note required, audited as `released_without_second_approval`), available to staff with the approve permission or to the requester on their own order; or *Withdraw request*. Release only moves it to "processing externally"; it still needs the verified confirmation. Releasing is refused while the setting is ON. Before deploying, review the Refunds list for "Awaiting approval" items.

**Refunds already in flight** (processing) are not disturbed if approval is switched back ON; new requests are gated again.

## Customer refund e-mail (ONE e-mail, only after completion)
The customer receives exactly **one** refund e-mail: **"Your Refund Has Been Processed Successfully — TKT House"**. Nothing is sent for a request, an approval, "processing externally", a provider notification/webhook (including status `approved`), or any pending / failed / rejected / unknown / unverified refund. The earlier "received" and "approved/processing" e-mails were removed (templates, translations, triggers, tests). Existing order canceled / status e-mails are untouched.

It is sent only when ALL of these are true (re-checked under a row lock): the refund is `succeeded` (audited confirmation or trusted provider evidence) · its ticket effects are done (`applied`, or `not_applicable` for money-only) so the covered tickets really are canceled · the order is already `refunded` / `partially_refunded`. A refund held back for ticket review (e.g. two paid payments) is announced only once an approver cancels the tickets.

- Layout: `emails.layouts.base` (same logo, components, footer, tone as the order status/canceled e-mails), to the order's customer e-mail, synchronously through the normal mailer (no queue worker/scheduler).
- Content: customer name, order number, event, confirmed amount + currency, full/partial, own refund reference (`REF-…`), completion date, reason, "being returned through the original payment method … may take some additional time … depending on your bank or payment provider" (no fixed timeframe is promised: no official provider timeline exists), tickets canceled; for partial refunds the remaining tickets stay valid; money-only refunds say no tickets were canceled.
- **No gateway names**: data comes from `RefundMailData` (no provider id/reference, dashboard, API or internal notes). The staff reason is shown to the customer; reasons containing provider names, links, e-mails, long numbers or words like gateway/dashboard/internal become "Refund arranged by our team". The timing sentence is editable in Settings → Notifications (*Refund e-mail: time-to-appear note*; provider names are ignored) and translatable in `lang/{en,ar}/refund_mail.php` (Arabic with RTL; customers have no stored language, so the app locale is used).
- Delivery: dispatched with `DB::afterCommit`; one per refund (row-locked claim in the refund meta; repeated webhooks/confirmations/retries never duplicate); a failure is logged (`order_delivery` activity "Delivery failed: refund_completed", refund timeline) and never rolls back the refund, order or tickets; the refund page shows a **Resend** button for a failed e-mail only (a delivered one is never re-sent).
- Preview: `docs/email-previews/` (desktop + mobile, English + Arabic).
