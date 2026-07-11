---
title: "Operations tasks & maintenance windows"
topic: admin.operations
version: 1
audience:
    - admin
related:
    - admin.backups
    - admin.diagnostics
---

WorkDiary supports day-to-day operations with two tools: the **task
center** for recurring operational checks and the planning of
**maintenance windows**.

**Operational checks as a scan:** A recurring scan (hourly by
default) checks operationally relevant items – such as expiring
certificates, missing backups, and expiry dates of licenses and
credentials. Detected items appear as prioritized tasks in the task
center, sorted by severity (critical before warning before notice)
and filterable by status, type and severity.

**Working through tasks:** You can mark each task as **done**,
**snooze** it (for a configurable number of days), **delegate** it
(to a person in your organization) or **ignore** it (with a mandatory
note). Completed tasks can be reopened. Every status change is
recorded in the audit trail. Visibility is bound to the organization;
installation-wide tasks live in the operator organization and are
marked accordingly.

**Planning maintenance windows:** You announce a maintenance window
with a start, an end and an optional lead time – from the
announcement date onwards, users see a banner with your message. A
window applies either **system-wide** or only to the current
**organization**.

**Lifecycle of a window:** After planning, a window goes through the
steps announce, start, extend if needed, and complete. While a window
is active you can optionally enable **read-only mode** (users see
data but cannot change anything) and **block data ingest** (external
deliveries are held back). If something goes wrong, a **rollback**
documents the abort including notes; planned windows can also be
cancelled entirely. Every action is audited.

**Recommendation:** Plan maintenance windows with enough lead time so
the announcement can take effect, and work through the task center
regularly – critical tasks first. Snoozing is meant for deliberate
postponements, not as a permanent state.
