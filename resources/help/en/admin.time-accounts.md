---
title: "Time accounts (administration)"
topic: admin.time-accounts
version: 1
audience: [admin]
related:
    - time-accounts.overview
---

Additional time accounts turn existing time data into managed accounts:
night-shift counters, time-off savings accounts, allowance collectors.
Flex time and vacation remain separate accounts and are not duplicated
here.

Per account you define the unit (minutes, days, count), optional traffic
light thresholds and the carryover policy — cumulative or capped at the
monthly close. Posting rules declaratively define the source: wage type
patterns from the time rule engine, net attendance, absence days, a shift
counter per shift type or quantities from imported external items; a
factor weights the posting (e.g. 1.25 for "a night hour counts 1:1.25").

The daily run posts idempotently; the journal is immutable — corrections
are reversal entries, manual special postings require a reason and are
audited. Optionally the balance appears in the terminal status response.
