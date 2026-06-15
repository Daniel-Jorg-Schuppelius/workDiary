---
title: "Dispatch and conflict warnings"
topic: dispatch.overview
version: 1
audience: []
related:
    - diary-entries.edit
    - planning.shifts
    - assets.fleet
---

Dispatch controls **who handles which order and when** — complementing the
order's functional status machine. Every order carries a **dispatch status**:

- **Unplanned**: neither scheduled nor assigned.
- **Planned**: scheduled or assigned to an employee.
- **Confirmed**: the assignment has been firmly confirmed.
- **En route**: the job is in progress.
- **Done**: the order is complete.

## Conflict warnings before confirmation

Before confirming a date, WorkDiary checks the planned assignment against the
existing working-time and availability rules (overlap with other shifts or
orders, rest period, daily/weekly maximum hours, vacation and absence). There
are two severities:

- **Hard conflicts** block confirmation. They can only be overridden
  deliberately with a **documented reason**; the override is recorded in an
  audit-proof way.
- **Warnings** are advisory and do not block.

## Vehicle reservation

A vehicle can be reserved for a time window on the order. If the vehicle is
already reserved during the requested window, the system prevents a double
booking. Reservations per vehicle can be reviewed and released in the
reservation list.
