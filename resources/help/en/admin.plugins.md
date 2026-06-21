---
title: "Plugins"
topic: admin.plugins
version: 1
audience:
    - admin
related:
    - admin.handbook
    - admin.toggl
    - admin.openproject
    - admin.lexoffice
    - admin.remote-support
---

Here you manage the installed plugins and integrations. Plugins
extend WorkDiary with external connections (e.g. Toggl, OpenProject,
Lexoffice, Remote Support).

Important: plugins are controlled **per organization**. Activation,
settings, health status and errors apply to your organization only –
the same plugin may be in a completely different state in another
organization.

Overview (list):

- **Status**: active, inactive or auto-disabled.
- **Health**: ok / degraded / failing, with the time of the last
  check.
- **Per-plugin actions**: configure, enable/disable, run a health
  check immediately, and (if auto-disabled) reset and reactivate.

Configuring (edit):

- Per-plugin settings (e.g. API token, endpoints). Passwords/tokens:
  an empty field keeps the existing value unchanged.
- **Test connection** triggers a health check without saving.

Health check and auto-disable:

- The health check verifies reachability/function and records the
  result per organization. It runs manually or on a schedule (cron).
- If errors recur and reach the threshold, the plugin is
  **automatically disabled** – for the affected organization only.
  Other organizations remain unaffected.
- After fixing the cause you reset the failure counter and
  reactivate the plugin.

Error log (plugin errors):

- List of all recorded errors with timestamp, plugin, phase
  (boot/runtime/health check), exception class and message.
- Filter by plugin, phase and status (open/acknowledged).
- The detail view shows the full message, context and stack trace.
- Errors can be marked as **acknowledged** (with operator and
  timestamp); they are retained for traceability.

Permissions: these areas are reserved for administrators and require
an organization context.

Risks: a disabled plugin stops its synchronization – imports/exports
and health checks pause until it is reactivated. Check the health
status after every configuration change.
