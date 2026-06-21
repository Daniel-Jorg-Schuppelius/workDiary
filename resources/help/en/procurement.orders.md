---
title: "Procurement & purchase orders"
topic: procurement.orders
version: 1
audience: []
related:
    - inventory.stock
    - articles.master
    - manufacturing.orders
    - contacts.manage
---

Purchase orders record the procurement of articles from a supplier
against a target warehouse. An order is first created as a draft,
filled with order lines (article, quantity, optionally purchase price)
and then ordered. Only articles flagged as purchasable can be ordered.
The status moves through draft, ordered, partially received, received
or cancelled.

Goods receipt is booked against the individual order line and increases
the warehouse stock at valuation; partial and over-deliveries are
supported. Alternatively a shipping notice (ASN) with advised
quantities can be recorded for an order and the goods receipt booked
from it later. The "Expected goods receipts" view lists open order
lines of ordered purchase orders, sorted by delivery date.

Automatic order suggestions determine the requirement per warehouse
from reorder point and open requests and propose quantities taking the
minimum order quantity and preferred supplier into account. Applied
suggestions create drafts that should be reviewed before ordering.
Creating, ordering and booking require the inventory posting
permission; cancelling a purchase order is irreversible.
