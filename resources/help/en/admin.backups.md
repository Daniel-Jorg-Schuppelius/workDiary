---
title: "Backups & operations monitoring"
topic: admin.backups
version: 2
audience:
    - admin
related:
    - admin.handbook
    - admin.security
---

WorkDiary monitors external backups via a **heartbeat**: your backup
job reports success to the platform after every run. Backups are not
registered manually in the UI — as soon as the first heartbeat
arrives, the source automatically appears on the **Backup & Restore**
page.

How the heartbeat works:

- Endpoint: `POST /admin/backup/heartbeat`, authenticated via bearer
  token (outside the normal login stack, rate-limited).
- The token is set via the environment variable
  `BACKUP_HEARTBEAT_TOKEN`; without a token the endpoint is disabled.
- Transmitted are `manifest_sha256` (SHA-256), `size_bytes`, `source`
  and `occurred_at`.
- Every receipt is stored and logged as the audit event
  `backup.heartbeatReceived`.

The **Backup & Restore** page shows the latest backup per source and
marks it as overdue when the most recent heartbeat is older than the
configured freshness (`BACKUP_HEARTBEAT_FRESHNESS_HOURS`, default
26 h). Restore tests are logged there via **Log restore test**; the
actual restore deliberately happens outside of WorkDiary.

System health: the command `php artisan system:health` checks the
database connection, migrations, storage (read/write test), queue,
APP_KEY, mail and license – exit code 0 when healthy, 1 on errors.
It changes no data and is suitable for cron/CI.

Important for restores:

- Besides the database, always back up the **APP_KEY** – without it,
  encrypted fields (PII, 2FA, data protection cases) are lost.
- Test restores regularly on a separate environment.
