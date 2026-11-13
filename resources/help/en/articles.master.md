---
title: "Article master data"
topic: articles.master
version: 6
keywords:
    - item master
    - products
    - create item
    - product catalog
    - article number
    - SKU
    - variants
    - EAN
    - GTIN
    - units of measure
    - HS code
    - customs tariff number
audience: []
modules:
    - module.lager
related:
    - articles.lexoffice
    - materials.manage
    - inventory.stock
    - warehouses.manage
---

The article master is the central catalogue of all products, materials
and services of the tenant. It is the foundation for inventory,
procurement, manufacturing and sales.

An article carries master data such as article number, type, status,
base unit, GTIN and default purchase and sale price. On the detail view
you additionally maintain options with option values (e.g. size,
colour), additional units with a conversion factor to the base unit, and
concrete variants. A variant is built from a combination of option
values and is automatically assigned an SKU.

Creating and editing run as a dialog. Articles and variants can be
retired instead of deleted. Deletion is only possible while no dependent
data exists; otherwise it is blocked. Create variants only once options
and option values are complete, because variants are composed from them.

For customs documents, articles carry the customs tariff number (6 to 11
digits; spaces and dots are removed), the country of origin as an ISO code and
the net weight per unit under “Customs and export”.

## Inventory and valuation

Under “Inventory and valuation” you set a separate **valuation method** in the article dialog; without a selection the method of the organisation applies. Switching to FIFO or FEFO creates an opening layer for existing stock; switching back to the moving average is only possible once no stock of the article remains in layers.

With a **serial number prefix** the article counts its own serial numbers (prefix and running number, optionally with a check digit); without a prefix the number range of the organisation applies.

The additional **units** take effect in stock postings, scanning, purchase orders and goods receipts: the quantity can be entered in the unit, and the posting is made in the base unit. The property “shelf life required” demands a lot and a best-before date at goods receipt.

If a merchandise management system maintains the article numbers (“Number ranges” page, range “Article”), article number and GTIN are read-only in the dialog; new articles and variants receive their number with the synchronisation.

**Tax class:** In the article form you record the tax class (standard, reduced, increased). It is stored with the article, taken over on import and compared for duplicates and merges; the tax rate of a document is still set on the line.

**Place of supply:** For services, choose the place-of-supply type (§ 3a UStG)
in the article form: **General**, **Catalogue service** (not taxable for
consumers in a non-EU country) or **Service connected with immovable property**
(tax at the location of the property, for all customers). Empty means the
organisation default. The detail page shows a chosen type; the CSV import takes
it in the column `place_of_supply_kind` (values `general`, `catalog`,
`property`, a header such as “Leistungsort”). How it determines the tax case is
described in the help topic **Invoices & documents**.

## Manufacturing

Under “Manufacturing” you choose the article’s **default work plan**: a
published version of an active procedure template. Manufacturing orders and
manufacturing planning then produce the article with it unless an order selects
its own version; material requirements planning reads it as well. The section
appears as soon as published procedure versions exist.
