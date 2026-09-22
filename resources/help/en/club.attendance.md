---
title: "Attendance records"
topic: club.attendance
version: 1
audience: []
modules:
    - module.club
related:
    - club.events
    - club.members
---

The attendance sheet belongs to a single club event. It shows the target list (target groups on the event day, registered and spontaneously added members, each member once) and records one status per person: present, partially present, excused or absent. An untouched row explicitly stays open and does not count.

**Minutes instead of a time clock:** Present takes the actually conducted duration without breaks, which you enter per event and may reduce per person. Partial attendance is recorded via minutes or via arrival and departure; everything is calculated in whole minutes, never negative and never longer than the conducted duration. Multi-day courses receive their teaching blocks, not the overnight hours.

**Confirmation:** Only the confirmed sheet creates training time — a registration is never a record. Confirmation freezes the state as a version; later corrections need a reason and are recorded with actor and time, the snapshot remains. A lock counter prevents two people from silently overwriting each other: a form with an older revision is rejected.

**Overlaps:** If two confirmed records of the same member overlap in time, the system flags both as an overlap. Until resolved with a reason, no time is credited — there is no double crediting.

**Record list and export:** Under "Records" you see all confirmed records in the header period, filtered by group or member, and export them as CSV. Club attendance is not working time: no bookings arise in working time accounts.

**Who may do what:** Group leaders record and confirm the sheets of their target groups, the club administration all of them. Members will see their own records under "My club" in the future.
