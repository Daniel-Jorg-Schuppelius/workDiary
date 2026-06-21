---
title: "Cohort comparison (before/after training)"
topic: reports.cohort-comparison
version: 1
audience: []
related:
    - reports.economics
---

The cohort comparison shows whether a metric improved for employees after they
acquired a training/qualification.

How it works:

- Pick a **training/qualification**, a **metric** (billable rate or rework
  share) and a **comparison window** in days (default 90).
- For every employee holding that qualification the metric is computed for the
  window **before** and **after** the acquisition date, and the difference
  (delta) is shown.
- A **cohort mean** is aggregated across all employees with an acquisition date.

Data basis:

- The **acquisition date** comes from the "valid from" field of the
  qualification assignment (user_qualifications.valid_from). Employees
  **without a recorded acquisition date** cannot enter the before/after split
  and are reported separately and transparently.
- Metrics are derived from the **same time-entry fields** (billable/non-billable)
  as the economics view; there is no parallel calculation.

Note: if one of the windows has no time entries, the comparison cannot be formed
for that person ("–"). The comparison is an indicator, not causal proof.
