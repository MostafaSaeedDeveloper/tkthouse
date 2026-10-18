# Admin notifications (the bell)

A bell in the admin top bar shows a red counter and the latest 8 notifications; **View all** opens `/dashboard/notifications`
(All / Unread, paginated). The counter refreshes every 45 s while the tab is visible (and when you come back to it).
Clicking an item marks it read and opens its page. Everyone sees only their own.

| Event | Who is told |
|---|---|
| New order | staff with `orders.view` who may see that order and either have `orders.update` or own it |
| Order waiting for approval (new or status change) | same |
| Payment received (order becomes paid) | staff who may see the order (including the agent who owns it) |
| Order assigned to you | the new assignee (not when you assign it to yourself) |
| Refund waiting for approval | staff with `payments.refunds.approve` who may see the order |

With order assignment ON, "may see the order" is the usual assignment scope: an agent is only told about orders they can see.
Customers are never notified here (accounts without a role or permission are skipped).

* Stored in Laravel's `notifications` table (relative links, so APP_URL does not matter). Read ones are pruned after 30 days, all after 90 (daily schedule).
* Triggered from the `Order` observer and the refund model event. Notifying can never break the action that caused it: failures are reported and swallowed.
* Not included yet: e-mail/push copies, per-user preferences (mute types), and notifications for canceled/failed payments.
