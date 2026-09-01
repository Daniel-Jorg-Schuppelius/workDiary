---
title: "Labels & templates"
topic: inventory.labels
version: 1
audience: []
modules:
    - module.lager
related:
    - inventory.stock
    - warehouses.manage
    - articles.master
---

Here you manage label templates and generate printable labels for
variants, lots and serial numbers.

A template defines paper size (A6, A7, A8), orientation (portrait or
landscape), the optional QR code and the displayed fields. Per
organisation at most one template can be marked as default; if you set a
new one as default, the previous one is reset automatically. Creating and
editing run as a dialog; managing templates requires the configuration
permission.

A label is rendered as a PDF and contains the scannable code (serial
number, lot or SKU) depending on the source. When generating, the default
template is used, or the explicitly selected template; without a template
a lightweight default configuration applies.
