---
title: "Dispatch centre: board and map"
topic: dispatch.board
version: 1
audience: []
modules:
    - module.planung
related:
    - dispatch.overview
    - tours.manage
    - sla.overview
---

The **dispatch centre** shows the open and planned orders of a period at a
glance — as a **board** (columns) or as a **map**. It is a pure overview: all
changes are still made on the individual order.

## Board

The board groups the orders of the selected period either:

- **By status**: columns by dispatch status (Unplanned, Planned, Confirmed,
  En route, Done).
- **By employee**: one lane per assigned employee.

Each card shows the customer, time window and employee and flags special
situations:

- **Conflict**: the current assignment has a **hard dispatch conflict**
  (e.g. double booking, shift overlap).
- **SLA**: the customer has a service ticket **at risk** or **breached**
  (SLA risk).

Clicking a card opens the order.

## Map

The map places orders by their own location or — if none is set — by the
**customer location**. Marker colour follows the dispatch status; orders that
are **SLA at-risk or breached** are highlighted in **red**. Filters let you show
**only SLA risks** or **only unconfirmed** orders.

## Deliberately out of scope

The dispatch centre is pure visualisation. **Route optimisation**,
**real-time tracking** and **continuous location surveillance** are not part of
this view for data-protection reasons.
