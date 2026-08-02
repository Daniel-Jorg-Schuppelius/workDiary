---
title: "Payment behavior"
topic: reports.payment-behavior
version: 2
audience: []
related:
    - reports.economics
    - reports.customer-value
---

Behavior and trend view of **locally managed invoices** — the billing
report shows the inventory (status, aging), this report the behavior
behind it. The reference date is always the **period end** (reproducible
reports).

## DSO with an example

**DSO** (days sales outstanding) = open receivables at month end
÷ revenue of the last 90 days × 90. Example: €12,000 open with €48,000
revenue in 90 days → 12,000 ÷ 48,000 × 90 = **22.5 days** of average
capital tie-up. A rising curve means the business increasingly ties up
liquidity — regardless of whether revenue grows.

## Days to pay vs. delay

- **Days to pay** = days from issue to payment (independent of the due
  date) — as a monthly trend and as a distribution (box plot) per customer.
- **Delay** = days **past due**; early payers count as 0. The top list
  shows customers with the highest average delay.

Reading the box plot: line = median, box = middle half, whiskers = range.
A customer with a median of 40 days on 14-day terms pays late
systematically — that is a pricing/terms issue, not a one-off.

## What to do with it

- **DSO rising** → review dunning, shorten payment terms, consider early
  payment discounts.
- **Individual customers with high average delay** → renegotiate terms,
  prepayment/installments for new orders, set an internal credit limit.
- **Overdue open invoices** (table below) → jump straight to the invoice
  or the customer's open invoices.

Clicking a customer in the box plot or delay top list filters this
report to them; if Lexoffice manages the invoices, they flow in via the
plugin's voucher mirror — the voucher sync also fetches the payment
data (payments endpoint). Without any data source the report states
this openly.
