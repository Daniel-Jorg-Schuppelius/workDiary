---
title: "Connecting JTL-Wawi"
topic: admin.jtl-wawi
version: 1
audience:
    - admin
modules:
    - module.lager
related:
    - admin.integrations
    - admin.plugins
---

WorkDiary connects JTL-Wawi as the **leading inventory management
system**: articles (parent/child), warehouses and stock come from JTL;
WorkDiary reads them and hands its own stock movements back in a
controlled way.

**Operating modes:** An *OnPremise* Wawi is connected through its
local API instance (create it in the JTL administrator, default port
5883). If the Wawi lives on your own network, you must explicitly
allow private addresses — this approval is audited. The *cloud
gateway* uses client ID/secret and tenant ID from the JTL partner
portal.

**App registration (OnPremise):** First open “Admin > App
registration” in JTL-Wawi, then start the registration here and
approve the app in the Wawi. The API key is issued **only once** and
stored encrypted — it never appears in logs or diagnostics.

**Mappings:** After the first synchronisation, map the JTL warehouses
to WorkDiary warehouses (1:1 for postings). Articles are matched
automatically via SKU and GTIN; unclear cases end up in the
integration inbox where you decide — WorkDiary never creates articles
automatically.

**Inventory leadership:** Under “Inventory leadership” you choose who
leads the stock: *local* (WorkDiary), *external* (JTL leads, WorkDiary
posts back through the outbox) or *read only*. Switching back to
“local” imports the JTL stock as an opening stocktake.

**Beta notice:** The JTL-Wawi API currently runs as a beta/pilot
programme. After the official release it may become edition-dependent
and chargeable; a lapsed licence leads to a visible blocked state,
never to silent mispostings.
