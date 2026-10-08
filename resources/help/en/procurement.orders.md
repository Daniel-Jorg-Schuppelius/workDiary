---
title: "Procurement & purchase orders"
topic: procurement.orders
version: 3
keywords:
    - purchasing
    - create purchase order
    - PO
    - supplier order
    - goods receipt
    - partial delivery
    - advance shipping notice
    - ASN
    - reorder suggestions
    - reorder point
    - minimum order quantity
    - expected deliveries
audience: []
modules:
    - module.lager
related:
    - inventory.stock
    - articles.master
    - manufacturing.orders
    - contacts.manage
---

Purchase orders record the procurement of articles from a supplier
against a target warehouse. You find them under **Sales & billing** →
**Procurement & catalogues** → **Purchase orders**. **New purchase
order** first creates a draft with **Supplier**, **Warehouse** and
optionally **Delivery date** and **Note**. Use **Add line** to fill the
order lines (**Article**, **Quantity**, optionally **Unit price**), then
place the order with **Place order**. Only articles flagged as
**Purchasable** can be ordered. The status moves through “Draft”,
“Ordered”, “Partially received”, “Received” or “Cancelled”.

**Receive goods** is booked against the individual order line and
increases the warehouse stock at valuation; partial and over-deliveries
are supported, and the **Ordered** and **Received** columns show the
progress. Alternatively, **Add shipping notice** records the announced
quantities for an order, and **Book goods receipt** later takes the goods
receipt over from it. The **Expected receipts** tab opens the “Expected
receipts” view; it lists open order lines of ordered purchase orders,
sorted by delivery date.

The **Reorder suggestions** tab determines, after **Select warehouse**,
the requirement (**Needed**) from the reorder point and open requests and
proposes quantities (**Suggested**) taking the minimum order quantity and
preferred supplier into account. **Create orders** creates drafts per
supplier from them, which you should review before ordering. Creating,
ordering and booking require the **Post stock movements** permission;
**Cancel** on a purchase order is irreversible.
