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
