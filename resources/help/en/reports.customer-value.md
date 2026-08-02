---
title: "Customer value"
topic: reports.customer-value
version: 2
audience: []
related:
    - reports.customer-analysis
    - reports.customer-retention
---

The customer value report answers: **which customers does the company
live on, where is concentration risk, which A-customers are at risk?**

## How to read this report

- **Revenue per customer (Pareto)**: descending bars + cumulative percent
  line — how fast the line reaches 80 % shows dependency on few customers.
- **Revenue by inactivity**: the further right, the longer without
  service; dots on the right **above** the P80 line are A-customers at risk.
- **Customers per segment**: clicking a bar filters the customer list
  below to exactly those customers.
- **Risk list**: high-revenue customers without service since the
  configured threshold, with a 12-month revenue sparkline.

## R, F and M — how the scores are built

Every customer active in the period gets three **quintile scores from
1 to 5**:

- **R (recency)** — days since the last service. The shorter, the higher.
- **F (frequency)** — number of activity days in the period.
- **M (monetary)** — revenue in the period (billable time snapshots,
  same source as the economics report).

Quintile means: customers are split into five equal groups per metric.
Example with five customers by revenue 10,000/8,000/5,000/1,000/300 €
→ M scores 5/4/3/2/1. Scores are **relative to your own customer base**,
not absolute.

## The segments (first matching rule wins)

| Segment | Rule |
| --- | --- |
| Inactive | no service in the period |
| New | first service falls into the period |
| Champions | R ≥ 4 and F ≥ 4 and M ≥ 4 |
| At risk | R ≤ 2 with M ≥ 4 (high revenue, long quiet) |
| Inactive | R ≤ 2 (active early, then silent) |
| Loyal | F ≥ 3 |
| Potential | all remaining active customers |

## HHI — concentration with an example

HHI = sum of **squared** revenue shares in percent. Two customers with
50 % each → 50² + 50² = **5000** (extremely concentrated); ten customers
with 10 % each → 10 × 10² = **1000** (uncritical). Guide values: below
1500 uncritical, 1500–2500 moderate, above 2500 high concentration risk.

## What to do with the segments

- **Champions**: retain — priority service, no experiments.
- **At risk**: reach out actively, find out why they went quiet.
- **Potential**: targeted offers — this is where growth lives.
- **New**: finish onboarding properly, secure the second order.
- **Inactive**: decide consciously — reactivate or close cleanly.
- **HHI/top-5 high**: prioritize new-customer acquisition.

Every chart point and table row links to its data basis (customers &
projects report or the filtered customer list).
