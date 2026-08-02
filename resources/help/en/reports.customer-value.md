---
title: "Customer value"
topic: reports.customer-value
version: 1
audience: []
related:
    - reports.customer-analysis
    - reports.customer-retention
---

The customer value report answers: **which customers does the company
live on, where is concentration risk, which A-customers are at risk?**

- **RFM segments**: each customer gets quintile scores 1–5 for
  **R**ecency (days since last service), **F**requency (activity days
  in the period) and **M**onetary (revenue). This yields the segments
  *Champions*, *Loyal*, *Potential*, *New*, *At risk* and *Inactive*.
- **Concentration**: top-5/top-10 revenue share and the
  Herfindahl-Hirschman index (HHI). Below 1500 is uncritical, above
  2500 indicates high concentration risk.
- **A-customers at risk**: high revenue (M ≥ 4) but no service since
  the configured threshold — with a 12-month revenue sparkline.

**Revenue** comes from billable time snapshots (same source as the
economics report); invoiced totals are a secondary value only, as they
are incomplete when invoicing is done externally.
