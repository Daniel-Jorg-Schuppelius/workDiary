---
title: "Stocktake"
topic: inventory.counts
version: 1
audience: []
modules:
    - module.lager
related:
    - inventory.stock
    - warehouses.manage
    - articles.master
---

On this page you carry out stocktakes per warehouse. The list shows the
counts within the selected period. You can open a full stocktake or start
a cyclic partial count over an ABC class, which covers only the variants
that are due.

In the detail view you record the counted quantities per line, optionally
also by scan. While a count is open, results can still be added and
changed.

Posting the differences creates correction movements in the stock and
closes the count. This action requires a separate approval permission and
cannot be undone; therefore check the counted values before you apply the
differences.
