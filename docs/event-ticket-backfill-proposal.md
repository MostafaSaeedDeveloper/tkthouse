# PROPOSAL ONLY — linking legacy order items to ticket types (`order_items.event_ticket_id`)

**Status: not implemented, not run on any data.** `events:audit-tickets` is read-only and never links anything. This document describes how a backfill *could* be done safely if the business decides it is worth it. Nothing here may be run on Production without the approvals listed in §7.

## 1. Why it is optional
New sales are linked by id since the ticket-integrity release. Old sales keep their immutable snapshot (name, price, line total, issued tickets, QR, check-ins), so nothing is broken without a backfill. A backfill would only improve "was this ticket type sold?" answers for events edited before the fix, and report grouping by ticket type.

## 2. Matching rules (all must hold for an item to become a *candidate*)
1. `order_items.event_ticket_id IS NULL` and `order_items.event_id` is not NULL.
2. The item text is exactly `"<event name> - <ticket name>"` where `<event name>` is the **current** name of the item's event (prefix equality, case-sensitive, trimmed).
3. Exactly **one** ticket type of the **same event** has that exact name (no duplicates, case-sensitively and case-insensitively).
4. The ticket type was **not recreated after the sale**: `event_tickets.created_at <= orders.created_at` of the item's order. A ticket type created later than the sale cannot be the one that was sold (typical sign of the old delete-and-recreate).
5. The price is consistent: `order_items.ticket_price` equals the ticket type's current price **or** the item's price appears in the event's price history from the activity log. Mismatch alone does not reject (prices are edited legitimately) but lowers confidence.

## 3. Confidence criteria
| Level | Criteria | Action |
|---|---|---|
| **A — high** | rules 1–4 hold, price equal, event has **no** old-style saves in the activity log and no id gap | eligible for scripted backfill after dry run |
| **B — medium** | rules 1–4 hold but price differs, **or** the event has old-style saves / id gaps | manual review item by item (ticket id history from backups if available) |
| **C — low / none** | duplicates, event renamed (prefix differs), ticket created after the sale, no match | never auto-linked; stays text-only forever or is resolved by a person with evidence |
Only level A may be scripted. B requires an approver's sign-off per event. C is never linked.

## 4. Dry run
`events:backfill-tickets --dry-run` (to be written only after approval) must be read-only and output a CSV: `order_item_id, order_number, event_id, item text, candidate ticket_type_id, rule results (1–5), confidence`, plus totals per level and per event. The CSV is reviewed and signed off before any write.

## 5. Backup requirements
* Full database backup immediately before the run (verified restore on staging).
* Export of `order_items(id, event_id, event_ticket_id, ticket_name, ticket_price)` for the affected events (the rollback file).
* Maintenance window with checkouts paused for the affected events (archive them first) or the run executed under the same row locks as `EventTicketSync`.

## 6. Manual review
A named reviewer (finance + event manager) checks every B item and a random 5% sample of A items against: the Paymob/Fawaterak transaction, the issued ticket numbers, the customer's confirmation e-mail. Disagreements stop the run.

## 7. Execution & rollback
* Approvals: business owner + technical owner, recorded in the change ticket. Staging run first on a Production copy.
* The script only ever does `UPDATE order_items SET event_ticket_id = ? WHERE id = ? AND event_ticket_id IS NULL` for approved ids, in chunks inside transactions, writing every id to an audit table.
* **Rollback:** `UPDATE order_items SET event_ticket_id = NULL WHERE id IN (<ids from the audit table>)` — reversible because the column was NULL before and nothing else is modified. Snapshots, issued tickets, QR and payments are never touched.
* Post-run: re-run `events:audit-tickets`; CONFIRMED must have grown by exactly the approved count, nothing else may change.
