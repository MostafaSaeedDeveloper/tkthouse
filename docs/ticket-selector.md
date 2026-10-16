# Event page — compact ticket selector + persistent checkout

Every ticket is independent (name, price, description, max per order, couple flag, status come from the database). Nothing is grouped, renamed or
classified from a name; one ticket or thirty work the same way.

* **Header**: "Select tickets" + "N ticket types available".
* **Desktop (≥992px)**: the card stays in the right column and is sticky under the fixed header. The available tickets live in their own scroll area
  (`max-height: min(420px, 52vh)`, ~4–5 cards), the **sold-out accordion** and the **order summary + Continue** sit below it in the same card, so the
  summary never moves down when the number of tickets grows; the card is capped to the viewport (`100vh − header − 32px`), so short screens keep Continue reachable.
* **Show more / Show less** (all screen sizes): the first **5** available tickets are shown; **Show 5 more tickets (N left)** reveals them step by step and **Show less** folds back to 5.
  Selected tickets are never folded away and nothing is re-ordered while the customer interacts.
* **Clear selection**: a link in the order summary (inside the sheet on mobile) that asks "Remove all selected tickets?" (Yes, clear / Keep) before resetting quantities and the total.
* **Mobile**: no nested scrolling.
  Once something is selected a **bottom checkout bar** appears (count + total + Continue, safe-area aware via `viewport-fit=cover`); tapping the total opens the **order sheet**
  (lines, fees, total) with a backdrop, close button and Escape; the page gets bottom padding so the footer is never covered.
* **Cards**: compact grid — name (full text, wraps) + short description, price, "Max N per order"/"Couples only · 2 tickets", 44px steppers. Unselected = white with a light border; selected =
  light-yellow background with a yellow border. Long names never overlap the price or the stepper.
* **Sold out**: not mixed into the list. A closed accordion **Sold out (N)** with tiny rows (name, price, badge); no quantity controls, no `data-id`, nothing can reach the cart.
  If every ticket is sold out the card shows a clear message + disabled "Booking closed" + a link to upcoming events instead of an empty purchase box.
* **Rules untouched**: couples move 0 → 2, maximum per order, fees and prices are computed as before; the cart format and the hand-off (`/checkout?event=…&cart=…`) are unchanged, the server still validates everything.

Verification: `tests/Feature/CompactTicketSelectorTest.php` (markup/contract) plus a Playwright run on seeded events with 1, 3, 5, 15, 18, 30 (+10 sold out) and all-sold-out tickets at 360/390/768/1024/1440
(188 checks: internal scroll ≤420px, summary in view while scrolling and on a 560px-high screen, Show more/less from the keyboard, Clear selection with confirmation, sheet focus handling, step-by-step fold, selection kept when folding, bottom bar/sheet, accordion, totals with fees, couple/max caps, hand-off to the checkout wizard).
Screenshots: `docs/screenshots/ticket-selector/`.

---

## Event Details redesign (two columns, ticket grid, sticky Order Summary)

Files: `resources/views/front/events/show.blade.php`, `public/js/tkt-booking.js`, `public/css/tkt-v2-pages.css`, `tests/Feature/CompactTicketSelectorTest.php`. No controller, checkout, payment, schema or admin file was touched.

* **Layout** (≥ 992px): ~70 % main column (tabs, description, Date/Time/Location, ticket grid) + ~30 % `Order Summary` sidebar. The sidebar is `position: sticky` under the header; its card is never taller than the viewport (`max-height: calc(100vh − header − 32px)`); only the ticket lines scroll inside it, the total and **Continue to Checkout** stay visible.
* **Ticket grid**: 3 columns ≥ 1100px, 2 columns ≥ 640px (also the 1024px case, where the 70 % column is ~650px wide), 1 compact column on phones. Cards grow with their content (no fixed height, names never clipped), selected card = yellow border + light yellow background. Each card: full name, description, price + EGP, "Available", `Max N per order` or the *Couples only · 2 tickets* badge, − / qty / + stepper.
* **Fold**: 6 available tickets by default, `Show More Available Tickets (N left)` reveals 6 more each time, `Show Less` returns; a selected ticket is never hidden and the order never changes. System order is kept.
* **Sold Out Tickets (N)**: one closed accordion with compact rows, no stepper.
* **Order Summary**: empty = "No tickets selected", `0.00 EGP`, disabled Continue. With a selection: each ticket (`qty × unit price`, line total), Subtotal, the event's own fee rows (same percentage/fixed rule as the checkout), Final total, *Continue to Checkout*. Note: "Promo codes and the final amount are confirmed at checkout". No VAT/tax is assumed — only fees that exist on the event.
* **Mobile** (< 992px): the same `#orderSummary` element becomes the bottom bar (count, total, Continue) with an "Order details" sheet (backdrop, focus trap, Escape, safe-area padding). One DOM element and one cart object → no duplicated state.
* **Hand-off unchanged**: `/checkout?event=…&cart=base64url([{ticket_id, qty}])`; the server recomputes everything.

QA (Playwright, local seeded data, Fake Gateway untouched): 1 / 3 / 5 / 15 / 30(40) / 18 ticket events, all-sold-out, long names, couple, max-per-order at 360 / 390 / 768 / 1024 / 1440 and 1366×600: no horizontal scroll, Continue and total always in view after selecting and scrolling, fold/accordion/sheet work, journey Event → select → summary → `/checkout` shows the same total. Screenshots: `docs/screenshots/event-redesign/`.
