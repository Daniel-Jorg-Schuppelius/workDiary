---
title: "Stock levels & scanning"
topic: inventory.stock
version: 6
keywords:
    - inventory
    - stock on hand
    - goods receipt
    - stock withdrawal
    - stock transfer
    - reservation
    - reorder point
    - minimum stock
    - block batch
    - batch management
    - FEFO
    - scan barcode
    - stock value
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

## Units, stock journal and settings

**Entering in a unit of the article.** If an article has further units with a conversion factor (for example 1 roll = 100 m), the posting form and the scan view offer a **unit** selection next to the quantity. Postings are always made in the base unit; the quantity entered and its unit stay with the movement. Rounding is commercial to four decimal places. A quantity that amounts to zero in the base unit and a unit not maintained for the article are rejected.

**Stock journal.** The **stock journal** tab lists all movements with time, warehouse, movement type, quantity in the base unit and the column **Entered as** (quantity and unit entered). Search, warehouse and the period at the top narrow the list.

**Shelf life.** If an article carries the property “shelf life required”, the warehouse accepts its receipt only via the goods receipt of a purchase order with lot and best-before date. Manual receipts and scan receipts are rejected. A lot that is already known brings its date with it.

**Inventory settings.** With the right to configure inventory you open the **inventory settings** in the stock overview. There you choose the **valuation method** of the organisation (moving average, FIFO or FEFO) and the **reservation timing** of manufacturing (on release or at start). When switching to FIFO or FEFO, existing stock receives an opening layer at the previous average price. Switching back to the moving average is only possible once no stock remains in layers, because the average was not maintained during that time.

## Rights, reservations and article numbers

**Separate rights.** Posting, counting stock, approving stock count differences, changing reservations and compensating external postings are separate rights. Anyone who was allowed to post keeps counting, reserving and compensating; your administrator can separate the rights per role.

**Changing a reservation.** With the right for reservations, the pencil icon of an active reservation opens a dialog showing the reserved, issued and open quantity. There you set the priority (a lower number means higher priority) and release a partial quantity, for example to assign it to another order.

**Four-eyes approval.** In the inventory settings you can enable four-eyes approval for stock count differences, with a threshold for the stock value of the difference per line. At or above the threshold, the person who counted does not approve. Threshold and approver are recorded in the audit log.

**SKU and GTIN.** The inventory settings show who maintains article number, variant SKU and GTIN. This is chosen on the “Number ranges” page at the range “Article”. If a merchandise management system maintains the numbers, the fields are locked in the article master and arrive with the synchronisation.

**Negative stock with four eyes:** If the organisation requires four-eyes approval for issues into negative stock in the inventory settings, an issue with “Allow negative stock” becomes a request in the approval inbox. It is posted only once another person with the “Approve negative stock” permission agrees; requester and approver are recorded in the audit log.

**Redistributing reservations:** In the “Edit reservation” dialog you hand over open quantity to another reservation of the same article, warehouse and ownership, for example to an order with higher priority. A newer reservation never silently displaces an older one; the redistribution is recorded in the audit log and the material line of a manufacturing order follows it.

**Stock value per method:** Average price and stock value in the overview
follow the article’s valuation method: for the moving average from the average
ledger, for FIFO and FEFO from the open valuation layers (value of the remaining
quantities, average = value ÷ quantity). The ABC classification of cycle counts
uses the same values.
