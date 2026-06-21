---
title: "Metrics"
topic: admin.metrics
version: 1
audience:
    - admin
related:
    - admin.diagnostics
    - admin.handbook
    - admin.backups
---

The metrics page shows read-only operational and performance metrics
for monitoring the system. It complements diagnostics, which provides
the traffic-light status of the health checks.

Collected metrics include:

- **Version** of the application
- **Queue**: pending and failed jobs
- **Backups**: most recent backups (timestamp, size, source)
- **Plugin errors**: count and recent incidents
- **Storage**: storage usage (e.g. attachments, document versions)
- **Active users**
- **Module counts**: record counts per module (e.g. diary,
  documents)
- **Feature usage**: aggregated usage of individual features

Values are collected fresh on each request; individual areas fall back
to empty defaults when unavailable, without blocking the page.

Access requires the metrics permission. Detailed health checks and the
test mail are available under **Diagnostics**.
