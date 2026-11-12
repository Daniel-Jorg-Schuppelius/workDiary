---
title: "Diagnostics"
topic: admin.diagnostics
version: 2
keywords:
    - system status
    - health check
    - system check
    - troubleshooting
    - test email
    - check mail settings
    - queue
    - scheduler
    - cron job
    - status report
audience:
    - admin
related:
    - admin.security
    - admin.metrics
    - admin.handbook
---

Diagnostics provides a system health report with a traffic-light
status per check area. It helps detect configuration and operational
issues early.

Checked areas include:

- **Version** of the application
- **License**
- **Queue** (background jobs)
- **Scheduler** (scheduled tasks)
- **Mail** (delivery configuration)
- **Storage**
- **Backup**

Each area is assigned a status (OK, warning, critical or unknown).
The report is also available as JSON for machine processing.

In addition, a **test mail** can be triggered to your own email
address to check the mail configuration. The result is reported back.

Diagnostics views and triggered tests are recorded in the audit log.
Viewing requires the diagnostics permission; running checks requires a
separate permission. Operational metrics are available under
**Metrics**.

**Monitoring access:** For external monitoring, create an access under “Monitoring access”. The monitoring system fetches the report without signing in at /api/monitoring/diagnostics and sends the token in the header “Authorization: Bearer …”. The token is shown only once and stored only as a fingerprint; revoking blocks it immediately. The report contains no names or addresses, such as the licensee or the sender address. Requests are logged at most once per hour and access.
