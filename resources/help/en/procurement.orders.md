---
title: "Procurement & purchase orders"
topic: procurement.orders
version: 6
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

## Credential light

If mandatory credentials are required for the supplier (such as an exemption certificate or insurance), the order header shows their **traffic light**: green if everything is valid, yellow if something expires soon, red if credentials are missing or expired. Clicking it opens the credential overview. Whether red blocks submission is still governed by the credential settings.

## Supplier reply via GAEB

Besides the openTRANS confirmation, **Import supplier reply** also reads GAEB trade files. An **X97** (order confirmation for order X96) stores the confirmed quantity, price and date per line and reports deviations without changing the order. An **X94** (price offer for price inquiry X93) applies the offered unit prices to the order while it is a draft. Lines are matched by the supplier's article number; the file must refer to the order's number. The price inquiry itself (**Price inquiry (GAEB X93)**) and the field for the offer are available in the draft order.

## Order unit and goods receipt

If the article has further units, you choose the **order unit** when adding the line (for example roll instead of metre). Quantity and unit price then apply per order unit, as do the ordered and received quantities of the line. The goods receipt converts to the base unit: the warehouse receives the quantity in the base unit, the cost price is kept per base unit, and the stock journal shows the entry in the order unit. The pack size of a supplier article remains a rounding quantity of the purchase suggestions in the base unit.

Articles with the property “shelf life required” demand a lot and a best-before date at goods receipt; a lot that is already known brings its date with it.

## Credential types and purchase suggestion

Under **Mandatory credentials → Credential types** you maintain the organisation’s own credential types: name, standard validity, advance warning, behaviour if the credential is missing and whether the type is mandatory. With tags, the obligation applies only to suppliers carrying one of them (trade, type of service). The catalogue types are templates and can be adopted as your own type; own types are deactivated instead of deleted.

Maintaining them requires the permission **Manage supplier credential types** (default: admin and management). Editing suppliers alone is not enough, because a credential type can block placing orders with all suppliers of the organisation.

The **purchase suggestion** rounds to the pack size of the supplier article. If the article has a unit with exactly this factor, the suggestion orders in that unit and converts the price per order unit.
