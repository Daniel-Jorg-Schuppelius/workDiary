---
title: "Access media"
topic: access.media
version: 1
audience: []
related:
    - assets.fleet
---

**Transponders, cards and codes** as a managed inventory — the extension of
the physical key handover. Every medium has **exactly one status** at all
times (In stock / Issued / Lost / Blocked / Retired) and a documented
whereabouts.

## Principles

- **The medium number is stored only as a hash** — the last four digits stay
  visible. The plain text is known only at creation.
- **The holder is a user OR an external person** (name + company) — a
  cleaning service has no employee account.
- **workDiary does not control any access system.** The administrative state
  here and the system state there are held together by the blocking task.

## Loss and blocking

A loss report sets the status to **Lost** and mandatorily creates a
**blocking task** (“Block medium …1234 in system X”, due in two days). Only
whoever has performed the blocking in the system confirms it — then the
medium becomes **Blocked** and the task done. Lost and blocked are
deliberately separate states: exactly this gap is meant to be visible,
because in it the medium is a risk.

## Issue and return

Every handover (issue/return) lands in the medium's **history** — with
holder, time, expected return and condition. An issued medium cannot be
retired — take it back first.
