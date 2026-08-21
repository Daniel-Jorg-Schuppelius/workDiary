---
title: "Customer circulars"
topic: circulars.overview
version: 1
audience: []
related:
    - contacts.manage
    - invoices.manage
---

Circulars are business notices to a filtered set of customers — a price
change, a maintenance window, altered emergency hours. Not a newsletter: no
tracking pixel, no rewritten links.

**Audience:** The set is determined through the existing customer filters
(search, city, postcode prefix, only customers with an active project).
Before sending, the recipient count is shown with the full list — a mail to
every customer must not be triggered by accident.

**Bulk-mail opt-out:** Customers with the *No bulk mail* switch are skipped.
Circulars marked as a *mandatory notice* still reach them; that is reserved
for legally required information.

**Proof:** Every recipient produces a row — including those skipped, with a
reason (a missing e-mail address, for instance). The notice is additionally
filed as a communication note in the customer file and, on request, appears
in the customer portal.

**Placeholders:** `:firma`, `:kunde` and `:ansprechpartner` are replaced per
recipient. If a value is missing the spot stays empty — nothing is invented.
