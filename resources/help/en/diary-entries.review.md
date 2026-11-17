---
title: "Internal review of orders"
topic: diary-entries.review
version: 1
keywords:
    - review
    - four-eyes principle
    - approval
    - query
    - submit for review
    - review requirement
    - period
audience: []
related:
    - diary-entries.edit
    - diary-entries.follow-up
    - carry-forward.notes
---

Orders of an entry type with a **review requirement** go through an internal
review before completion: the person working on it submits the order, a second
person approves it or raises a query. Nobody approves their own work.

## How it works

1. **Submit for review** – this step replaces “Complete” on the order. The
   order is then **In review**.
2. The reviewer **approves** or **raises a query** (text required). The
   question is recorded in the history; the order is set to **Query** and goes
   back to the person working on it.
3. After a query, **resubmit**. As long as nobody has decided, a submission can
   also be **withdrawn**.
4. After approval (**Approved**) the person working on the order completes it –
   for example after filing. Where the approval itself is the completion, the
   reviewer uses **Approve and complete**.

## Who reviews

Anyone with the **Review orders** right or named as **reviewer** on the order
may review – never the person who submitted it. If a reviewer is named, the
submission only appears for them; otherwise for everyone with the review right.

If you are only named as reviewer and have no approval right, you find the
submission in the **For review** tile and approve it via the review queue; the
approval inbox then stays closed to you.

## Where submissions appear

- **For review** in the order book: the review queue, by due date, then by
  submission date.
- In the **approval inbox** and the **For review** tile on the dashboard. Your
  own submissions never appear there.
- In the **work queue** your orders are listed in working order: query,
  approved, in progress, accepted.

## Rides, patrols, tours and Kanban

If the work is finished outside the order – ride completed, patrol finished,
tour stop done – the app submits an order that requires review instead of
completing it; the step appears in the history. On the Kanban board,
submitting, approving and querying run through the order: dragging a card into
the review columns or to **Completed** points you there.

## Review requirement and period

- The review requirement comes from the entry type. With the right **Waive the
  review requirement on an order** it can be switched off or on for a single
  order before submission; this is recorded with a reason in the history and
  the audit log.
- If the entry type requires a **period** (year, half-year, quarter, month),
  it is mandatory when creating the order. Format: `2026`, `2026-H1`,
  `2026-Q1`, `2026-03`.
