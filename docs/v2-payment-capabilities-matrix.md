# Provider capabilities matrix (from official sources; verified 2026-10-16, no sandbox run yet)

Legend: ✅ documented by the provider · ⚠️ partly documented / needs sandbox confirmation · ❌ not documented → **not implemented as an API call**.

| Capability | Paymob (Egypt, accept.paymob.com) | Fawaterak |
|---|---|---|
| Payment status inquiry | ✅ `GET /api/acceptance/transactions/{id}` (Bearer from `/api/auth/tokens`); `POST /api/ecommerce/orders/transaction_inquiry` by order id / merchant_order_id (latest transaction only) | ⚠️ `getInvoiceData/{id}` already used (field semantics of `data.paid` still unconfirmed) |
| Paid webhook | ✅ "transaction processed callback" (POST, `hmac` query param, HMAC-SHA512 over 20 ordered fields incl. `is_refunded`, `is_voided`, `has_parent_transaction`) + GET response callback | ✅ payload `hashKey, invoice_key, invoice_id, payment_method, invoice_status, pay_load, referenceNumber`; URL must end with `_json`; hash = HMAC-SHA256 of `InvoiceId=…&InvoiceKey=…&PaymentMethod=…` with the **vendor key** (hash covers ids/method, **not** status or amount ⇒ always confirm by inquiry) |
| Failed webhook | ⚠️ same callback with `success=false` | ⚠️ payload `invoice_key, invoice_id, payment_method, amount, paidCurrency, errorMessage, response{gatewayCode,…}, referenceNumber` — **no `hashKey` in the documented sample ⇒ cannot be authenticated ⇒ informational only** |
| Cancel / expired webhook | n/a | ✅ Fawry/Aman/Masary only: `hashKey, referenceId, status=EXPIRED, paymentMethod, transactionId, transactionKey`; hash = HMAC-SHA256 of `referenceId=…&PaymentMethod=…`; mapping of `referenceId`/`transactionId` to our invoice id is **not documented** ⇒ stored, matched only by exact invoice id, never changes money state by itself |
| Refund API (create) | ✅ `POST /api/acceptance/void_refund/refund`, header `Authorization: Token <secret_key>`, JSON `{transaction_id:int, amount_cents:int}`; amount ≤ original; partial allowed; multiple partials until the original amount is returned; all methods except Kiosk (source: Paymob "Refund & Void & Capture APIs" Postman collection, Paymob dashboard docs). ⚠️ Response body/synchronicity not documented in the sources reachable | ❌ **No refund-creation API found in the official reference.** Only a refund *webhook* exists |
| Void API | ✅ `POST /api/acceptance/void_refund/void` `{transaction_id}`; **card only, before settlement** (same business day) | ❌ |
| Refund status inquiry | ⚠️ inspect original transaction (`is_refunded` is documented as part of the transaction/callback; a refunded-amount field is **not** documented in reachable sources) | ❌ |
| Refund webhook | ⚠️ refund appears as a transaction callback (`is_refunded`, `has_parent_transaction` are signed fields); exact refund-transaction shape not documented | ⚠️ payload `transactionId, amount(string), currency, status("approved"), reason, approvedAt`; **no hash documented**; "approved" is provider approval, not proof of money returned; meaning of `transactionId` not documented |
| Partial refund | ✅ | ❌ (no API) |
| Idempotency key on refund | ❌ not documented ⇒ we never auto-retry a refund after a timeout | n/a |
| Sandbox | ✅ Test Mode toggle in dashboard (secret key differs per mode) | ⚠️ not verified |

## What the system does with this
* **Paymob**: real API refund + void buttons (permission-gated, off until the account is marked "refund-verified" by an operator after the sandbox runbook). Success is accepted only on positive evidence (signed callback or inquiry), else stays *Processing / Unknown – needs review*.
* **Fawaterak**: **Manual / External refund workflow only** (record who refunded, when, amount, provider reference); incoming refund webhook is stored as an *unauthenticated hint* and flags the manual refund for confirmation — never auto-confirms money.
