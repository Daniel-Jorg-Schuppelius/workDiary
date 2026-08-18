---
title: "Offline synchronisation"
topic: admin.offline-sync
version: 1
audience: []
related:
    - admin.metrics
---

Whoever works on the road without a network records into a **device outbox**;
once the connection returns, the device transmits the commands. This page
shows **every transmitted command with its result** — the answer to which data
was created offline and whether it arrived.

## The four results

- **Applied** — the command is in the records. The normal case.
- **Duplicate** — the same device sent the same command twice (typically after
  a dropped connection mid-transfer). Not an error: the command was applied
  the first time, the repetition recognised and discarded.
- **Conflict** — the records changed in the meantime; the command was **not**
  applied.
- **Rejected** — the command was invalid (say, a clocking command in an
  impermissible state); the error column names the reason.

**Conflict and Rejected are the reason this page exists:** those recordings
did *not* reach the records. The counters in the result filter always count
the full stock — a set filter does not hide them.

## The two timestamps

**Captured (offline)** is the device time of recording, **Transmitted** the
arrival on the server. The span between them is the offline latency — a day
is normal for field work, a week is a hint that a device does not synchronise.
