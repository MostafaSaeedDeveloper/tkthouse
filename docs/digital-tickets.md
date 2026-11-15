# Digital tickets & QR visibility

- Booking e-mails carry **no ticket PDF** and no QR; only the payment invoice PDF stays attached. The button "VIEW YOUR TICKETS" opens the ticket(s) in the customer's account (`/tickets/{uuid}` for one ticket, `/account/tickets?order=…` for several).
- Signed-out visitors (buyers, guest buyers, gifted holders) land on `/tickets/access`: e-mail + 6-digit code, then they are returned to the ticket. A holder with no account gets a passwordless one, only once the code proves the mailbox.
- Per event (Edit Event → *Ticket QR Visibility*): Show Immediately (default), Scheduled Reveal (N minutes/hours/days before start), Manual Control; Show QR / Hide QR works over any mode. Who/when is stored and activity-logged. Logic: `Event::qrIsVisible()`.
- QR content for new tickets is an unguessable token (`TKT-` + 24 letters) stored in `tickets.qr_payload`; old tickets keep the bare ticket number and still scan. QR images are drawn locally (bacon/bacon-qr-code), never by a third party.
- Hidden QR is never sent to the browser. The scanner enforces the same rules server-side (canceled/refunded refused, QR refused while hidden, a scanned bare number is not a credential for new tickets, atomic single check-in).
- Ticket PDFs: none for new tickets; links for old tickets still work (and respect visibility). Admin-issued guest-list tickets keep their PDF.
