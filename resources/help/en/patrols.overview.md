---
title: "Guard patrols"
topic: patrols.overview
version: 1
audience: []
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

## Deviations

Missed points or scans outside the window are **shown, never smoothed** — and
completion then requires a **justification**. Additionally an **open issue**
is raised for the control centre (due the next day) — escalation runs through
the existing system, no separate channel.

Target times are **evidence, not a performance-pressure metric**: there is
deliberately no position data on the scan and no person-based long-term
evaluation.
