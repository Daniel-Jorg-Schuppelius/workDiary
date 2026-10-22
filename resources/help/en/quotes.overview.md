---
title: "Quotes"
topic: quotes.overview
version: 7
keywords:
    - create quote
    - estimate
    - quotation
    - proposal
    - send quote
    - accept quote online
    - partial acceptance
    - validity period
    - order confirmation
    - convert quote to invoice
    - optional items
    - quote revision
    - quote PDF
    - related documents
    - document chain
    - quote number
    - quote to Lexoffice
    - order confirmation number
    - delivery note
    - partial delivery
    - delivery note number
audience: []
modules:
    - module.vertrieb
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

**Invoiced in the accounting system:** If an accepted quote is invoiced in
the accounting system rather than here, mark it via the menu of the quote
page with **Invoiced in the accounting system**, optionally with the invoice
number used there. The quote then leaves the list **Accepted quotes without
invoice**; the mark can be removed again. The action is available as long as
there is no invoice for the quote.

**PDF:** Every quote can be downloaded as a PDF (including option markers
and per-rate tax breakdown); sent quotes permanently keep the document design
of the time of sending. The order confirmation is a separate document (see
below).

## Related documents

At the bottom of the page, **Related documents** lists every document
connected to this one: the quote and its versions, invoices, down payments and
the final invoice, credit notes and cancellations, delivery notes,
manufacturing orders and handovers. If an accounting program has taken over a
document, its counterpart appears there with the program's number. Links the
program reports itself, such as a down payment created in Lexoffice, appear as
well. The **Relation** column says what the document is to this one; "via …"
names the document through which the connection exists.

## Quote number from the accounting program

If you set an accounting program (Lexoffice, easybill or orgaMAX) for **Quote** on the
**Number ranges** page, a new quote first carries a draft number. When it is
**sent**, WorkDiary creates the quote in the program, finalises it there and
takes over its number; only then is the PDF created. If the transfer fails,
the quote stays approved and you can send it again. A new version is a new
quote with its own number in the program. Lexoffice marks optional items as
optional; easybill lists them without calculation, orgaMAX leaves them out.

## Order confirmation

For an accepted quote, **Create order confirmation** creates a separate
document with the accepted items. The order confirmation has its own number
range (**Number ranges**, range “Order confirmation”). **Confirm** gives it its
number; if an accounting program (Lexoffice, easybill or orgaMAX) assigns the number,
WorkDiary transfers it at that moment. After that, the PDF and e-mail sending
are available. Cancelling withdraws it in WorkDiary; it remains in the
program.

## Delivery note

A confirmed order confirmation offers **Create delivery note**. The delivery
note takes over the quantities not yet delivered, without prices. For a
partial delivery, adjust the quantities under **Edit quantities**; 0 removes
an item from this delivery note, it stays open for the next one. **Deliver**
fixes the number from the range “Delivery note”; if an accounting program
(Lexoffice or easybill) assigns the number, WorkDiary transfers the delivery
note at that moment. After that, the PDF and e-mail sending are available.
Delivery notes from manufacturing are created with every delivery (see
manufacturing orders).

## Viewed and signature

When the customer opens the quote via the link or in the customer portal, the quote header shows **“Viewed by the customer on …”**; the first opening of a sent quote counts. On acceptance, the customer can optionally enter their **name** and **sign**. The signature is stored as an image on the quote and recorded with name and time in the frozen acceptance record; accepting without a signature remains possible.
