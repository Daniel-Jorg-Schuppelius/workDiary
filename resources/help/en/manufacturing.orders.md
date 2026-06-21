---
title: "Manufacturing orders"
topic: manufacturing.orders
version: 1
audience: []
related:
    - manufacturing.work-centers
    - procurement.orders
    - articles.master
    - inventory.stock
---

Manufacturing orders represent the production of a finished good based
on its bill of materials or recipe. Only articles flagged as
manufacturable can be selected; the system derives the material
requirement from the target quantity, variant and bill of materials. On
release a snapshot of the bill of materials is captured, so later
changes no longer affect the running order.

The flow follows a state machine: draft, released, in progress,
waiting, blocked, completed or cancelled. Material is locked against
stock via "Reserve", starting records execution, and partial reports
capture produced, good, scrap and rework quantities. Finished goods are
booked into stock via "Deliver"; this requires a variant and warehouse
to be set.

From the detail page an order can be assigned to a work center with a
planned occupancy time, or commissioned to a supplier as subcontracting
(which creates a purchase order). The planning view shows the
multi-level material requirements explosion (MRP) for a finished good as
well as quality metrics per article. Cancelling is irreversible;
creating, reporting and delivering require the inventory posting
permission.
