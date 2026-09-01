---
title: "Creating an order"
topic: diary-entries.create
version: 2
audience: []
schema: process
related:
    - protocols.create
    - time-entries.start
    - projects.manage
    - reports.entry-type-analysis
---

## Purpose and background

Order entries are WorkDiary's order book: every maintenance, fault or
assembly job gets an entry with customer, type and status. The entry
anchors protocols, times and later billing — and its status
transitions map the order lifecycle traceably.

## Requirements

- An existing **customer** (required), optionally a **project**.
- Suitable **entry types** (e.g. maintenance, fault, assembly) —
  maintained by the administration.
- The right to create order entries.

## Recommended workflow

1. Open **"New entry"** in the top bar or the quick action on the
   dashboard.
2. Record the **customer** (required) and a **project** if
   applicable.
3. Choose the **entry type** and describe the **content** in one or
   two sentences.
4. Optionally store a **planned duration** in minutes.
5. Status transitions then run through the **detail modal** — no bulk
   update from the list.

![Order book work list with status counters and entries](media/auftraege/arbeitsliste.png)
*The work list: status counters on top, below the orders with status and actions.*

## Practical example

A fault report comes in by phone: the back office creates an entry of
type "fault" with customer and short description in under a minute.
The technician finds the order on their list, starts the clock on it
and attaches the protocol later.

## Common mistakes

- **Expecting bulk status changes:** transitions deliberately run one
  by one through the detail modal — this keeps the audit trail clean
  and prevents mass resets.
- **Using a "miscellaneous" customer:** without a real customer link,
  reporting and billing are missing later.
- **Writing novels:** one or two sentences are enough — details
  belong in the protocol.

## Effects and next steps

With the entry the anchor for everything else exists: book time on
it, create a protocol if needed and drive the status to completion.
The type analysis later shows what the business really spends its
time on.
