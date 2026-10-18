# Payment methods per assigned agent (replaces per-agent merchant accounts)

**Rule:** one merchant account collects every payment. What differs between agents is **which payment methods the customer sees**.

* **Users → Edit → Payment methods**: a multiselect of the active payment methods. Empty = every active method. Stored in `users.allowed_payment_methods`.
* **Settings → Payments → "Show each agent's payment methods"** (`payment_routing_enabled`) is the master switch. Off = every method for everyone (nothing changes). On = a customer paying an order sees only the methods allowed for the agent the order is **currently** assigned to.
* The same list is enforced server side when a payment is opened (a method that is not offered cannot be started), on the payment page of an existing order, and on the checkout payment step (the reservation picks the agent first, so what is shown is what is allowed).
* Reassigning an order changes the methods its customer sees. An order pinned to a merchant account (by "Switch payment account") keeps that account but still follows its agent's methods.
* Unassigned orders show every method (unless the "unassigned order payment policy" blocks online payment).
* Safety net: if none of an agent's selected methods is currently available, the customer sees every available method instead of being unable to pay.
* **Removed:** the per-user "Payment account / Merchant account / Routing enabled" controls. The `users.payment_*` columns remain but are no longer read; the default merchant account (Settings → Payments) is the only account choice besides an explicit per-order switch.

Older design notes (`v2-stage-*`, `v2-payment-sandbox-runbook.md`) that mention an agent's custom account describe the previous behaviour.
