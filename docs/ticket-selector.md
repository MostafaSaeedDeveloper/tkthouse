# Event page ticket selector (dynamic tickets)

Every ticket is independent: its name, price, description, maximum per order, couple flag and status come from the database. Nothing is grouped,
renamed or classified from a name (no VIP/Wave/... logic anywhere in PHP, JS or CSS — covered by `TicketSelectorTest`).

* **Layout**: ticket selection is a wide section right after the event header (card grid, one column on phones); *Your order* (list of chosen tickets,
  fees, total, Continue) is a separate sticky card on desktop and a bottom bar on mobile that appears once something is selected. Nothing stretches next to empty space.
* **Cards**: name (clamped to 3 lines, full text in `title` and behind *Read more*), optional short description (2 lines), prominent price, "Max N per order" or
  "Couple pass · 2 tickets", a 46px − / + stepper and a per-ticket subtotal once selected.
* **Order**: available first, then sold-out/unavailable as quiet one-line rows; each group keeps the existing server order (active before sold out, then price). More than 3 sold-out
  rows fold behind "Show all sold-out tickets".
* **Show more**: more than 6 available tickets → the first 6 plus **Show more tickets (N)**; selected tickets are never hidden (CSS `:not(.is-selected)`), the cart lives in JS state,
  so expanding/collapsing never changes it; the count excludes tickets that are already visible because selected.
* **Rules untouched**: couple passes move 0 → 2 only, maximum per order, fees and prices are computed exactly as before; the hand-off is still `/checkout?event=…&cart=…`.
* **Global font size**: unchanged (Bootstrap 3's `html{font-size:10px}` still applies). A site-wide bump was tried and reverted at the owner's request.
* **Verified**: `tests/Feature/TicketSelectorTest.php` plus a Playwright run (148 checks at 360/390/768/1024/1440) on seeded events with 1, 3 and 23 ticket types (long names, couple passes,
  sold out): clamping, couple cap, max per order, Show more with selection kept, fees in the total, no overflow, hand-off to the checkout wizard. Screenshots in `docs/screenshots/ticket-selector/`.
