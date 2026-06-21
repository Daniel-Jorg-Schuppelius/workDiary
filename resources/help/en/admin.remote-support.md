---
title: "Remote Support"
topic: admin.remote-support
version: 1
audience:
    - admin
related:
    - admin.support
    - admin.plugins
    - assets.fleet
---

Remote support imports session reports from AnyDesk and TeamViewer
and turns them into time entries. Sessions are matched to an asset
(workstation, server, notebook) via the device ID (AnyDesk/
TeamViewer ID).

Inbox (pending requests):

- This is where sessions collect whose device ID is not yet linked
  to any asset in the organization. They await a decision.
- Entries are grouped by provider and device ID (with count,
  duration and date range).

Actions:

- **Assign to existing device**: links the device ID to an existing
  asset; pending sessions are booked immediately as time entries.
- **Create new device**: creates a new asset (category, customer)
  and assigns the device ID in one step.
- **Assign sessions (shared device)**: for devices used by several
  customers, individual sessions can be routed to a specific
  customer/project – preventing misbilling.
- **Dismiss**: rejects an entire device-ID group; the sessions are
  not booked.
- **Dismiss single session**: discards selected sessions of a shared
  device.

Security and risks:

- The providers' API credentials live in the organization's plugin
  settings. The system reads session reports – it does not grant
  direct remote access.
- Shared devices require careful per-session assignment to avoid
  cross-customer misbilling.
- **Dismissed sessions are permanently removed** and are not
  converted into time entries – they cannot be recovered.

Permissions: the inbox is reserved for administrators.
