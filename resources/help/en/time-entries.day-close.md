---
title: "Daily close"
topic: time-entries.day-close
version: 3
keywords:
    - close the day
    - end of day
    - end workday
    - add break
    - daily balance
    - time gaps
    - mandatory break
    - open clock-in
    - request correction
    - book missing time
    - day summary
audience: []
related:
    - time-entries.start
    - attendance.manage
    - time-accounts.flex
---

The **Daily close** bundles everything that belongs to a working day on
the **Today** page (menu **Daily operations** → **Entry** → **Today**):
**Clockings**, breaks, **Time entries**, the checks under **Gaps &
warnings** and the **Balance** (including **Attendance (gross)**,
**Required break**, **Day balance** and **Balance current month**).

How to proceed:

1. **Review**: open the page at the end of the day; **Previous day** and
   **Next day** take you to other days. Gaps and inconsistencies appear in
   the **Gaps & warnings** section.
2. **Fill in**: record missing times in the entry bar at the top (choose
   a project, enter **Duration** or **From / to**, **Capture**). Assign
   attendance blocks that are not yet booked to a project under **Quick
   booking** with **Book**; there, `Ctrl` + `Enter` books the block and
   moves on to the next one. Clockings themselves can only be changed via
   a correction request.
3. **Close**: once no ⛔ warnings remain, close the day with **Close
   day**. **Save** keeps the current state without closing the day.

⛔ warnings block closing: an open time clock, unallocated attendance
(more than 5 minutes) or a required break that was not met. ⚠ notices do
not block, for example a day balance beyond ±2 hours, more than 10 hours
of net working time, an attendance gap without a break or billable
bookings without a comment.

After closing, the day is locked for you. If you need a change, request
approval via **Request correction** (reason, at least 20 characters).
People with the **Approve day-closure corrections** permission (team
leads by default) or administrators decide with **Approve** or
**Reject**. After approval only bookings can be changed; clockings stay
locked. Anyone with the **Reopen day closure** permission can also
unlock a closed day without a request via **Reopen day**; the reason is
stored in the audit log.

Days in an already approved month are fully locked; any change there
goes through the monthly approval.
