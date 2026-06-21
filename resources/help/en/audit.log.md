---
title: "Audit log"
topic: audit.log
version: 1
audience:
    - admin
related:
    - admin.security
    - admin.handbook
    - privacy.overview
---

The audit log (`/audit`) is the tamper-evident record of changes and
actions in the system. Entries are **append-only** and chained
together via a **SHA-256 hash chain** (GoBD); they are never written
raw and cannot be altered or deleted afterwards.

**Filters**: The list can be narrowed by

- **action** (e.g. created, updated, deleted, archived, restored, and
  import events),
- **type** of the affected object (e.g. diary entry, comment,
  customer, supplier, import run, number sequence),
- **user**, and
- **time range** (via the global date filter).

Each entry shows the timestamp, the triggering user, the action, the
object, the concrete changes, and the IP address.

**Verify integrity**: The hash chain is checked via the console
command `php artisan audit:verify`. It validates the chaining and
exits with code 1 on a break – ideal for cron/CI. Keep the command
green at all times; a break indicates manipulation or a data error.
With `--chain` you can check a single chain specifically (`audit_logs`
or `organization_audit_logs`).

Note: The audit log is a read-only tool. It displays events but does
not change any data itself.
