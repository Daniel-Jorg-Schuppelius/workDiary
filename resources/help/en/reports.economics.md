---
title: "Profitability"
topic: reports.economics
version: 3
keywords:
    - post-calculation
    - contribution margin
    - margin
    - project profitability
    - profit per customer
    - plan vs actual
    - budget comparison
    - internal cost rate
    - loss-making projects
    - controlling
    - top and flop
    - job costing
audience: []
modules:
    - module.auswertungen_team
related:
    - reports.customer-analysis
    - reports.drilldown
---

The **Profitability** page (post-calculation) under **Reports** →
**Finance & audit** → **Profitability** shows the contribution margin per
customer (**Profitability per customer**) and per project
(**Profitability & plan-vs-actual per project**) in the selected
**Period**:

- **Revenue** = billable time × rate + billed material + billable
  expenses. The authoritative invoice is kept by the external billing
  system; here the recorded amounts serve as a projection.
- **Costs** = internal time cost rate × time + direct material and
  receipt expenses.
- **Contribution margin** = revenue − costs, also shown as **Margin** in
  percent.

Further evaluations:

- **Ranking**: “Top 5 customers (contribution margin)”, “Bottom 5
  customers (contribution margin)” and the same for projects – making
  loss-making customers and projects visible.
- **Non-billable time**: per customer, **Billable (min.)**,
  **Non-billable (min.)** and **Share %** show how much time was recorded
  without billing – an indicator of rework and goodwill. Per project,
  **Rework (min.)**, **Goodwill (min.)** and **Rework %** show the
  time recorded with a rework or goodwill reason.
- **Plan vs. actual** per project: **Actual (min.)** against **Plan
  (min.)** from the project time budget (**Δ Min.**) and actual costs
  against the **Plan budget** in euros (**Δ Budget**).

Data quality notes:

- If **no internal cost rate** is maintained for some entries, they are
  included with €0 cost — the contribution margin is then too
  optimistic. The costs then carry an asterisk with the note “Cost rates
  not fully maintained”.
- Projects **without a time budget/budget** show “–” in the plan columns.

Export as **PDF**, **CSV** or **Excel** for management and controlling.
The page shows organisation-wide financial data and is only available to
people with the **View reports** permission.
