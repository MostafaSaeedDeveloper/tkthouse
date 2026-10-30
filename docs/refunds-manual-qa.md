# Refunds — manual QA checklist (admin dashboard)

> **Part A is SIMULATED**: use a staging copy or test orders (fake payment method), a test mailbox and no real Fawaterak money.
> **Part B is a REAL financial refund**: do it once, on one small real order, only after Part A passed.
> Never run Part B on a production order "to see what happens".

Prerequisites: staff accounts — an **agent** (refund create + view, assigned to the test order), a **second agent**, and an **admin** (approve permission, `orders.view_all`). A scanner account. A mailbox you control as the order's customer e-mail.
Settings: *Settings → Payments → Payment Routing* → "Require a second person to approve refunds" (ON = four-eyes, OFF = one-person workflow). Optional env `FAWATERAK_DASHBOARD_URL` (https address of your Fawaterak merchant dashboard) for the "Reopen Fawaterak" link.

## Part A — simulated testing

### A1. One-person workflow, FULL refund (approval OFF)
1. Settings → Payments → Payment Routing: untick the approval setting → Save. (It is written to the settings audit log.)
2. Open **Orders → a paid test order** assigned to the agent (4 tickets, e.g. 100 EGP each). Log in as that agent.
3. In the **page header** (next to Edit / Change Status) click **Refund**.
4. Choose **Full refund**, enter a short factual **reason** (it is shown to scanners and in the customer e-mail — no provider names/links), tick the confirmation → **Record refund request**.
   - Expected: you land on the refund page; status **Processing externally**; no approval step; **no e-mail** sent; order still **Paid**; tickets still valid.
5. On the refund page click **Open Transaction in Fawaterak**. A tracking window opens (and your dashboard tab only if `FAWATERAK_DASHBOARD_URL` is set).
   - Expected: shows order, refund reference, original invoice ID, amount, "Full refund", tickets, reason, status "Processing externally…", "Last verification: Never — … a person verifies it". Opening it changes nothing. Click **Copy invoice / transaction ID**, **Check status**, **Close** and reopen: nothing lost, no second refund created.
6. Simulate the Fawaterak refund webhook (staging only), see "How to verify the refund webhook" below.
   - Expected: while the window is open it changes (≤ 5 s) to **Refund notification received — needs verification (not completed yet)**. Refund status still Processing, order still Paid, tickets valid, **no e-mail**, dashboard unchanged. The agent gets a bell notification "verify and confirm".
7. Under **Actions → Confirm completed**: enter a reference (e.g. `TEST-REF-1`), the actual date, an evidence note → **Record completed** → confirm.
   - Expected: status **Refund confirmed**; order **Refunded**; all 4 tickets **Canceled**; one scan-log row per ticket (`refund_canceled`); refund activity timeline complete.
8. Scanner (log in as scanner, **Ticket scanner**, scan/type a ticket number): **REFUNDED / CANCELED**, "Refund confirmed — <reason>", cancel date, no Check-in button, no amounts. Try the check-in URL manually: refused.
9. Customer mailbox: **exactly one** e-mail "Your Refund Has Been Processed Successfully — TKT House" (name, order, event, amount EGP, Full refund, REF-… reference, date, "being returned through the original payment method … depending on your bank", tickets canceled). **No** Fawaterak/Paymob/gateway words, no fixed timeframe. Nothing earlier.
10. **Dashboard** (as the agent, then as admin, range covering today): *Total Refunded* = the refund amount, *Refunded Orders* = 1; *Total sales* unchanged; Revenue summary "Net collected" = sales − refunded. Click each card → Orders list shows only the refunded order(s) (agent sees only theirs).
11. Repeat: press **Record completed** again / resend the webhook → error "already succeeded"; no second e-mail, no second ticket cancellation, same dashboard figures.

### A2. PARTIAL refund
1. New paid test order, 4 tickets × 100 EGP. Header button → **Partial refund** → tick ONE ticket (the amount fills in as 100.00) → reason → submit.
2. Open Fawaterak window, webhook with amount **100**, confirm as in A1.
   - Expected: order **Partially refunded**; only the selected ticket **Canceled**; the other 3 still scan and check in; e-mail says "Partial refund … remaining tickets stay valid".
3. Cumulative limit: request a second partial for 2 other tickets (200) → confirm. Try a third request for 150 or for an already refunded ticket → **refused** ("exceeds…/already refunded"). Only one refund can be open per payment at a time. After the last ticket is refunded, order → **Refunded**; dashboard counts it as **one** refunded order.
4. Money-only refund: choose partial, tick **Refund money only**, amount 20 → confirm: tickets stay valid, e-mail says no tickets were canceled.

### A3. Approval ON (four-eyes) and old pending requests
1. Turn the setting ON. Agent requests a refund → status **Awaiting approval**; agent cannot approve it ("different person"); the **webhook before approval is not matched** (needs review).
2. Admin (different user): refund page → **Approve** → status approved/waiting external → continue as A1.
3. Create a request while ON, then turn the setting OFF: it **stays waiting** (nothing is approved silently). On its page use **Release for external processing** (note required) or **Withdraw request**. Release only makes it "processing externally"; it is still not completed.

### A4. Isolation
1. Agent A opens an order of Agent B (type the URL): **404**. Refund URL, tracking URL (`…/refunds/{id}/tracking`), Refunds list, search, dashboard figures: nothing of B is visible.
2. Reassign an order A → B (Orders list → assign): A loses it at once, B gets it with its refund and figures; the refund keeps its original requester and the payment keeps its original merchant account.
3. An employee without order access sees no refund data; an agent without refund permissions sees no refund button/cards.

### A5. E-mail failure
Temporarily break mail settings on staging → confirm a refund: the refund still succeeds (tickets canceled, order updated); the refund page shows "Customer e-mails: Refund completed: FAILED" with **Resend**. Fix mail → **Resend** → delivered once; pressing Resend again does nothing.

### A6. Customer side
Customer logged in: for a partly refunded order the remaining tickets are still listed and downloadable; the refunded ticket's PDF returns "canceled after a refund" (410).

## How to verify the refund webhook
- Webhook URL configured in Fawaterak: `POST https://<your-domain>/payments/fawaterak/webhook_json`.
- Body documented by Fawaterak: `transactionId` (the original invoice id), `amount`, `currency`, `status: "approved"`, `reason`, `approvedAt`. It is **unsigned**, so it is only ever a **hint**.
- Simulate on staging: `curl -X POST https://<staging>/payments/fawaterak/webhook_json -H 'Content-Type: application/json' -d '{"transactionId":"<invoice id shown in the window>","amount":"100","currency":"EGP","status":"approved","reason":"x","approvedAt":"2026-10-30 12:00:00"}'`
- Check: **Settings → Payments → Webhooks** (admin) shows the event as *processed* (matched) or *needs review* (amount/currency/transaction mismatch, ambiguous, refund not approved yet, unknown id) — never "succeeded". The refund page timeline gets "provider notification received".

## How to check the original transaction in Fawaterak
1. In the tracking window copy the **Original Fawaterak invoice ID** (and reference number if shown).
2. Open your Fawaterak merchant dashboard (Fawaterak documents no direct link; use your normal login) and search that invoice/transaction ID.
3. Check amount, currency and the refund state there. A legacy order without a stored id: find it in Fawaterak, paste it into **Record original id** (it is audited).

## If the webhook does not arrive
Normal — Fawaterak is not guaranteed to send it and it proves nothing anyway. Verify the refund in the Fawaterak dashboard and confirm manually (A1 step 7). Do not wait for the webhook to complete a refund.

## If the refund succeeded in Fawaterak but TKT House still shows "Processing"
1. Verify in the Fawaterak dashboard that the refund really completed (amount, currency, reference).
2. Refund page → **Confirm completed** with the real reference, date and evidence note. This is the only way a refund becomes completed; it cancels the tickets, updates the order, dashboard and e-mails the customer.
3. **Do not create a second refund** for the same money (the page blocks a second one while this one is open and the balance includes it).

## Part B — REAL refund (production) — read every line
- ⚠ **Never repeat a financial refund if you are not sure what happened.** If the outcome is uncertain (timeout, error, page closed, no confirmation in Fawaterak): **stop**, look at the refund in the Fawaterak dashboard first, and check the TKT House refund page timeline. Only if Fawaterak shows no refund AND the original is still refundable create/continue once. A second real refund pays the customer twice and cannot be taken back from TKT House.
- TKT House never sends money; it only records. The real refund is done by you in the Fawaterak dashboard.
1. Pick one small real paid order (modern, with a stored invoice ID). Confirm the customer e-mail is correct.
2. Create the refund in TKT House (header button). Check amount, type and tickets in the tracking window.
3. In Fawaterak (copy the invoice ID) execute the refund **once**. Write down the Fawaterak refund reference.
4. Wait for/ignore the webhook; verify the refund state in the Fawaterak dashboard.
5. In TKT House confirm with that reference, date and evidence. Check: order status, ticket statuses, scanner, dashboard, the single customer e-mail (to a customer you warned).
6. If anything is unclear at any step: do NOT retry; ask a second person to read the timeline and the Fawaterak dashboard.

## Sign-off
- [ ] A1 full · [ ] A2 partial/cumulative/money-only · [ ] A3 approval ON/OFF/pending · [ ] A4 isolation · [ ] A5 mail failure · [ ] A6 customer · [ ] webhook · [ ] one real refund (Part B)
