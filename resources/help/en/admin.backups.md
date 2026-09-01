---
title: "Backups & operations monitoring"
topic: admin.backups
version: 3
audience:
    - admin
schema: process
related:
    - admin.handbook
    - admin.security
    - admin.import
---

## Purpose and background

WorkDiary monitors external backups via a **heartbeat**: the backup
job reports success to the platform after every run. Backups are not
registered manually — with the first heartbeat the source appears
automatically on **Backup & Restore**. The actual backup and restore
deliberately run outside WorkDiary.

## Requirements

- An external backup job (e.g. the bundled `backup.sh`).
- The heartbeat token in the environment variable
  `BACKUP_HEARTBEAT_TOKEN` — without it the endpoint is disabled.
- Administration rights for the **Backup & Restore** page.

## Recommended workflow

1. Set up the backup job and let it send the heartbeat:
   `POST /admin/backup/heartbeat` with bearer token (outside the
   normal login stack, throttled); transmitted are `manifest_sha256`,
   `size_bytes`, `source` and `occurred_at`.
2. Check the source on **Backup & Restore**: the page shows the last
   backup per source and marks it **overdue** when the latest
   heartbeat is older than the configured freshness
   (`BACKUP_HEARTBEAT_FRESHNESS_HOURS`, default 26 h).
3. Run restore tests regularly on a separate environment and record
   them via **Log restore test**.
4. Check system health automatically: `php artisan system:health`
   tests database, migrations, storage, queue, APP_KEY, mail and
   licence (exit code 0/1, changes no data — ideal for cron/CI).

## Practical example

The nightly cron backs up at 11 pm and reports the heartbeat. When a
storage rebuild stops the job for two nights, the source jumps to
"overdue" — the admin sees it on the dashboard in the morning, before
data loss looms.

## Common mistakes

- **Backing up only the database:** without the **APP_KEY**,
  encrypted fields (PII, 2FA, data protection cases) are
  irretrievably lost.
- **Never testing restores:** a backup without a verified restore is
  a hope, not a concept.
- **Confusing heartbeat with backup:** the heartbeat only reports
  success — it replaces neither backup nor retention.

## Effects and next steps

Every heartbeat is stored and logged as the audit event
`backup.heartbeatReceived`; overdue sources appear in operations
monitoring. Next: schedule a restore test, add `system:health` to
cron and read the disaster recovery notes in the admin handbook.
