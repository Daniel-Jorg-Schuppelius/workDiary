---
title: "Production capacity (work centers)"
topic: manufacturing.work-centers
version: 1
audience: []
modules:
    - module.lager
related:
    - manufacturing.orders
    - inventory.stock
    - articles.master
---

Work centers represent the production stations where orders are
processed. For each work center a name, an optional code, the available
daily capacity in minutes and a flat setup time are stored. This master
data forms the basis for capacity and occupancy planning in
manufacturing.

The capacity board shows the planned load per work center for the
period selected in the header – that is, the assigned minutes including
setup time against the configured daily capacity. Individual orders are
assigned to a work center from the respective order detail page.

Viewing is possible with the manufacturing permission; creating new
work centers requires the inventory posting permission. The capacity
figures are purely planning values and do not post any stock.
