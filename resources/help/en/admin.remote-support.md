---
title: "Remote Support"
topic: admin.remote-support
version: 3
keywords:
    - AnyDesk
    - TeamViewer
    - remote access
    - remote session
    - remote maintenance
    - session reports
    - device ID
    - remote desktop
    - support session
    - log remote sessions as time
audience:
    - admin
related:
    - admin.support
    - admin.plugins
    - assets.fleet
---

Remote maintenance takes over session reports from AnyDesk and
TeamViewer and converts them into time entries. Sessions are assigned
to a device (asset, e.g. workstation, server, notebook) via the device
ID (AnyDesk/TeamViewer ID). With **Import sessions** you can also read
in AnyDesk sessions through the central import.

The **Remote maintenance – unassigned connections** page has two tabs;
the search field finds device ID, alias, device or note.

**Unassigned devices** tab:

- IDs that appear in the reports but are not yet assigned to any of the
  organization's devices collect here – with number of sessions,
  duration and period.
- If there is a **Suggestion** (matching customer or device), you adopt
  it with **Apply**.
- **Existing device**: pick an existing device under **Select device**
  and click **Assign**; the stored sessions are booked as time entries
  immediately.
- **New device**: enter **Name**, **Category**, **Customer** and
  optionally **End customer**, then click **Create & assign**.
- **Multi-customer device**: this checkbox in both tabs marks a device
  used for several customers. Its sessions are then not booked
  automatically but per customer in the second tab.
- **Dismiss**: rejects all connections of an ID; they are not booked.

**Assign sessions** tab (multi-customer devices):

- Select sessions, choose **Customer**, optionally **End customer** and
  **Project**, and click **Post selected** – this books the time to the
  right customer.
- **Book selected internally** books sessions without a customer to the
  internal maintenance project.
- **Discard selected** discards individual sessions.

Security and risks:

- The providers' API credentials are stored in the organization's
  plugin settings. The system reads session reports – it does not grant
  direct remote access.
- Multi-customer devices require careful per-session assignment to
  avoid cross-customer misbookings.
- **Dismissed connections and sessions are not booked**; the page
  offers no way to bring them back.

Permission: the page is reserved for administrators.
