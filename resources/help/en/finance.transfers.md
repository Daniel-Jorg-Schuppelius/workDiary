---
title: "Invoicing transfer"
topic: finance.transfers
version: 1
audience: []
modules:
    - module.finance
related:
    - exports.payroll
    - admin.surcharge-rules
    - roles.buchhaltung
    - glossary.core
---

The invoicing transfer hands over billable **time** and **materials**
to the leading invoicing system.

Core principle of invoicing sovereignty: **the invoice is created in
the leading external program** (DATEV or Lexoffice) – WorkDiary only
supplies verified positions plus a transfer record. A local invoice
in WorkDiary exists only when no external invoicing software is in
use. Exactly one invoicing path applies per organization/customer.

Typical workflow:

1. **Create a transfer** ("Draft"): choose the channel –
   "Services/Time" or "Products/Material" (separate) – and the
   target: "Lexoffice" (invoice draft via API), "DATEV" (currently as
   a file package) or "File export" (CSV).
2. Review the positions and **confirm** ("Confirmed").
3. **Execute** → status **"Transferred"** (final). On "Failed" a
   retry from "Confirmed" is possible.
4. "Draft"/"Confirmed" can be **voided** – the contained positions
   are released again.

Risks and irreversible actions:

- **"Transferred" is final** – the contained positions are locked
  against changes.
- Corrections run via traceable **cancellation/difference
  transfers**, never via silent resets.

Permissions: time and material transfers are protected separately.
Only staff authorized for the respective billing channel can execute
that transfer.
