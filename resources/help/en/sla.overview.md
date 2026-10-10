---
title: "SLA, contracts & service levels"
topic: sla.overview
version: 4
keywords:
    - service level agreement
    - response time
    - resolution time
    - service contract
    - SLA breach
    - deadline overrun
    - escalation
    - overdue ticket
    - compliance rate
    - SLA report
audience: []
related:
    - glossary.core
---

SLA contracts (service level agreements) store the agreed response and
resolution deadlines per priority (**Deadlines per priority**) per
customer or as a **Default contract** for all customers, optionally with
**Business hours** – without them, deadlines run in calendar time. You
find the contracts under **Service desk** → **SLA contracts**. From these
targets WorkDiary derives the SLA status of a service ticket and records
breaches in an audit-proof way.

## SLA status on the ticket

Every service ticket with an SLA deadline shows its resolution status as
a badge:

- **SLA on track**: enough time remains until the resolution deadline.
- **SLA at risk**: the remaining time is at most 20 % of the total
  deadline.
- **SLA breached**: the deadline has passed (or the ticket was
  acknowledged or resolved too late).
- **SLA met**: the ticket was resolved in time.

Tickets without a deadline show “No SLA”. The response deadline is
evaluated in the same way and checked at the first acknowledgement.

## Violation register & detection

Missed deadlines are recorded in a violation register – exactly once per
ticket and type (“Response time” or “Resolution time”). They are
detected:

1. by the automatic check of open tickets, which runs every five minutes
   by default,
2. on status transitions, when the first response or the resolution
   happens too late.

Each violation can be given a **Cause** in the SLA report's **Violation
list** and marked with **Acknowledge**; this requires the **Acknowledge
SLA violations** permission.

## Escalation

The automatic check notifies the assigned person about at-risk and
breached tickets. If the event remains unresolved, WorkDiary escalates
according to the organisation's **Notification rules** to the escalation
role set there (team leads by default). In addition, WorkDiary works
through the stages stored under **Escalation** in the SLA contract.

## SLA report

The **SLA report** (**Reports** → **Projects & customers** → **SLA**)
shows, for the selected period, the **Tickets with SLA**, the
**Compliance rate** and the **Violations** – broken down **By type**,
**By priority**, **By customer** and **By cause** – plus a **Violation
list** with a jump to the ticket and the **Included-time quotas**. The
report can be exported as PDF, CSV and Excel. Anyone with the **View SLA
status & report** permission may view it; exporting additionally requires the
permission **Export reports** – without it, the export buttons are missing.
Administrators may always do both.
