---
title: "Invoices & vouchers"
topic: invoices.manage
version: 2
audience: []
related:
    - contacts.manage
    - projects.manage
    - finance.transfers
    - travel-expenses.manage
---

The invoice overview manages local invoices and connected vouchers. The
authoritative workflow depends on the organization and its billing
integration.

Before creation, review customer, service period, project reference,
lines, tax data and recipient address. Drafts can be completed; sent,
posted or externally transferred documents must not be changed silently.

PDF, sending and external synchronization are outputs of the same
documented state. For errors, use the intended cancellation or
correction process instead of overwriting document numbers or amounts.

Since MVP-462 the create dialog shows a **preview** of the resulting
items (count, duration in clock and decimal format, amount, late-entry
warning) as soon as customer and period are selected. Individual time
entries can be **excluded** from the run via checkbox — they stay open
and reappear in the next run. On the invoice, the **source time
entries** of each item can be expanded; hour quantities are also shown
in clock format (e.g. 1.50 h = 1:30 h).

**Dunning letter:** Dunning creates a dedicated dunning-letter PDF
(level 1 = payment reminder) with a claim overview, optional dunning
fee and payment deadline; the email carries the letter and the original
invoice as attachments. No new accounting document is created.
