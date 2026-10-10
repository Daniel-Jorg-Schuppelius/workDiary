---
title: "Manufacturing orders"
topic: manufacturing.orders
version: 2
keywords:
    - production order
    - work order
    - bill of materials
    - BOM
    - recipe
    - material requirements
    - MRP
    - production confirmation
    - scrap
    - subcontracting
    - customs documents
    - proforma invoice
    - commercial invoice
    - delivery note
    - shipping label
    - shipment status
    - cancel shipment
audience: []
modules:
    - module.lager
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
creating, reporting and delivering require the right **Post stock
movements**.

## Shipping on the delivery

With an active shipping connection (see “Shipping connections DHL, UPS and
FedEx”) you create a shipping order including a label on a delivery with a
customer via **Ship**. The delivery then shows the status, for example
**Shipping: Label created**, with the parcel service and tracking number. If
you hover over the status, you see when it was last checked with the parcel
service. Next to the status you find:

- **Download label**: downloads the shipping label again.
- **Check shipment status**: queries the current status from the parcel
  service immediately – no longer available for **Delivered** or
  **Cancelled**. WorkDiary also checks open shipments regularly by itself.
- **Cancel shipment**: only in the status **Draft** or **Label created** and
  after a confirmation prompt. The label becomes invalid. You can then create a
  new shipping order, and the parcels of the delivery can be edited again.

Creating a shipping order, checking the shipment status and cancelling a
shipment require the right **Post stock movements**.

## Customs documents for shipments outside the EU

For every delivery with a recipient, “Customs documents” creates a commercial
invoice (for a sale) or a pro forma invoice (gift, sample, returned goods,
repair and other reasons) as a PDF. The dialog shows whether the destination
is outside the EU and stores the chosen reason for export on the delivery. The
document lists the description of goods, customs tariff number, country of
origin, quantity, net weight and value; gross weight and number of parcels
come from the recorded parcels. Maintain the customs tariff number, country of
origin and net weight on the article, and the sender's EORI number in the
organisation settings. If any of this is missing, the dialog names it and
creates no document. The customs documents do not replace an electronic
export declaration.

The **Delivery notes** page lists all deliveries in the selected period — with
delivery note PDF, sending by email, customs documents and shipping status,
without going through each manufacturing order. The filter “Not shipped” shows
what is still waiting for a label.
