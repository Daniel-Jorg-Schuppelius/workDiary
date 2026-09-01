---
title: "Warehouses"
topic: warehouses.manage
version: 1
audience: []
modules:
    - module.lager
related:
    - inventory.stock
    - inventory.counts
    - inventory.labels
    - articles.master
---

Here you create the warehouses and storage locations that carry stock,
stocktakes and movements. The list shows the warehouses with the number
of associated movements; the default warehouse is listed first.

A warehouse essentially carries a name and an identifier; one can be
marked as default. Creating and editing run as a dialog.

A warehouse can only be deleted while no movements exist on it; otherwise
deletion is blocked. Therefore create warehouses carefully, as they can
no longer be removed once the first postings have been made.
