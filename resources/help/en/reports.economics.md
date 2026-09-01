---
title: "Profitability"
topic: reports.economics
version: 1
audience: []
modules:
    - module.auswertungen_team
related:
    - reports.customer-analysis
    - reports.drilldown
---

The profitability view (job costing) shows the contribution margin per customer
and per project for the selected period:

- **Revenue** = billable time × rate + billed material + billable expenses.
  The authoritative invoice is produced by the external billing system; the
  recorded amounts serve as a projection here.
- **Cost** = internal time cost rate × time + direct material and receipt cost.
- **Contribution margin** = revenue − cost, plus **margin** as a percentage.

Additional analyses:

- **Ranking** (top/flop 5) per project and customer by contribution margin —
  making loss-making customers, projects and jobs visible.
- **Non-billable time** (`billable=false`) as a proxy for rework and goodwill,
  reported separately with its share.
- **Plan vs. actual** per project: actual minutes against the project time
  budget and actual cost against the project budget (€).

Data quality notes:

- If **no internal cost rate** is maintained for some entries, they are
  included with €0 cost — the contribution margin is then too optimistic
  (marked with \`*\`).
- Projects **without a time budget/budget** show "–" in the plan-vs-actual
  column.

Export as CSV or PDF for management and controlling. Org-wide financial data —
restricted to users with report read permission.
