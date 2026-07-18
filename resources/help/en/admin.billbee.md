---
title: "Connect Billbee"
topic: admin.billbee
version: 1
audience:
    - admin
related:
    - admin.integrations
    - admin.plugins
---

WorkDiary connects **Billbee** as a multichannel aggregator: orders from
Amazon, eBay, Otto, Kaufland, Shopify and more converge in Billbee and are
imported here as an **order mirror with channel origin**.

**Inbox-first:** Buyers are never created blindly as customers. Unique
matches or already assigned repeat buyers are linked; everything else
appears as a suggestion in the integration inbox and is decided there.

**Credentials:** API key (activated by Billbee support), Billbee username
and the separate API password — encrypted per organisation, maintained via
the plugin card (Administration → Plugins).

**Stock return channel:** If the organisation runs inventory in "external"
mode via Billbee, local movements are transmitted as **absolute stock
updates** per SKU (no drift on retries). This requires a maintained SKU
mapping — products without a local counterpart remain visible as open
assignments.

**Throttle:** Billbee allows 2 requests per second; the sync keeps this
limit automatically.
