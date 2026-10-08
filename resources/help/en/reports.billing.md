---
title: "Billing, expenses, payouts and revenue"
topic: reports.billing
version: 1
keywords:
    - outstanding receivables
    - aging report
    - unbilled time
    - revenue per customer
    - dunning levels
    - quote acceptance rate
    - expense overview
    - contractor fees
    - pay freelancers
    - surcharge preview
    - revenue per article
    - revenue by category
audience:
    - admin
    - geschaeftsfuehrung
    - buchhaltung
    - personalverwaltung
    - teamleitung
related:
    - invoices.manage
    - finance.dunning
    - finance.incoming-invoices
    - travel-expenses.manage
    - org.members
    - admin.surcharge-rules
    - articles.master
    - reports.economics
---

These reports bring together the money around services and staff: the state
of invoices and outstanding receivables, time not yet billed, expenses,
payouts to external staff, expected surcharges from the shift plan and the
revenue per article. You find most pages under **Reports** → **Finance &
audit**, the **Surcharge forecast** under **Reports** → **Team**.

## Period and export

- You choose the period with the period selector in the header. The
  **Surcharge forecast** instead looks ahead from the current month.
- **PDF** downloads a print version; **CSV** and **Excel** are available under
  **Export**; the formats offered are stated with each report. Exports keep
  the filters you have set. PDF and CSV exports are recorded in the audit
  log.

## Billing

**Reports** → **Finance & audit** → **Billing** opens the **Billing report**.
It opens for administrators and roles with the **See all time entries**
permission; without this permission access is denied.

- Tiles:
  - **Issued + paid (Σ gross)**: gross total of the invoices with the status
    **Issued** or **Paid** whose invoice date lies in the period (without an
    invoice date, the creation date counts).
  - **Outstanding receivables**: gross total of all invoices with the status
    **Issued**, regardless of the period. The tile turns red as soon as one of
    them is more than 30 days overdue; the hint states their number.
  - **Unbilled time**: billable time entries of the period that have not yet
    been used up by any billing path, with the number of entries and the
    expected revenue from the stored amounts.
- Charts: billable and non-billable hours over time, and **Revenue per
  customer (top 15)** from local invoices and the vouchers mirrored from the
  accounting software. Clicking a customer opens **Customers & projects** for
  that customer.
- **Invoices by status**: **Quantity**, **Net** and **Gross** per status.
- **Aging – outstanding items**: the open invoices by days past the due date
  (without a due date, from the invoice date) in the levels **Current**, 1–7,
  8–14, 15–30 and more than 30 days, with **Open total**.
- **Top customers (issued + paid in the period)**: **Customer**, **Invoices**
  and **Gross**; if amounts come from the accounting software, a further
  column **thereof accounting system** appears.
- **Incoming e-invoices (in period)**: incoming documents per status with
  number and gross amount, plus the number handed over to accounting.
- **Incoming validation & dunning levels**: **Validation reviewed**,
  **Validation passed**, **Validation failed** and the open invoices per
  dunning level 1 to 3.
- **Quotes & document chain (in period)**: quotes per status, **Acceptance
  rate**, **Median creation → decision** in days, **Quote → invoice**, **Pro
  forma → invoice**, **Cancellations / credit notes** and **Correction rate**.

Filters: **Customer**, **Project**, **Employee** and **Include hidden
customers**. Customer and project apply to invoices, quotes and times, the
employee only to the times. Incoming invoices and dunning levels always apply
to the whole organization. When a project is selected, the amounts from the
accounting software are left out because these vouchers have no project.
Export as PDF, CSV and Excel.

## Expenses

**Reports** → **Finance & audit** → **Expenses** opens the **Expense report**:
expenses per employee and category over the period, calculated with gross
amounts by the date of the expense.

- Charts: expenses per month (or week or day) by category, with the four
  largest categories shown individually and the rest combined, and **Top
  spenders (top 15)**.
- Tiles: **Total (gross)**, **Employee**, **Categories** and **months**.
- Table with one row per **Employee** and **Category**, one column per month
  and the **Total**, followed by **Top categories**.

Filters: **Area** (**Mine only** or **Entire tenant**, administrators only),
**Employee**, **Team**, **Project** and **Status**. Without administrator
rights you only see your own expenses. The page offers no export.

## External payouts

**Reports** → **Finance & audit** → **External payouts** calculates the
amounts to be paid to external staff in the period. The menu item appears
with the **Manage personnel & payroll data** permission.

It covers employees whose **Compensation model** is set to **Flat rate** or
**By time spent**:

- **Flat rate** with the interval **Monthly**: **Flat amount (€)** times the
  number of months in the period.
- **Flat rate** with the interval **Per assignment**: flat amount times the
  number of days with time entries.
- **Flat rate** with the interval **One-time**: the flat amount once.
- **By time spent**: recorded time times **Compensation rate (€/h)**.

The table shows **Employee**, **Model**, **Calculation basis** and **Amount**
with a grand total. Charts show the payouts over time and **Payouts per
external (top 15)**. All amounts are gross, excluding tax and social security.
Filter: **Employee**. The page offers no export.

## Surcharge forecast

**Reports** → **Team** → **Surcharge forecast** estimates the surcharge
minutes per month and wage type on the basis of the planned shifts in the
**Shift plan**. The menu item appears for administrators and with the **View
reports** permission.

- **Months**: 3, 6 or 12 months from the current month.
- **Employee**: all active employees or one person.
- Table: **Wage type**, **Rule**, one column per month and **Total**, with a
  totals row.

The calculation uses the active **Surcharge rules**; cancelled shifts do not
count. It is a pure preview without site context: rules that depend on the
site only apply once time is stamped. Settlement happens exclusively via the
time export. Export as CSV and Excel.

## Revenue per product

**Reports** → **Finance & audit** → **Revenue per product** shows quantity,
net revenue and share per article. The menu item appears for administrators
and with the **See all time entries** permission.

- Data basis: lines of local invoices, partial and final invoices whose
  invoice date lies in the period and whose status is **Issued**, **Partially
  paid** or **Paid**; credit notes and cancellation documents reduce the
  figures with a negative quantity. In addition, invoices and credit notes
  mirrored from the accounting software are included. Documents handed over
  from a local invoice count only once, drafts and cancelled documents not at
  all.
- Tiles: **Total net revenue**, **thereof from the accounting system**,
  **Articles with revenue** and **Share without article link** (lines without
  a selected article).
- Chart of the articles with the highest revenue; you set how many it shows
  with **Top N in chart** (3 to 50, default 10). A click opens the article.
- **Revenue by category**: **Category**, **Article**, **Net revenue** and
  **Share**.
- Table: **Article number**, **Article**, **Quantity**, **Unit**, **Net
  revenue**, **Share**, **Receipts** and **Source** (**local** or the name of
  the connected accounting software). Lines without an article are grouped
  under **without article link**.

Export as PDF, CSV and Excel.
