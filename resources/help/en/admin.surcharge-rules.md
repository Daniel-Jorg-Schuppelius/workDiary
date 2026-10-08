---
title: "Surcharge rules"
topic: admin.surcharge-rules
version: 3
keywords:
    - night premium
    - night shift allowance
    - Sunday premium
    - holiday premium
    - weekend premium
    - shift premium
    - wage type
    - payroll export
    - DATEV payroll
    - Lexware
    - premium rates
audience:
    - admin
    - geschaeftsfuehrung
    - buchhaltung
modules:
    - module.lohn
related:
    - exports.payroll
    - finance.transfers
    - admin.handbook
    - glossary.core
---

Surcharge rules define night, weekend, public holiday and custom
time-window surcharges as well as surcharges for on-call duty, standby
duty and overtime. During the time export, times are evaluated
accordingly and reported as separate lines per wage type.

Typical workflow:

1. **Create** opens the **Create surcharge rule** dialog. Under
   **Basics** you enter the **Code** (unique, e.g. “night”), **Label**
   (e.g. “Night surcharge”), **Kind** and **Surcharge (%)**
   (0–999.99).
2. Choose the **Kind**: **Night** (time window, may cross midnight,
   e.g. 22:00–06:00), **Saturday**, **Sunday**, **Public holiday**
   (public holidays automatically), **Custom** (free time window),
   **On-call duty**, **Standby duty** or **Overtime**. For Night and
   Custom you set the **Time window** with **Window from** and **Window
   to**.
3. Under **Payroll handover**, optionally enter the **Wage type** for
   DATEV/Lexware (e.g. “2010”) and the **Priority**. With **Tax-free up
   to (%)** and **Wage type for taxable share** you split a surcharge
   above the tax-free limit into two wage types.
4. Under **Validity**, optionally set **Valid from**/**Valid until** and
   switch on **Rule is active**; under **Conditions** you restrict the
   rule to **Teams**, **Sites** or **Shift types**.

Important rules:

- With overlapping rules of the kinds Night, Saturday, Sunday, Public
  holiday and Custom the **highest percentage wins** – surcharges are
  not added up. On a tie, priority decides.
- **On-call duty** evaluates the hours of recorded standby duties,
  **Standby duty** evaluates time entries of the kind Standby, and
  **Overtime** the hours above the monthly target. These three kinds
  need no time window, are not offset against the other surcharges and
  appear as separate lines. The export currently does not evaluate
  validity and conditions for them.
- Conditions restrict a rule: empty = applies to everyone; multiple
  conditions combine with AND, within one list a single match suffices.
  Sites are detected via terminal clock-ins — without determinable
  context a conditional rule does not apply. Sites may carry their own
  holiday region (holiday surcharge at the place of work).
- Changes affect **future exports**; already created exports remain
  unchanged (correction via re-export). Historical periods are only
  re-evaluated by an audited recalculation run by operations — never
  by a silent rule change.

Permissions: **View surcharge rules** shows the list; only people with
the **Manage surcharge rules** permission may create, edit and delete
rules.
