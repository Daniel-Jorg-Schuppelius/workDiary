---
title: "Diagnostics"
topic: admin.diagnostics
version: 1
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
