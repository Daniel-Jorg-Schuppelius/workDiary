---
title: "Duty and shift planning"
topic: planning.shifts
version: 4
keywords:
    - roster
    - rota
    - shift schedule
    - staff scheduling
    - workforce planning
    - shift types
    - early shift
    - night shift
    - minimum staffing
    - staffing levels
    - publish roster
audience: []
related:
    - attendance.manage
    - absences.manage
    - reports.overview
---

Duty plans structure a planning period; the shift schedule assigns
specific shifts to employees, dates and shift types.

Typical flow:

1. Maintain shift types, teams and required qualifications.
2. Create the duty plan and assign its shifts.
3. Review conflicts with leave, sickness, working time or missing
   qualifications.
4. Publish the plan and document later changes.

A published schedule is visibly binding for employees. Change it only
with the appropriate permission and inform affected people about
short-notice adjustments.

Per shift type you can define minimum, ideal and maximum staffing —
optionally with qualification minimums (e.g. "at least 2 certified
nurses in the early shift"). The heatmap in the duty plan highlights
days where headcount or qualification minimums are missed.

You create entries in the duty plan under **Target staffing** with **Add
requirement**. The fields **Weekday** and **Specific date** determine when an
entry applies; if both stay empty, it applies on all days. The list shows this
in the column **Scope** as **Specific date**, **Weekday** or **Always**. The most
specific entry applies: date before weekday before **Always**. Entries with
**Always** apply on every day of the duty plan. If no entry fits on a day, the
duty plan's **Minimum staff per shift** applies to the plan's shift types. Only
shifts with the status **Published** or **Confirmed** count as staffed; drafts
and cancelled shifts do not. The report **Coverage** calculates by the same
rules.

With **For all duty plans** an entry applies across plans: in every duty plan and on days without a duty plan, for example for shifts entered directly in the **Shift plan**. The list marks such entries with **All duty plans**; you see and edit them in every duty plan.
