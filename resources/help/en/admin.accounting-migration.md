---
title: "Accounting software migration"
topic: admin.accounting-migration
version: 3
keywords:
    - switch accounting software
    - migrate accounting
    - change provider
    - Lexoffice
    - orgaMAX
    - sevDesk
    - easybill
    - Lexoffice to orgaMAX
    - data migration
    - dry run
    - test run
    - cutover date
    - parallel operation
audience:
    - admin
related:
    - admin.plugins
    - customers.billing
---

The accounting migration moves an organization from one accounting system
to the next in a controlled way. Source and target can be chosen freely
among Lexoffice, orgaMAX, sevDesk and easybill, in both directions.
WorkDiary does not blindly copy from system to system — it maps both foreign
systems onto the same local business objects.

Steps: plan (data areas + cutover date, one migration per organization at a
time) → analysis as a dry run (writes to no foreign system) → decide
ambiguous records individually → parallel operation (legacy documents are
settled in the source system) → cutover (from the cutover date new billing
documents are created exclusively in the target system; the source push is
technically blocked, and the cutover stays blocked while conflicts or
unclear write outcomes remain) → completion with a CSV report.

For sevDesk and easybill:

- **Master data** is reconciled via the contact link: customers and
  suppliers already linked to the system (transfer, contact push or contact
  sync) count as assigned.
- **Articles** cannot be reconciled with sevDesk and easybill, because
  WorkDiary only mirrors their article master and does not link it to local
  articles. Deselect the area in that case. The form marks it (“not with
  sevDesk, easybill”); if planning fails because of it, source, target and
  cutover date are kept and the area is deselected.
- The **document history** comes from the document mirror. Run the
  document fetch before the analysis and before completion so that open
  legacy documents are fully captured.
- The document images stay in the system. Back them up via its export
  before the contract ends.

Principles: finalized documents are **never** rebuilt in the target system —
they stay findable as history with number, status and origin. Every step is
recorded in a tamper-evident event chain.
