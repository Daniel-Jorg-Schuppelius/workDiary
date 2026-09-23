---
title: "Invoices & documents"
topic: invoices.manage
version: 6
audience: []
modules:
    - module.vertrieb
schema: process
related:
    - contacts.manage
    - projects.manage
    - finance.datev-bookings
    - finance.transfers
    - travel-expenses.manage
---

## Purpose and background

The invoice overview manages local invoices and connected documents.
Which path leads depends on the organisation and the billing
integration in use: per period either WorkDiary issues the invoices
or exactly one external system — never both at once.

## Requirements

- Checked master data: customer, recipient address, tax details.
- **Service period and project link** of the positions to bill.
- The right to create invoices; for dunning runs the corresponding
  finance role.

## Recommended workflow

1. Choose customer and period — the creation dialog shows a
   **preview** of the resulting positions (count, duration in clock
   and decimal format, amount, late-entry warning).
2. Exclude individual time entries by checkbox if needed — they stay
   open and appear in the next run.
3. Check and complete the draft; per position the **source time
   entries** can be expanded (1.50 h = 1:30 h). For an article with
   copper weight, the **copper surcharge** checkbox in the line dialog adds
   the surcharge at the current DEL daily price as a separate line.
4. Issue or send — PDF, dispatch and external synchronisation are
   outputs of the same documented state.
5. On late payment use the **dunning run**: level 1 produces a payment
   reminder as its own dunning letter PDF with claims overview,
   optional fee and payment deadline; the e-mail contains the letter
   and the original invoice. No new document is created.

**E-invoice.** The XRechnung is created in UBL syntax; if a recipient
requires CII, choose the delivery format “XRechnung (XML, CII syntax)” on the
customer or when sending. Peppol always uses UBL. Without a VAT ID — for
example as a small business under § 19 UStG — the tax number in the e-invoice
master data is enough: it is also entered as the seller identifier that the
recipient’s validation requires.

## Practical example

At month's end accounting picks "Müller GmbH" and the previous month:
the preview shows 14 positions and warns about two late time entries.
One disputed entry is excluded and automatically moves to the next
run — the invoice goes out without a debate.

## Common mistakes

- **Silently changing sent or handed-over documents:** issued, booked
  or externally handed-over documents are immutable — mistakes go
  through the cancellation or correction process.
- **Overwriting document numbers or amounts** instead of correcting —
  this destroys traceability.
- **Double invoicing sovereignty:** if an external system runs the
  billing, local invoices deliberately do not exist in parallel.

## Effects and next steps

Issued invoices flow into open items, dunning and the accounting
handover. Next: check payment runs and allocation, and create the
DATEV batch for the tax office.

## Free invoice without times

In the create dialog, **“Compose line items yourself”** stands on equal footing
with taking over times or material usage. The draft only needs the customer
(optionally project, end customer and payment terms) and starts empty: it can
be saved and completed later, but cannot be issued or sent while it has no line
item — this applies to issuing, e-mail, Peppol and the Lexoffice handover
alike. A double click on “Create draft” does not create a second invoice.

**Articles, material and services.** A line item is an article (with an
optional variant), material or free text — such as “assembly, flat rate” or a
custom-made product without a manufacturing order. Description, article
number, unit and price are frozen as document values; later changes in the
article master do not alter the document. A missing price must be entered
deliberately (0.00 is allowed as a free item); an article price in a foreign
currency is never converted silently. Article and free-text line items post
**no stock**; deliveries run via inventory/delivery.

**Invoicing manufacturing deliveries.** With the inventory module active,
“Take over delivery” imports completed deliveries of the customer with a local
invoicing target — each entirely as one line item with a source-bound quantity
and the sales price of the delivery (not the production cost). A delivery can
only sit in one draft at a time; issuing marks it invoiced, removing the line
item, discarding the draft or a full cancellation release it again, and the
origin stays visible on the document. Partial credit notes release nothing;
stock stays untouched by all invoice operations.
