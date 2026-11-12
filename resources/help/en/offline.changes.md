---
title: "Offline changes"
topic: offline.changes
version: 2
keywords:
    - work offline
    - no internet
    - no signal
    - sync queue
    - synchronization
    - pending changes
    - rejected entries
    - offline mode
    - not synced
    - clock in offline
audience: []
related:
    - attendance.manage
    - forms.fill
---

Actions captured without a network connection (clockings, order comments, forms) are queued on this device and transferred automatically once a connection is available.

**Pending:** Entries not yet transferred. They are sent on the next online contact; "Discard" removes them beforehand.

**Rejected:** Entries the server did not accept (e.g. missing required field or already clocked in) — with a reason. "Apply again" re-queues the entry.

**Important:** The list is stored on this device only and is cleared on logout. Files and signatures cannot be captured offline.

## What works without a connection

Besides clockings, order comments and forms you can also capture **project time** offline via the entry bar on "Today" (duration or from/to — not the timer), **material** via "Material" on "Today" and **corrections of your clockings** via the pencil icon in the clocking list on "Today". These dialogs are part of the page, so they open without a network as long as "Today" was loaded before.

## Deciding conflicts

A conflict means the entry was **not** taken over because something changed since you captured it or a required field is missing — for instance a clocking changed meanwhile, a closed day or a booking without a project. You decide:

- **Keep the other version** or **Discard** — your version is dropped.
- **Send my version** — only for a changed clocking: your correction is applied to the new state.
- **Edit** — adjust fields (such as date, duration or reason) and send them as a new entry.

Every decision is recorded in the audit log. The page also shows open conflicts that arose on another device; their contents are stored there, so here they can only be discarded.
