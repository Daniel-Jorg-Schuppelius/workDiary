---
title: "Stock levels & scanning"
topic: inventory.stock
version: 2
audience: []
modules:
    - module.lager
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

A lot can be **blocked** and **released** again in the lot list — each with
a reason; both are recorded in the audit log. The stock of a blocked lot
stays in the warehouse, but the pick list no longer suggests it; splitting
and merging is only possible after release. On merging, the stock of the
source lot moves to the target lot via counter-postings (transfer out/in);
the merged lot is closed afterwards and no longer accepts receipts.

**Issuing by lot.** If the warehouse holds lot stock, the posting form
offers the field “Lot (issue)”: left empty, the issue follows FEFO
(earliest best-before date first, as on the pick list); otherwise exactly
the chosen lot is issued. For receipt, reservation and release the field
has no effect. Batch-tracked articles can be issued this way as long as
their lots cover the quantity — stock without a lot is not issued for them;
receipts still go through goods receipt. A transfer by scan takes the lots
along to the target warehouse; a scanned lot code posts exactly that lot.

**Blocking in the stock.** Blocking a lot posts its stock to the state
“blocked”: it stays in the warehouse but no longer counts as available and
appears in the stock overview in the column “Blocked”. A blocked lot accepts
no goods receipt; returns and restocking into it stay blocked. Releasing
posts the blocked stock back. Splitting a lot is a posting as well: the
split quantity moves to the new lot in the movement journal.

**Cleaning up legacy stock.** Until October 2026 issues carried no lot, so
the stock of a lot may exceed what is actually left of it. Your
administrator checks this with the command `inventory:lots:repair`: without
options it is a dry run with a table per lot (ledger balance, remaining
quantity of the valuation layers, difference); with `--apply` it re-posts
the difference to “without lot” under FIFO or FEFO valuation, and the total
stock stays the same. Under moving average it only reports the lot, and
blocked lots only after their release.
