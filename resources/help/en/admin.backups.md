---
title: "Backups & operations monitoring"
topic: admin.backups
version: 1
audience:
    - admin
related:
    - admin.handbook
    - admin.security
---

WorkDiary monitors external backups via a **heartbeat**: your backup
job reports success to the platform after every run.

How the heartbeat works:

- Endpoint: `POST /admin/backup/heartbeat`, authenticated via bearer
  token (outside the normal login stack, rate-limited).
- Transmitted are, among others, the **manifest hash (SHA-256)**,
  **size**, source and time.
- Every receipt is stored and logged as the audit event
  `backup.heartbeatReceived`.

Current state (honest): there is **no monitoring UI yet** – control
runs via the heartbeat table and the audit trail. Therefore set up
external alerting for missing heartbeats.

System health: the command `php artisan system:health` checks the
database connection, migrations, storage (read/write test), queue,
APP_KEY, mail and license – exit code 0 when healthy, 1 on errors.
It changes no data and is suitable for cron/CI.

Important for restores:

- Besides the database, always back up the **APP_KEY** – without it,
  encrypted fields (PII, 2FA, data protection cases) are lost.
- Test restores regularly on a separate environment.
