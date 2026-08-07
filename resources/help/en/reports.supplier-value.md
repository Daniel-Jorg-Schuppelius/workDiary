---
title: "Supplier value"
topic: reports.supplier-value
version: 1
audience: []
related:
    - reports.supplier-analysis
    - reports.customer-value
---

The supplier value report is the purchasing counterpart to customer value
and answers: **which suppliers do we depend on, where is concentration
risk, which are strategic, dormant or occasional?**

## How to read this report

- **Spend per supplier (Pareto)**: descending bars plus a cumulative
  percentage line — how quickly the line reaches 80 % reveals dependence
  on a few suppliers.
- **Spend by inactivity**: the further right, the longer since the last
  voucher; points to the right **above** the P80 line are high-spend
  suppliers that have not delivered for a long time.
- **Suppliers per segment**: clicking a bar filters the supplier list
  below to exactly those suppliers.
- **Risk list**: suppliers whose spend share exceeds the configured
  threshold (single-source concentration risk), with a 12-month spend
  trend (sparkline).

## R, F and M — how the scores work

Every supplier active in the period gets three **quintile scores from 1 to
5**:

- **R (Recency)** — days since the last voucher. The shorter, the higher.
- **F (Frequency)** — number of voucher days in the period.
- **M (Monetary)** — spend in the period (purchase vouchers from the
  voucher mirror, credit notes reduce it).

Quintile means suppliers are split into five equally sized groups per
metric. Scores are therefore **relative to your own supplier base**, not
absolute.

## Segments

- **Strategic** — R ≥ 4, F ≥ 4, M ≥ 4 (high spend, regular, current).
- **Dormant key supplier** — R ≤ 2 with M ≥ 4 (high spend but no vouchers
  for a long time).
- **Core supplier** — F ≥ 3 (regular procurement).
- **Occasional** — all other active suppliers.
- **New** — first voucher falls within the period.
- **Dormant** — no vouchers in the period.

The report shows financial data and is only visible to users with
reporting permission.
