---
title: "Stock levels & scanning"
topic: inventory.stock
version: 1
audience: []
related:
    - warehouses.manage
    - inventory.counts
    - inventory.labels
    - articles.master
---

The stock overview shows, per selected warehouse, the balances of
variants: available, physical and reserved quantity, moving average price
and stock value, and the reorder point. Active reservations and variants
below the reorder point are listed separately.

With posting permission you record manual movements (receipt, issue,
reservation, release) including ownership type, and you can set minimum
and reorder levels per variant and warehouse. Issuing into negative is
only possible if you explicitly allow it.

Lots are managed in the lot list with remaining quantity; there you can
split and merge lots. The scan view resolves a code (serial number, lot,
GTIN or SKU) and posts an action directly (receipt, issue, transfer). All
movements write to the append-only movement journal and cannot be undone;
corrections are made via counter-postings.
