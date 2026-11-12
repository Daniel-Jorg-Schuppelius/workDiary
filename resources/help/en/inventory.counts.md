---
title: "Stocktake"
topic: inventory.counts
version: 4
keywords:
    - inventory count
    - physical inventory
    - stock count
    - cycle count
    - annual inventory
    - count sheet
    - count stock
    - inventory differences
    - post differences
    - ABC class
    - scan count
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

## Counting in units and approval

If an article has further units, the counted quantity can be entered in one of these units per line in the count list, when scanning and in mobile counting; it is stored in the base unit. Counting and approving are separate rights. If four-eyes approval is enabled, differences at or above the threshold are not approved by the person who counted them.

**Counting offline with a unit:** Even without a connection, the mobile view counts in the selected unit (for example carton). When the count is sent, the server converts it into the base unit, exactly as online.

## Finish, reopen or discard counting

**Finish counting** freezes the counted quantities for review; nothing more can be entered afterwards. Whoever approves differences can **reopen counting** for corrections, post the differences or **discard the stocktake** – a discarded stocktake posts nothing. The review step is optional; approval is also possible directly from counting.
