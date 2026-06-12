---
title: "Time export & payroll handover"
topic: exports.payroll
version: 1
audience: []
related:
    - admin.surcharge-rules
    - finance.transfers
    - glossary.core
---

The time export hands approved monthly data over to payroll –
traceable, reproducible and with an audit trail.

Typical workflow:

1. **Month closure**: employees submit their month ("submitted"),
   the team lead approves ("approved"). After the export, the month
   is **locked**.
2. **Create an export**: it moves through "preparing" → "ready" and
   is marked "delivered" or "rejected" after handover.
3. **Choose a profile**: currently **Generic CSV export**, with the
   columns employee, wage type, quantity, unit and period.

Honest note on the DATEV profile: the **DATEV profile** is a
**preparation** (LODAS-oriented). Today only the **generic CSV
export** is available in production – it is deliberately **not a
certified DATEV format**. A real LODAS/Lexware profile will follow
separately.

Wage types in the export: normal hours, night/Sunday/holiday hours
(from the surcharge rules), on-call duty, vacation and sick days,
travel time (if billable).

Important rules:

- Export only works when **all affected month closures** are approved
  or locked – open submissions block it.
- Every export carries a reproducible **SHA-256 hash**; after
  corrections a **new export** is created and the old one is marked
  "superseded" – nothing is silently overwritten.

Permissions: payroll staff or organization administrators create,
deliver and, where necessary, delete exports.
