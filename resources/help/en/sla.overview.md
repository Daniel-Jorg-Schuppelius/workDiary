---
title: "SLA, contracts & service levels"
topic: sla.overview
version: 1
audience: []
related:
    - glossary.core
---

SLA contracts (Service Level Agreements) define the agreed response and
resolution deadlines per priority, either per customer or as a default. From
these targets WorkDiary derives a service ticket's SLA status and records
breaches in an audit-proof register.

## SLA status on the ticket

Every service ticket with an SLA deadline shows its resolution status as a
badge:

- **SLA on track**: enough time left until the resolution deadline.
- **SLA at risk**: remaining time is below 20 % of the total deadline.
- **SLA breached**: the deadline has passed (or the ticket was acknowledged
  or resolved too late).

The response deadline is evaluated the same way and checked on the first
acknowledgement.

## Violation register & detection

Missed deadlines are recorded in a violation register – exactly once per
ticket and type (response or resolution). They are detected:

1. by the nightly scan of open tickets (`tickets:scan-sla-breaches`),
2. on status transitions when the first response or the resolution happens
   too late.

Each violation can be acknowledged and annotated with a cause.

## Escalation

The deadline scanner notifies the ticket owner about at-risk and breached
tickets and – as an escalation – the team lead. Thresholds and recipients
follow the organisation's notification rules.

## SLA report

The SLA report (Reports → SLA) shows, for the selected period, the
compliance rate, violations by type, priority and customer, a cause grouping
and a violation list with drill-down to the ticket. The report is
exportable as CSV and PDF.
