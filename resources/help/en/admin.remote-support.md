---
title: "Remote Support"
topic: admin.remote-support
version: 5
keywords:
    - AnyDesk
    - TeamViewer
    - RustDesk
    - Splashtop
    - pcvisit
    - API token
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

Remote maintenance takes over session reports from AnyDesk, TeamViewer,
RustDesk, Splashtop and pcvisit and converts them into time entries.
Sessions are assigned to a device (asset, e.g. workstation, server,
notebook) via the provider's device ID. With **Import sessions** you can
also read in session exports (AnyDesk, RustDesk Server Pro) through the
central import; the header row reveals the provider, a **Provider**
column overrides it.

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

Setting up providers (plugin settings of the organization, one switch
per provider):

- **AnyDesk**: license ID and API password of the license; the device ID
  is the AnyDesk ID of the computer.
- **TeamViewer**: script token with permission for connection reports;
  the device ID is the TeamViewer ID.
- **RustDesk**: the free RustDesk server does not store connections.
  Sessions come only from an API server that the clients have entered
  under “API server”. For **Server Pro** enter the address of the web
  console and an API token (Settings → Tokens → Create, permission “Audit
  Log”); for the **open-source API server** (rustdesk-api) the address
  including the port plus username and password of an administrator, the
  server time zone only if the server does not run in the organization's
  zone. If the server runs on your own network, switch on **Allow private
  network addresses**. The device ID is the RustDesk ID of the computer
  (without spaces). Connections still running are taken over after they
  end.
- **Splashtop**: API token from the web console (account settings → Web
  API Tokens; team owners and super admins only, the Open API is part of
  Splashtop Enterprise). Choose the region of your account; the team ID
  is determined from the token. Session kinds are selectable as a
  comma-separated list (default: unattended and SOS). The device ID is
  the computer UUID from Splashtop; the inbox shows the computer name
  next to it. Other plans use the CSV export of the Logs page through the
  central import.
- **pcvisit**: user API token from the pcvisit web app (account settings)
  and an integrator token that pcvisit issues to the software vendor
  (operator setting; enter it per organization only with your own
  agreement). Finished sessions are taken over with the duration recorded
  in pcvisit; the device ID is the computer ID from pcvisit, the end
  customer is in the note.

Connections are combined into one job: if a connection to the same
device starts within **Combine connections up to (minutes)** (default
30) after the previous one ended, it extends that time entry – so
reconnects and the waiting time during an update do not become a
series of separate bookings. The note of the follow-up connection is
added to the description; if it names another project by keyword, a
separate entry is created. Exported entries and closed months are
never extended. A connection shorter than one minute is booked as one
minute; the actual duration stays with the session record. 0 switches
combining off.

If one provider fails, the others keep running; the message appears in
the plugin status and in the import result.

Security and risks:

- The providers' API credentials are stored in the organization's
  plugin settings. The system reads session reports – it does not grant
  direct remote access.
- Multi-customer devices require careful per-session assignment to
  avoid cross-customer misbookings.
- **Dismissed connections and sessions are not booked**; the page
  offers no way to bring them back.

Permission: the page is reserved for administrators.
