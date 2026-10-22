---
title: "Self-billing"
topic: invoices.self-billing
version: 1
keywords:
    - self-billing
    - self-billed invoice
    - credit note to supplier
    - subcontractor settlement
    - type code 389
audience:
    - admin
    - geschaeftsfuehrung
    - buchhaltung
modules:
    - module.vertrieb
related:
    - invoices.manage
    - finance.incoming-invoices
---
With **self-billing**, your organisation, as the recipient of a service, issues the invoice for the supplier’s service itself (section 14(2) sentence 2 of the German VAT Act) — for example for subcontractors, commissions or purchases. The document is called a **self-billed credit note (Gutschrift)** and is kept separate from a commercial credit note for one of your own invoices.

## Prerequisites

- The supplier has the date of the **self-billing agreement** recorded (edit supplier). Without an agreement, the supplier is not offered.
- The supplier has a **VAT ID** or **tax number** and a complete address.
- The credit note has at least one line and a **service period**.

If anything is missing, the detail page lists the open points; **Issue** appears only once everything is in place.

## Procedure

1. **Create credit note**: choose the supplier and the service period.
2. **Add line**: description, quantity, unit, unit price and tax rate. Totals are calculated as for invoices and quotes.
3. **Issue**: assigns the number from its own number range (default `GS-year-number`, changeable under number ranges) and freezes totals and content.

Issuing creates the e-invoice (XRechnung, type code 389) and places it in **incoming invoices**, already assigned to the supplier. From there it goes to accounting like any incoming invoice — to DATEV or to the connected program. The PDF carries the label “Gutschrift”, names the supplier as the service provider and refers to the agreement.

## Correction and cancellation

An issued credit note cannot be changed. **Cancel** is possible only while accounting has not yet taken it over; the related incoming record is then rejected. Afterwards, correct it with a new credit note with negative amounts.

Permissions: viewing with “List invoices”, creating with “Create invoice”, issuing and cancelling with “Issue invoice”.
