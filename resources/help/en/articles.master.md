---
title: "Article master data"
topic: articles.master
version: 1
audience: []
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
