---
title: "Invoices & documents"
topic: invoices.manage
version: 3
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
   entries** can be expanded (1.50 h = 1:30 h).
4. Issue or send — PDF, dispatch and external synchronisation are
   outputs of the same documented state.
5. On late payment use the **dunning run**: level 1 produces a payment
   reminder as its own dunning letter PDF with claims overview,
   optional fee and payment deadline; the e-mail contains the letter
   and the original invoice. No new document is created.

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
