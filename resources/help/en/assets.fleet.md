---
title: "Assets & fleet"
topic: assets.fleet
version: 1
audience: []
related:
    - documents.manage
    - travel-expenses.manage
    - reports.overview
---

Assets and vehicles represent operational objects with status,
responsibility, documents and maintenance information. Fuel and
charging logs add consumption history.

Maintain master data and unique identifiers, assign locations or
responsible people, and record maintenance intervals and relevant
documents. Status changes should reflect the real lifecycle.

Before deletion or retirement, check for linked maintenance, work
items, journeys or documents. Preserve critical history through
archiving instead of overwriting it.

## Check-out and return

The "Check-out / return" panel on the asset detail page hands a device
to a person or team, optionally with a work-order reference and an
expected return date. An asset has at most one open assignment; an asset
that is already checked out or blocked by a defect cannot be checked out
again. Returning it makes the asset available again. If an assignment
passes its expected return, an overdue hint appears and the deadline
scanner notifies the borrower or the team lead.

## Defects and blocks

The "Defects / blocks" panel records faults with a severity. When "block
asset" is set, the open defect blocks any further check-out until it is
resolved or written off. Resolving or writing off a defect requires a
resolution note.

## Object file (lifecycle)

The "object file" consolidates an asset's entire lifecycle into one
coherent, printable view: master data, location and room, the derived
lifecycle status (in operation, replaced or decommissioned),
commissioning, decommissioning and warranty. Below it lists maintenance,
check-outs and returns, defects and blocks, linked orders, protocols,
material usage, open issues and attachments, plus the full lifecycle
history.

Open it via the "Object file" button on the asset detail page; use the
browser print function to produce a document (appending "?print=1" opens
the print dialog directly). The lifecycle status is derived from status,
decommissioning and warranty – there is no separate field to maintain.
