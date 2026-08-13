---
title: "Change history & version comparison"
topic: admin.audit-diff
version: 1
audience: [admin]
related:
    - audit.log
---

The change history makes the audit-proof hash chain readable: for a
selected record (member, customer, work schedule, shift type, time
account, organisation) the timeline shows all recorded changes with
timestamp, event and user.

Select two states (A = older, B = newer) and compare: the diff table
shows per field the value before state A and after state B — clarifying
in seconds since when a value has been set and who changed it.

Sensitive fields (passwords, secrets, tax and social security numbers)
are shown masked. The comparison is deliberately display-only:
corrections remain proper, audited operations — there is no automatic
rollback.
