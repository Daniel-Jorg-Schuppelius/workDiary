---
title: "Report Targets"
topic: admin.report-targets
version: 3
keywords:
    - target values
    - goals
    - benchmark
    - KPI
    - key figures
    - traffic light
    - plan vs actual
    - utilization target
    - margin target
    - billable ratio
    - SLA rate
audience:
    - admin
    - geschaeftsfuehrung
related:
    - reports.overview
    - admin.handbook
---

Targets are benchmark values against which reports compare the actual
figures. The comparison yields a traffic light (green/amber/red). You
maintain them on the **Targets & benchmarks** page; **Add target**
opens the dialog.

For each target you define:

- **Metric**: **Contribution margin (%)**, **Billable rate (%)**,
  **Rework share (%)**, **SLA compliance rate (%)** or **Utilization
  (%)**.
- **Target value**: the numeric goal as a percentage.
- **Scope**: **Organization (global)** or specifically **Customer**,
  **Project** or **Employee**; you select the matching **Scope object**
  next to it.
- **Reference period** (optional): **Month**, **Quarter** or **Year** –
  purely documentary.
- **Valid from**/**Valid until** (optional): the period in which the
  target applies.
- **Note** (optional): a short explanation.

Mind the direction of each metric: for margin, billable rate, SLA
compliance rate and utilization “higher is better”; for rework share
“lower is better”.

Usage: the targets feed into reports – such as the economics,
utilization and SLA reports – where **Target**, **Actual** and the
deviation are shown side by side and color-coded.

Note: several targets may overlap (e.g. organization-wide and
project-specific). In that case the more specific scope applies; on a
tie, the most recently created target wins. Keep scopes and validity
periods unambiguous so the traffic light evaluates the intended target.
