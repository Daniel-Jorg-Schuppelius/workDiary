---
title: "Dunning"
topic: finance.dunning
version: 1
audience:
    - admin
    - buchhaltung
related:
    - invoices.manage
    - finance.reconciliation
    - finance.open-times
---

Dunning chases **open invoices** in up to three levels. There are two paths:
the **individual reminder** from the invoice itself, and the **dunning run**,
which picks up all due items at once.

## What a reminder is — and what it is not

A reminder is a **letter, not a document of record**. It creates **no
posting** and no new invoice; it only changes the dunning status of the
existing invoice. This matters for reconciliation: the open amount stays the
same, even after the third level.

**Default interest is stated, not posted.** If a rate above zero is
configured for the organisation, the system calculates it to the day from the
due date (365-day basis) and states the amount in the letter. Whether and
when it is actually claimed is for accounting to decide — which is why no
receivable arises from it.

## Levels and grace period

Grace days, fee and payment term per level come from the organisation
settings. The grace period prevents an invoice from being chased the day
after it falls due while the transfer is still in flight.

## Before you send a reminder

Check the **bank reconciliation**. The most common avoidable reminder goes to
someone who paid long ago — the incoming payment had simply not been matched
yet.
