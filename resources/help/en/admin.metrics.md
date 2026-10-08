---
title: "Metrics"
topic: admin.metrics
version: 3
keywords:
    - KPIs
    - statistics
    - monitoring
    - system usage
    - storage usage
    - disk space
    - active users
    - failed jobs
    - queue
    - usage statistics
    - performance
audience:
    - admin
related:
    - admin.diagnostics
    - admin.handbook
    - admin.backups
---

The **Operations metrics** page shows read-only operational and
performance metrics for monitoring the system. It complements
diagnostics, which provides the traffic-light status of the health
checks. All metrics are collected and stored locally only; nothing is
sent to external systems.

The page is divided into the following areas:

- **Version** of the application (in the page header)
- **Queue**: **Pending jobs** and **Failed jobs**
- **Backup heartbeats**: most recently reported backups (timestamp,
  size, source)
- **Plugin errors (7 days)**: count and recent incidents
- **Storage**: number and size of **Attachments** and **Document
  versions** according to database metadata (disk usage is shown by
  diagnostics)
- **Active users (30 days)**: distinct users with a login according to
  the audit log
- **Records per core module**: record counts e.g. for **Jobs (diary)**,
  **Documents**, **Protocols** and **Knowledge articles**
- **Feature usage (30 days)**: **Count** and **Last used** per feature,
  aggregated per organization and day
- **Metrics transparency**: which usage counters are collected and
  whether they are currently active

Values are collected fresh on each request; individual areas fall back
to empty defaults when unavailable, without blocking the page.

Access requires the **View operations metrics** permission. Detailed
health checks and the test mail are available under **Diagnostics**.
