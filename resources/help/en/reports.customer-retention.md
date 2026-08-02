---
title: "Customer retention"
topic: reports.customer-retention
version: 2
audience: []
related:
    - reports.customer-value
    - reports.customer-analysis
---

This report shows **how well the company retains its customers** — and
what feeds the customer base.

## Reading the cohort matrix

Customers are grouped by their **first service year** (org-wide,
independent of the period filter). Each row is a cohort, each column
"+n" the n-th year after. Example: row **2028 (n=12)**, column **+2**
= 75 % → of the 12 customers whose first service was in 2028, 9 also
bought services in 2030. If a row drops quickly, customers are lost soon
after onboarding. **Clicking a row or cell** opens the cohort's name list.

## Customer bridge — definitions

"**Active**" at a reference date means: service within the configured
threshold before it (default 365 days, filter "Lost after"). The bridge
adds up exactly:

Base at start **+ new customers** (first service in the period)
**+ won back** (inactive before, active again)
**− new, inactive again** (first-timers without follow-up)
**− lost** (active at start, not at the end)
= base at end.

Clicking a bridge step jumps to the name list below; every name links to
the customers & projects report.

## KPIs

- **Returning rate**: share of last year's active customers who are also
  active in the reporting year — the most honest retention metric.
- **Ø customer age**: years since first service, averaged over customers
  active at the end.

## What to do with it

- Cohort collapses in year +1 → review onboarding / second order.
- Lost customers piling up → collect loss reasons (price, quality,
  contact person), start targeted win-back.
- Returning rate below ~70 % in a repeat business → set up retention
  measures (maintenance contracts, check-in appointments).
