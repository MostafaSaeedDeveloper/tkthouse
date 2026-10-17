# Event deletion protection, archiving and the legacy ticket audit

## What was wrong
`EventController::destroy()` did `$event->delete()`. Through the foreign keys that **cascade-deleted** the event's ticket types, fees, images and lineup, and **set to NULL** `event_id` on `order_items`, `tickets` (QR / check-in / guest list) and `scan_logs`. Orders, payments and issued tickets survived, but detached from their event (reports, scoping by event, the scanner's event label, "which event is this order?" all degraded), and nothing warned the admin.

## Delete flow now (`App\Services\Events\EventLifecycle::delete`)
One transaction; the event row and its ticket-type rows are locked (`FOR UPDATE`); the dependency check runs **inside** that lock; only then is the event deleted. Deletion is refused when **any** of these exist (searched through every way the schema, and its history, ties data to an event — not only `orders.event_id`, which does not exist):
| Evidence | How it is found |
|---|---|
| Sold tickets | `order_items.event_id`, or `order_items.event_ticket_id` ∈ the event's ticket types, or (very old rows with no event id) the text `"Event - …"` |
| Orders | via those order items (including soft-deleted orders) |
| Payment attempts, refunds (and through them webhook events) | by `order_id` of those orders |
| Issued tickets | `issued_tickets.order_item_id` |
| QR / check-in records **and guest-list attendees** (no order at all) | `tickets.event_id`, or `tickets.order_item_id`, or (no event id) the name text |
| Scanner logs | `scan_logs.event_id`, or (no event id) `event_name` |
The text matches are deliberately conservative (they may block more, never less). A refused delete shows a clear message ("…cannot be deleted permanently because it has: 3 order items…, 1 payment attempts… Archive it instead"). Only configuration rows (ticket types with no sale, fees, images, lineup) go with an event that has no history at all.
**Concurrency:** order placement now takes the same event-row lock first and re-reads the event status under it. Delete vs checkout therefore serialises: either the order commits first (the delete then sees it and refuses) or the delete commits first (the checkout is refused / finds no event). Verified with real parallel processes on MariaDB (6 trials: no sale reported as successful ever disappears; no order item is ever left without its event).

## Archive instead of delete
* `events.status = 'archived'` plus `archived_at`, `archived_by`, `status_before_archive` (additive migration). **Same event id, same ticket-type ids**; orders, payments, refunds, issued tickets, QR, check-ins, scan logs are not touched (tested: row counts, ids and prices identical before/after).
* **Off sale:** public lists already filter on `active`/`sold_out`; the public event page returns **404** for archived events; `CheckoutSelection::isEventBookable` (status = active) refuses new carts; `OrderPlacementService::place` re-checks under the event lock (so a customer who loaded the page before the archive is refused too); `PaymentService::open` refuses a **new** payment session for an order of an archived event.
* **Money already in flight is not affected:** settlement of an existing attempt (callback / reconcile) does not pass through `open()`; it still completes and issues tickets exactly once (tested, including replay).
* **Check-in policy:** archiving never invalidates an issued ticket. The scanner lookup / check-in and the customer's ticket page do not read the event status (tested and verified in the browser). Refunds for old orders still work (tested).
* **Restore** (`events.restore`): returns the event to its previous status **only if it is ready to sell** (name, location, time, a date that has not passed, at least one active ticket type); otherwise it comes back as **draft** (not public) and the reasons are shown. A form can never un-archive an event: the status select is locked and the server ignores it; only Restore changes it.
* **Permissions:** `events.archive`, `events.restore` (granted by the migration only to holders of `events.delete` and super admins), `events.delete` still gates permanent deletion.

### Payment logic change (documented as requested)
The only payment-code change is the guard at the top of `PaymentService::open()` (no new payment session for an archived event). It is necessary to meet "no new bookings/payment attempts on archived events"; refund, settlement, webhook and routing logic are unchanged.

## Admin UI (Admin V2, existing design)
Events list: Current / Archived / All tabs, "Archived" status badge, **Archive** or **Restore** per row, **Delete permanently** only on rows that have no history (server re-checks). Event details: Archive/Restore buttons, an "Archived" notice, and a **Deletion** card that lists exactly what blocks deletion. Edit page: archived banner with Restore.

## Legacy ticket audit (`php artisan events:audit-tickets [--event=ID] [--limit=N]`, read-only)
Classes (only CONFIRMED is a link; nothing else is ever treated as one):
| Class | Meaning |
|---|---|
| CONFIRMED | order item carries `event_ticket_id` and that ticket type exists |
| LINKED-TYPE-GONE | carries an id whose ticket type no longer exists (broken relation) |
| EXACT TEXT | no id; exactly one ticket type of the same event has exactly that name, event-name prefix equal (text only; a rename/recreate since the sale can make this wrong) |
| PROBABLE | no id; the ticket part matches one type but the event name differs (event renamed) |
| AMBIGUOUS | no id; several types could match (duplicate names) |
| MISSING | no id; nothing matches (renamed / deleted / recreated) |
| UNKNOWN | no id and no event |
Also reported: duplicate ticket-type names per event; **events possibly affected by the old delete-and-recreate** (activity-log "Event updated" entries written before the id-preserving fix + non-contiguous ticket-type ids as a hint, with the number of that event's legacy items that are not CONFIRMED/EXACT); broken relations (legacy `order_items.ticket_id`, issued tickets / QR records / scan logs / payment attempts whose parent is missing); order items without an event.
On the development database used for QA (test data, not Production): 16 items — 3 CONFIRMED, 13 EXACT TEXT, 0 AMBIGUOUS/MISSING/PROBABLE/UNKNOWN, 0 broken relations. **Production has not been audited.** Run it on a Production copy first.
A backfill is **not implemented and not run**; the proposal (rules, confidence, dry run, backup, manual review, rollback) is in `docs/event-ticket-backfill-proposal.md`.

## Tests
* `EventLifecycleTest` (16): delete refused for each kind of history (order items by event id, by ticket-type id only, by text only, guest-list attendee, QR by name, scanner log by id and by name, payment attempt) with nothing lost; refunds/issued tickets block; clean event deletable; permissions; archive keeps ids/rows and is idempotent; off the public site and not bookable; stale checkout refused; hidden ticket not purchasable; no new payment attempt but in-flight money settles once; check-in and customer ticket page work; refund for an archived event's old order; list filter/labels and delete button only where allowed; edit cannot un-archive; restore ready / not ready (→ draft) / no-op.
* `EventTicketAuditTest` (2): all confidence classes; ambiguity never "exact"; report writes nothing.
* `ConcurrencyMysqlTest` (+2): delete vs checkout, archive vs checkout (real parallel processes).
* Browser journey (Chromium, Fake gateway, dev data): create event + tickets → customer pays → list shows Archive but no Delete → details page explains why → forged DELETE refused, event intact → archive → hidden from default list, shown under Archived → sale/QR/issued unchanged → public page 404, checkout refused → customer (verified) still opens the ticket, scanner lookup OK → restore → public again → an event without history shows Delete permanently and it works → mobile list. Screenshots: `docs/screenshots/event-lifecycle/`.

## Decisions still open
1. Whether archived events should also disappear from admin event pickers (orders/customers filters) — they currently stay, for historical reporting.
2. Whether pending, unpaid orders of an event being archived should be auto-expired (today they stay pending and simply cannot start a new payment; the existing expiry job handles them).
3. Legacy backfill (see proposal) — optional, needs approvals.
