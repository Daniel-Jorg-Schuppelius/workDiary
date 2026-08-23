---
title: "Local accounting"
topic: accounting.overview
version: 1
audience:
    - admin
    - buchhaltung
related:
    - accounting.posting
    - accounting.closing
---

Local accounting keeps a ledger inside WorkDiary — for organizations without
separate accounting software. It replaces neither the accounting plugins nor
their data authority: for any period either WorkDiary or exactly one external
system leads.

**Three leadership questions that stay separate:**

1. *Invoicing authority* — who issues invoices?
2. *Master data authority* — who leads customers and suppliers?
3. *Posting authority* — who keeps the ledger? Only this axis is new.

**Setup** (Finance → Set up accounting): choose the profile (cash basis or
double entry), base currency, fiscal year and posting start. The preflight
checks whether the organization can post on its own from the effective date;
activation is only possible once no check is red.

Documents before the effective date stay history and are not posted
retroactively.
