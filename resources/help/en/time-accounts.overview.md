---
title: "Time accounts"
topic: time-accounts.overview
version: 2
keywords:
    - additional account
    - time-off account
    - time off in lieu
    - TOIL
    - night shift counter
    - allowance hours
    - account balance
    - traffic light
    - booking journal
    - reversal entry
    - export time accounts
    - period comparison
audience: []
related:
    - time-accounts.flex
---

Additional time accounts track selected time figures as dedicated
accounts — for example a counter of night shifts worked, a time-off
account for extra work or collected allowance hours. Flex time and
vacation remain in the working time account.

The overview shows the current balance per account with a traffic light
(thresholds are set by the organisation), the average monthly turnover and
a simple trend. "View journal" shows every single posting with date,
quantity, source and note — corrections appear as reversal entries,
nothing is overwritten.

The report (for management roles) compares opening balance, turnover and
closing balance per employee for a period and can be exported as CSV or
PDF.

## Period comparison

The period comparison shows the postings of a time account side by side per
calendar week or per month. You find it under **Reports** → **Team** →
**Period comparison**.

- In the filter bar you choose the **Account** (all active time accounts) and
  the **Granularity**: **Calendar week** (default) or **Month**. The choice
  takes effect immediately.
- The period follows the date filter in the header. At most 53 columns are
  shown, i.e. one year in weeks.
- For each employee the table shows the **Opening balance** (sum of all
  postings before the period), the sum per week or month, the **Net change**
  within the period and the **Closing balance**. The
  closing balance carries the account's traffic light colour. All values are
  shown in the account's unit.
- People whose opening balance and turnover are both zero are not listed. If
  there are no values at all in the period, the page reports “No postings in
  the selected period.”

**Export:** **PDF** and, under **Export**, the formats **CSV** and **Excel**.
PDF and CSV contain the selected account. Excel delivers all active accounts
as one worksheet each in the same workbook.

**Visibility:** The **Administrator** role sees all employees of the
organisation; everyone else sees only their own row. If no active time
accounts are set up, the page shows the note “No time accounts configured”.
