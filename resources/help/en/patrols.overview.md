---
title: "Guard patrols"
topic: patrols.overview
version: 2
keywords:
    - guard tour
    - security patrol
    - checkpoints
    - scan checkpoint
    - patrol log
    - proof of patrol
    - guard tour system
    - security service
    - QR checkpoint
    - missed checkpoint
    - abort patrol
audience: []
modules:
    - module.planung
related:
    - dispatch.overview
---

A **patrol** is an ordered list of **checkpoints** with target windows
relative to the start (“point 3: +20 min ± 10”). The **scan proves point and
time** — the reliable evidence towards clients (guarding, facility, winter
service).

## Tokens

Every checkpoint gets a **token** (printed on the tag/as QR). Only the hash
is stored; the plain text appears exactly once — at creation. **A lost tag**
is replaced via “reissue token”: new token, same route, the old one is
immediately worthless.

## Execution

Start patrol → scan tokens (camera scanner types as keyboard, or enter by
hand) → complete. At most one patrol runs per route at a time; double scans
count once.

## Aborting

A running patrol can be **aborted** — only with a **justification**. It then
does not count as completed; confirmed checkpoints remain as evidence, and
the report states the abort with reason, person and time. Open checkpoints go
to the control centre as an **open issue**, just like a deviation. After
that the route is free again.

## Deviations

Missed points or scans outside the window are **shown, never smoothed** — and
completion then requires a **justification**. Additionally an **open issue**
is raised for the control centre (due the next day) — escalation runs through
the existing system, no separate channel.

Target times are **evidence, not a performance-pressure metric**: there is
deliberately no position data on the scan and no person-based long-term
evaluation.

## Maintaining routes and checkpoints

**Edit** changes the name, the site and the **responsible person** of the
route. A route can be **deactivated** — a running patrol may finish, new ones
cannot be started. Checkpoints can be edited (location, target, tolerance;
recorded scans keep their rating) and **deactivated**: from then on they are no
longer expected and can no longer be scanned. Earlier patrols stay unchanged:
a run that started before the deactivation still expects the checkpoint.

## Escalation

The open item is created **immediately on the deviation** — with the first scan
outside the window or as soon as a due checkpoint has been skipped — and goes
to the person responsible for the route, or without one to the team lead of the
person doing the patrol. There is one open item per run; it is updated until
the end. If a patrol is still running after the planned duration plus the
tolerance of the last checkpoint, the deadline run reports it once as **not
finished**; the control centre can then abort it with a reason.
