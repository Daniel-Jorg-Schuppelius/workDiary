---
title: "Quotes"
topic: quotes.overview
version: 1
audience: []
related:
    - invoices.manage
---

Quotes follow a fixed lifecycle: draft → approval → sending → customer
decision → conversion into an invoice. The list filters by customer and
status (draft, approved, sent, accepted, partially accepted, rejected,
expired).

**Draft:** A quote is created with a customer, an optional project, a
validity period and terms. Items (description, quantity, unit, unit
price, tax rate) can only be added, changed and removed while the quote
is a draft; totals are recalculated automatically. Individual items can
be marked as optional — if the customer declines only those, the quote
still counts as fully accepted.

**Approval & sending:** After approval the quote is marked as sent. This
creates an acceptance link for the customer: it is shown exactly once in
plain text, only a check value is stored — so copy the link immediately
and send it with the quote message (e-mail or letter). From the moment of
sending, the state is immutable; changes are only possible through a new
version that references its predecessor. The whole version chain stays
visible on the quote.

**Customer decision:** Via the link the customer can view the quote
without logging in and accept it, accept it partially (selecting
individual items) or reject it. Alternatively, the editor documents a
decision received by phone or in writing internally, with an optional
reason for rejections. Once the validity period has expired, acceptance
is no longer possible; expired, rejected or sent quotes can be turned
into a new version and offered again.

**Invoice:** Accepted or partially accepted quotes are converted into a
draft invoice with one click. Only the accepted items are carried over;
the invoice then follows the normal invoicing process (review, issue,
delivery). The invoices created from a quote stay linked to it, so the
path from quote to final document remains traceable.

Draft quotes that were never sent can be deleted; everything from the
moment of sending is kept as history.

**PDF & order confirmation:** Every quote can be downloaded as a PDF
(including option markers and per-rate tax breakdown); sent quotes
permanently keep the document design of the time of sending. For
(partially) accepted quotes an order confirmation PDF is also
available, confirming exactly the accepted items.
