---
title: "SLA, contracts & service levels"
topic: sla.overview
version: 9
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

You maintain the stages in the contract under **Escalation levels**: per
level the minutes after the resolution deadline and the recipient – the
assignee of the ticket, a role or a person.

## Which contract applies

For a new ticket the first matching contract applies in this order: contract
of the asset, contract of the project, contract of the customer, **Default
SLA** of the queue, **default contract** of the organisation. The contract of
the asset is the narrowest commitment – it applies to exactly this object and
is selected in the asset form (topic "Assets & fleet"). It only applies if it
is active and belongs to the ticket's customer or has no customer; otherwise
the remaining order applies. A change of queue leaves it in place. If the contract has no business
hours, those of the queue apply; the holiday region of the queue replaces
that of the organisation. The contract state is frozen on the ticket – later
changes to the contract do not reinterpret existing tickets.

## Pauses

Under **Pause SLA on** the contract defines in which waiting states the
clock stops. On resuming – also through a customer reply – the open deadlines
move by the length of the pause.

## OLA

A contract with **OLA (internal target)** and a team is not a promise to the
customer: it is never used as the SLA of a ticket and can neither be the
default contract nor be bound to a customer or project. Tickets in the queues
of the team additionally show the internal target at the SLA clock.

If an open ticket exceeds the internal target, the lead of the OLA team is
notified once (without a lead, the assignee); after that the escalation of the
notification rule **Internal target (OLA) exceeded** applies. WorkDiary keeps
no violation register for OLA.

## Contractually required maintenance

The contract page lists under **Contractually required maintenance** the
maintenance plans bound to this contract – with asset, next due date and
the **On due date** action. Plans with **Contractual obligation** carry
the marker of the same name. You set the binding in the dialog of the
maintenance plan on the asset (topic "Assets & fleet"). With the action
**Create a service ticket**, one ticket per due date is created to which
this contract applies.

## Included-time quotas

On the SLA contract, **Add quota** sets the included hours per period (month,
quarter, year), the **warning threshold** in percent from which deadline
monitoring reports, and – as evidence – the **flat fee** and the **overage
rate**. Usage is the billable person time of the contract customer in the
period. The contract page and the SLA report show usage, flat fee and the
overage amount (hours above the quota × rate). workDiary does not issue an
invoice from it – invoicing happens in the accounting program. Editing and
deleting require the permission to maintain SLA contracts.

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

## Material suggestion

If the ticket has equipment with a component list, it shows the **due wear parts** (from replacement interval and installation date) as a suggestion for the job — what is actually replaced is decided by the technician. The same card appears on orders with equipment.

**Confidential tickets in the report:** For confidential tickets you may not
open, the list of SLA breaches shows neither the ticket nor the customer; in the
breakdown by customer they count under "Confidential ticket".
