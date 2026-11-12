---
title: "Edit a time entry"
topic: time-entries.edit
version: 4
keywords:
    - correct time
    - change time entry
    - fix hours
    - wrong time
    - change start and end
    - change break
    - rebook project
    - correction request
    - locked entry
    - change log
    - administrative time
    - record internal time
    - equipment runtime
    - runtime over several days
    - read out disk
audience: []
related:
    - time-entries.start
    - reports.customer-analysis
---

Click a row in the time tracking list to edit the entry. Changes are
recorded in the audit log with person, timestamp and previous value.

Important:

- **Already approved** entries are locked. For subsequent corrections
  use the **correction requests**.
- Never change only the end of a shift – always adjust **start, end and
  break as a set**, otherwise reports become inconsistent.
- Switching the project is allowed as long as the old project
  assignment has not been invoiced yet.

## Administrative time

Administrative time is working time without a project: meetings, training,
internal work, travel time, breaks and other activities. You always record it
for yourself – the entry is assigned to your own account. Recording it for
other people is not provided here.

**Where to start:**

- In the sidebar via **New …** → **Daily operations** → **Administrative
  time**. The date is prefilled with today.
- In the day view (**Daily operations** → **Entry** → **Today**) via the
  **Administrative time** button at the top right. The date is prefilled with
  the day shown – including an earlier day if you paged back to it with
  **Previous day**.

**Fields in the “Record administrative time” dialog:**

- **Date** and **Duration (minutes)** are required. The duration is between
  1 and 1440 minutes; 30 minutes are prefilled.
- **Activity type** (required): **Administration** (default), **Meeting**,
  **Training**, **Internal**, **Travel**, **Break** or **Other**.
- **Category (optional)**: one of your organisation's active activity
  categories; the list shows the activity type of each category.
- **Period (optional)**: **Start (time)** and **End (time)**. An end without a
  start is rejected. If the end is earlier than the start, it counts towards
  the following day – this is how you record times across midnight. If start
  and end are both given, the application calculates the duration from them
  and replaces the number of minutes you entered.
- **Description** (up to 500 characters) and **Tags**.
- If you already have a clocking on the prefilled date, the entry is linked to
  it. The dialog then shows the note “Will be linked with clocking
  (since …)”.

After **Capture** you return to the day view of the chosen date. The entry is
listed there under **Time entries** with its activity type and, if chosen, its
category.

**Differences from time tracking with a project:**

- There is no project field; the time is classified by activity type and
  category.
- Start and end are optional – a duration is enough.
- The activity type is limited to the kinds listed above that have no project
  reference.

**Editing and deleting:** In the **Today** view, the pencil icon (**Edit**)
in the row of an administrative time entry opens the **Edit administrative
time** dialog. In the **Edit administrative time** dialog you change
the same fields, write **Comments** and remove the entry with **Delete** (after
a confirmation prompt). After saving or deleting, the day view of the entry's
date opens. You may only edit and delete your own entries, and only as long as
they are not locked. An entry is locked if

- the correction window has expired (by default 7 days after the day of the
  entry),
- the month has already been released for you,
- you have already closed the day of the entry (changes then go through
  **Request correction** in the day view),
- the related timesheet is signed or locked, or
- the entry has already been exported.

The dialog then states the reason; comments remain possible.

**Permission:** Every signed-in person may record administrative time for
themselves. The **Administrator** role may also edit and delete other people's
entries and locked entries; the dialog then points out that you are editing as
admin.

## Equipment runtime

If a device or process keeps running without you, for example reading out a
defective hard disk over two days, record it as **Type** → **Equipment
runtime** instead of work:

- The duration may be up to **31 days**, in duration mode also beyond 24
  hours (for example `51:37`) or as **From / To** across several days.
  Entries of the types work, travel and standby stay limited to 24 hours.
- Equipment runtime does **not count as your working time**: it is excluded
  from flexitime, break checks, payroll export, payouts, SLA quotas and the
  working time of a timesheet. Project lists show it as a separate line
  “of which equipment runtime” and with a badge on the entry.
- It is billed at the organisation's **hourly rate for equipment runtime**
  (Settings → Invoice defaults); a **billing rule** on the project for the
  type equipment runtime takes precedence and may name a fixed price or its
  own service. Customer agreements and person rates do not apply.
