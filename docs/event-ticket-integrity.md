# Event ticket data integrity (ticket-type ids on event update) + favicon

## Root cause
`EventController::update()` called `syncTickets(..., replace: true)`, which ran `$event->tickets()->delete()` and then re-created every ticket type from the form. Consequences:
* Every save of an event gave **all its ticket types new ids** (the old rows were deleted).
* A request that arrived **without** the `tickets` array (partial request, failed page load) deleted **all** ticket types of the event.
* Sales were never linked to ticket types by id at all (see below), so nothing stopped any of this and nothing recorded it.

## What the real schema looks like (reviewed, not assumed)
| Table / column | Relation to a ticket type |
|---|---|
| `event_tickets` | the ticket types (id, event_id, name, price, status…). No stock/capacity column exists; `max_per_order` only limits one cart. |
| `order_items.ticket_name`, `ticket_price`, `line_total`, `event_id` | **text/price snapshot** "Event - Ticket" written at checkout. This is the historical record. |
| `order_items.ticket_id` | legacy FK to the *issued-tickets* table `tickets` (not to `event_tickets`); always NULL for new orders. |
| `issued_tickets`, `tickets` (QR payload, check-in, `checked_in_at`) | snapshot copies (name, price, ticket number, holder) keyed by `order_item_id`; **no ticket-type id**. |
| `scan_logs.ticket_id` | FK to `tickets` (issued), not to the ticket type. |
| Refunds / payment attempts | tied to orders / attempts, not ticket types. |
| Promo codes | no event or ticket column in the schema → not linked to ticket types. |
| Reports | group by parsing `order_items.ticket_name`. |
| Checkout cart/session | stores the **ticket-type id** (`CheckoutSelection`), so a recreate also broke in-flight carts. |
| `PaymentService` (pending payment settlement) | looked the ticket type up **by event + name text**, so a rename made a pending order "ticket unavailable". |

So the only live dependencies on a ticket-type *id* were the checkout cart and the form itself; everything historical is a snapshot. That is why history survived a recreate — but only by accident, and "was this ticket sold?" could only be answered by text matching.

## The fix
`App\Services\Events\EventTicketSync` (used by event create and update), all inside one DB transaction with the event's ticket rows locked (`FOR UPDATE`):
* **Row with an id** (must belong to *this* event, else a validation error and the whole transaction rolls back) → the same record is updated. Rename / price / status / colour / limits / order never change the id.
* **Row without an id** → a new ticket type (new id only here). On *create*, submitted ids are ignored.
* **Removal is explicit.** The form adds `removed_ticket_ids[]` only when the user clicks Remove on an existing ticket type. A ticket type that is merely absent from the request is left alone (partial request, failed page load, no `tickets` key at all).
* **Removal with sales evidence never deletes**: the ticket type is set to `hidden` (not shown, not purchasable; record, orders, issued tickets, QR and check-ins untouched) and the admin sees: *"These ticket types already have sales, so they were hidden instead of deleted…"*. Without any sales evidence it is deleted. "Sales evidence" = any `order_items` row (including those of soft-deleted orders) with `event_ticket_id` = the id **or** (older rows) an exact `Event - Ticket` / `Ticket` text match inside the same event.
* **Names stay unique per event** (case-insensitive) for new/renamed tickets, because legacy matching and reports rely on names. Untouched legacy duplicates are not blocked.
* **Order** is persisted in the new `event_tickets.sort_order` (set from the form position; up/down buttons added to ticket rows). Ties keep the old order (by id).
* **Historical prices**: nothing reads `event_tickets` to recompute old orders. `order_items.ticket_price/line_total/ticket_name` and `issued_tickets`/`tickets` price/name stay as sold (tested: 500 stays 500 after a change to 700).
* **Concurrency**: order placement now locks the ticket rows it sells (`OrderPlacementService → CheckoutSelection::revalidate(lockTickets: true)`), the sync locks them too. Either the sale commits first (the sync then sees it and hides instead of deleting) or the sync commits first (the placement then fails with "no longer available"). Verified with real parallel processes on MariaDB.
* **New sales are linked by id**: `order_items.event_ticket_id` (new nullable indexed column, **no foreign key** — a cascade/set-null rule could only lose information). `PaymentService` prefers the id and falls back to text for older rows, so renaming a ticket no longer breaks a pending payment.
* Before a ticket type is **renamed**, older order items whose text matches the *current* name exactly (same event) are stamped with its id, so the rename cannot detach them. This is an exact, same-event match performed on an explicit admin action, not guesswork; nothing else is re-linked.
* Not changed: fees and lineup rows are still replaced on save (nothing references them by id). Event **deletion** is still a hard delete that cascades to ticket types and sets `order_items.event_id` to NULL — see "Recommendations".

## Existing data
* Migration `2026_12_06_000001_ticket_type_integrity`: additive only (`order_items.event_ticket_id` nullable + index, `event_tickets.sort_order` default 0). Old rows stay NULL / 0. **No existing row is modified or re-linked.**
* Read-only diagnostic: `php artisan events:audit-tickets [--limit=15]` reports: how many sales are linked by id vs text only; events saved before the fix (from the activity log — each such save recreated all ticket types); non-contiguous ticket-type ids (a hint, not proof); duplicate names inside an event; **sales lines whose ticket type can no longer be found**; order items without event; `order_items.ticket_id` / issued tickets / QR records / scan logs pointing at missing rows. It writes nothing.
* Result on the development database used for QA (test data, **not Production**): no broken relations, no orphaned sales, no earlier edit recorded. **Production has not been audited or touched**; run the command on a Production copy first and review the findings by hand. Do not auto-repair or re-link by guess.

## Recommendations (not done, need your decision)
1. Event deletion with sales: today `Event::delete()` cascades the ticket types and nulls `order_items.event_id`; consider blocking deletion once an event has orders (deactivate instead).
2. Legacy order items could be linked by id later through a reviewed script driven by the audit output.
3. A real inventory/capacity feature does not exist; if it is introduced, enforce "capacity ≥ sold + reserved" inside `EventTicketSync` under the same lock.

## Favicon
* **Cause:** `public/images/favicon.png` never existed (404 on every Admin, Auth and Scanner page; the Frontend fell back to the same missing file) and `public/favicon.ico` was a **0-byte file**.
* **Fix:** one icon, `public/favicon.ico` (real multi-size ICO 16–256 px) derived from the existing TKT House wordmark (`public/images/logo-light.png`, "tkt" in brand yellow on the Admin dark `#0b0b0b`). One partial, `resources/views/partials/favicon.blade.php`, is included by the Frontend layouts, Admin master, Login and both Scanner pages; a custom icon set in Settings → Branding (`site_favicon`) still takes precedence. No other icon paths remain (a test fails if a view references `images/favicon.png`).
* Note: `public/images/logo-dark.png` is a leftover template image ("Music Forest") used by the legacy frontend header; unrelated to this change and not touched.

## Tests
* `EventTicketIntegrityTest` (15): ids kept; rename; price + historical price; add; explicit/implicit removal; request without tickets; sold ticket hidden + message; reorder; issued tickets/QR/check-in; foreign ticket id (update + removal); full rollback; duplicate names/ids; disabling a sold ticket; new sales carry the id and survive rename/price change; ids ignored on create; read-only audit.
* `ConcurrencyMysqlTest`: removal racing real checkouts never orphans a sale (parallel processes).
* `FaviconTest`: valid ICO, one icon link on Frontend/Login/Admin, no reference to the missing PNG.
* Browser journey (Chromium, Fake gateway, dev data): create event + 3 tickets → customer pays 500 EGP for GA → check-in → edit (rename GA, 500→700, reorder, remove unsold, add) → ids kept (rename = same id), unsold removed, new id only for the new ticket, order/QR/check-in unchanged, order page still shows 500 and the original name → remove the sold ticket → hidden + warning, storefront no longer lists it. Screenshots: `docs/screenshots/event-ticket-integrity/`.
