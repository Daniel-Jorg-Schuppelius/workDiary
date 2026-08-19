---
title: "Accounting software migration"
topic: admin.accounting-migration
version: 1
audience:
    - admin
related:
    - admin.plugins
    - customers.billing
---

The accounting migration moves an organization from one accounting system
to the next in a controlled way (first supported path: Lexoffice → orgaMAX).
WorkDiary does not blindly copy from system to system — it maps both foreign
systems onto the same local business objects.

Steps: plan (data areas + cutover date, one migration per organization at a
time) → analysis as a dry run (writes to no foreign system) → decide
ambiguous records individually → parallel operation (legacy documents are
settled in the source system) → cutover (from the cutover date new billing
documents are created exclusively in the target system; the source push is
technically blocked, and the cutover stays blocked while conflicts or
unclear write outcomes remain) → completion with a CSV report.

Principles: finalized documents are **never** rebuilt in the target system —
they stay findable as history with number, status and origin. Every step is
recorded in a tamper-evident event chain.
