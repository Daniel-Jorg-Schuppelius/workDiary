---
title: "Report Targets"
topic: admin.report-targets
version: 1
audience:
    - admin
related:
    - reports.overview
    - admin.handbook
---

Report targets are benchmark values against which reports compare
the actual figures. The comparison yields a traffic light
(green/amber/red).

For each target you define:

- **Metric**: contribution margin, billable rate, rework share, SLA
  compliance rate or utilization.
- **Scope**: organization-wide or specifically for a customer, a
  project or a user.
- **Target value**: the numeric goal (e.g. a percentage).
- **Period** (optional): month, quarter or year – purely
  documentary.
- **Valid from/until** (optional): the period in which the target
  applies.
- **Note** (optional): a short explanation.

Mind the direction of each metric: for margin, billable rate, SLA
rate and utilization "higher is better"; for rework share "lower is
better".

Usage: the targets feed into reports – such as the economics and SLA
reports – where actual values are set against the targets and color-
coded.

Note: several targets may overlap (e.g. organization-wide and
project-specific). Keep scopes and validity periods unambiguous so
the traffic light evaluates the intended target.
