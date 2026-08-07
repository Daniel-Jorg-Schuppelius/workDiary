---
title: "Supplier analysis"
topic: reports.supplier-analysis
version: 1
audience: []
related:
    - reports.customer-analysis
    - reports.customer-value
---

Supplier analysis is the purchasing counterpart to customer analysis and
answers: **what do we spend money on, which suppliers do we depend on,
where are open liabilities?**

## How to read this report

- **Spend per supplier (Pareto)**: descending bars plus a cumulative
  percentage line — how quickly the line reaches 80 % reveals dependence
  on a few suppliers (concentration risk in purchasing).
- **Spend per month**: org-wide spend trend of the last twelve months,
  independent of the selected period.
- **Open amount per supplier**: purchase vouchers not yet fully paid —
  the current liabilities.

## Data source

Spend comes from the **accounting voucher mirror** (purchase invoices,
purchase credit notes and generic vouchers per supplier). Credit notes
reduce spend. Drafts and voided vouchers do not count. The report
therefore works **without the warehouse module**.

If the **warehouse module** is active, **purchase orders** (placed in the
period) and **open purchase orders** (currently running) are added per
supplier.

## Metrics

- **HHI (concentration)** — Herfindahl-Hirschman index over spend: below
  1500 uncritical, 1500–2500 moderate, above 2500 high.
- **Top-5 share** — share of the five highest-spend suppliers;
  concentration risk starts at roughly 60 %.
- **Trend %** — spend in the period versus the immediately preceding,
  equally long comparison period.

Each row opens the **supplier detail page** on click. The report shows
financial data and is therefore only visible to users with reporting
permission.
