---
title: "License stock"
topic: finance.license-stock
version: 1
keywords:
    - license management
    - license keys
    - serial number
    - activation key
    - software licenses
    - product key
    - sell license
    - license pack
    - resell licenses
    - reorder level
    - import keys
audience: []
modules:
    - module.reselling
related:
    - finance.resale
---

The **license stock** manages licenses that you buy in packages and resell
individually — for example ten VPN licenses, each consisting of several keys
that belong together. Licenses are counted, never keys.

## Product and package

1. **Add a license product:** name, manufacturer and the keys per license,
   e.g. “Serial number” and “Activation key”. The **reorder level** decides
   when “Reorder” appears: empty = never, 0 = only when sold out.
   Optionally link an article from the article catalogue (article master or
   connected accounting system).
2. **Add a license package:** package reference, purchase date, supplier,
   quantity and optionally the purchase document. As many numbered licenses
   as purchased are created, initially without keys. Repeat purchases are new
   packages.
3. **Enter keys:** individually via “Maintain keys” or as a CSV with one line
   per license (template in the package). The preview shows lines and errors;
   only an error-free file is applied, and only after your confirmation.

## Status of a license

- **Incomplete:** at least one key is missing — not sellable.
- **Available:** all keys present, not sold, not blocked.
- **Sold:** assigned to exactly one customer, with the complete key set.
- **Blocked:** taken out of sale with a reason.

Purchased is always the sum of available, sold, incomplete and blocked. The
stock is current and does not depend on the period in the header.

## Selling and correcting

- **Sell license** suggests the oldest available license. The sale is
  documented but does not create an invoice; an invoice number is only a
  reference. With an end customer as holder, the customer remains the
  invoice recipient.
- **Correct sale** assigns the same license seamlessly to another customer;
  both remain in the history.
- **Take back sale** blocks the license, because its key may already have
  been used. It can only be released with a reason and your confirmation.

## Protecting keys

Keys are stored encrypted. Lists, the customer record and forms never show
them; the plain text only appears via “Show keys” with the separate right
“show license keys in plain text”, which is not granted to any role
automatically. Every access is logged — without the value.
