---
title: "Billing schedules"
topic: invoices.schedules
version: 1
audience:
    - admin
modules:
    - module.vertrieb
related:
    - invoices.manage
---

**Billing schedules** create recurring invoice **drafts** (MVP-415) —
issuing and sending always remain manual steps.

- **Rhythm**: week/month/quarter/year × count; the billing period is either
  the previous or the current period (advance payment).
- **Line item template**: placeholders `{zeitraum_von}` and `{zeitraum_bis}`
  are replaced on each run; discounts are carried over.
- **Contract**: optionally linked — when the contract ends, the schedule ends.
- **Idempotent**: at most one draft per schedule and period, even if the run
  starts multiple times; missed runs are caught up.
- **Invoicing sovereignty**: if an external system manages the customer's
  invoices, the schedule stays visibly blocked and creates nothing.
