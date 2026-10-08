---
title: "Product recalls"
topic: recalls.overview
version: 1
keywords:
    - recall campaign
    - start recall
    - defective product
    - serial numbers
    - affected customers
    - notify customers
    - return rate
    - authority notification
    - product safety
    - traceability
    - block stock
    - safety notice
audience: []
modules:
    - module.lager
related:
    - serials.tracking
    - claims.overview
    - customer-portal.overview
---

A recall determines which customers received a faulty product, informs
them and tracks the returns. You find recalls under "Damage & recalls"; each
recall receives a number (RUF-…).

## Narrow down and activate

A recall applies to one article variant. It is narrowed down by production
orders, a delivery period and serial numbers; the given criteria apply
together, empty ones do not restrict. In draft, the record previews the
affected deliveries. Activating creates one item per delivery (per number
for serial numbers); optionally, stock within the scope that has not yet
been delivered is blocked. Cancelling lifts exactly these blocks again.

## Inform customers and returns

"Notify customers" sends every affected customer an email with product
and serial numbers and creates a dispatch record. Customers without a valid
email address stay open and are reported. The customer portal shows a
notice about the recall. For each item you open a claim for the return;
the record shows the count per state and the return rate.

## Authority notification

Enter the reporting details (hazard, risk level, measure, countries,
authority, file number, contact) in the "Authority notification" dialog. The
report form as PDF additionally contains quantities, informed customers
and returns and serves as the template for the report in the authority
portal.
