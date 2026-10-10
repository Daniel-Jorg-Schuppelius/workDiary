---
title: "CalDAV calendar"
topic: admin.caldav
version: 3
keywords:
    - CalDAV
    - Nextcloud calendar
    - ownCloud calendar
    - publish appointments
    - subscribe to calendar
    - roster in calendar
    - leave in calendar
    - two-way sync
    - app password
    - calendar path
    - private addresses
audience:
    - admin
related:
    - admin.integrations
    - admin.plugins
    - admin.integration-inbox
    - events.manage
    - planning.shifts
    - absences.manage
    - admin.import
    - admin.notification-rules
---

The **CalDAV** page publishes appointments from WorkDiary to an external
CalDAV calendar, for example in Nextcloud or ownCloud – without a Microsoft or
Google account. If you wish, rosters and leave are added, and changes from the
calendar can be brought back as proposals. WorkDiary remains the leading
system: cancelled and deleted appointments disappear there, and repeated runs never create
duplicates. You find the page in the system menu (the **System** gear in the
header) under **Plugins** → **CalDAV** once the plugin is active.

## Prerequisites

- The plugin is activated for your organization: **System** → **Plugins** →
  **Plugins**, then **Activate** on the CalDAV entry. You set up the
  connection itself on the **CalDAV** page, not in the plugin dialog.
- The page is open to administrators.
- You need a calendar on the CalDAV server, an account with write access to
  it and an app password (Nextcloud: Settings → Security → App password).
- The server must be publicly reachable. If it runs on your own network,
  switch on **Allow private/internal addresses** (see below).
- There is exactly one CalDAV connection per organization.

## Setting up the connection

In the **Connection** section you fill in:

- **Label**: a name of your choice; it also appears when you choose an
  import source.
- **DAV base URL**: the DAV address of the server without the calendar path,
  for Nextcloud …/remote.php/dav. It must start with http:// or https://.
- **Username** and **App password**: the password is required the first time
  you save and is stored encrypted; later, an empty field keeps the stored
  password.
- **Calendar path (collection)**: the path of the calendar relative to the
  base URL, for example calendars/team/roster. If you paste a full address
  copied with “Copy link” in Nextcloud, WorkDiary shortens it itself as long as
  it starts with the base URL.
- **Allow private/internal addresses**: switch it on only if the CalDAV
  server runs on your own network (for example 192.168.x.x). Without this
  switch WorkDiary rejects internal addresses as soon as you save. Switching
  it on is audited. If the operator of your installation has blocked this
  approval, the switch has no effect.
- **Active**: switches the connection on or off.
- **Two-way: import external changes as inbox proposals**: see below.
- **Published content**: **Events** and/or **Rosters & leave**. Without a
  selection, only events apply.

**Save** applies your entries. When the connection is active, the page shows
its health (for example **Health ok**) and **Test connection**.

## What is published

- **Events:** the events of your organization that start between 30 days in
  the past and 180 days in the future. WorkDiary removes cancelled and
  deleted events from the calendar.
- **Rosters & leave:** published or confirmed shifts with times from two
  months in the past onwards, and approved leave that ended no more than a
  year ago. Draft shifts, shifts without times, cancelled shifts and leave
  that is no longer approved are removed or never created.
- Notification rules with the **Calendar** channel also place
  appointment-like notifications in connections with **Events**.
- Changed entries are updated; unchanged ones are not sent again.

## When publishing runs

- Once a day (default 04:35) WorkDiary synchronises the calendar. The interval
  can be changed under **Scheduled tasks**.
- **Publish now** starts the synchronisation right away in the background –
  for example after the setup or after many changes.
- New or changed appointments therefore appear in the calendar only after the
  next run. Only notifications through the **Calendar** channel go out
  immediately.

## Two-way: changes from the calendar

The return import stays off until you switch on **Two-way: import external
changes as inbox proposals**. Then WorkDiary reads the calendar every hour,
in a window from 30 days back to 180 days ahead:

- New entries in the calendar become proposals in the Mapping Inbox. Nothing
  is created without asking.
- Recurring appointments appear as a group with their individual occurrences
  within the window; WorkDiary takes moved and cancelled occurrences into
  account. The group can be created as appointments or discarded in one go.
- External changes to published appointments appear as a conflict, entries
  deleted in the calendar as an “Appointment deleted in the CalDAV calendar”
  case. WorkDiary does not delete anything itself in doing so.

The Mapping Inbox is open to administrators and accounting.

## Calendar as an import source

In the CSV import (**Data transfer** → **Import**) you can choose the CalDAV
calendar instead of a file as the source for clock-ins and project times.
Active connections are offered with their **Label**; WorkDiary then reads the
entries of the chosen period.

## Disconnecting

**Disconnect** switches the connection off. Entries already published stay in
the calendar. To switch it on again, set **Active** and save.

The next sync removes deleted events, shifts and leave from the calendar,
just like cancelled ones. Entries that merely drop out of the time window stay
there.

## Common errors

- “The base URL must start with http:// or https://.”: enter the full
  address.
- “The calendar URL is not below the base URL.”: the pasted link does not
  match the DAV base URL. Enter the path relative to the base URL.
- “A new connection requires an app password.”: the password is missing the
  first time you save.
- **Health failing** with “CalDAV server unreachable or credentials
  invalid.”: check the address, calendar path, username and app password. A
  CalDAV error with RuntimeException often points to an address on an
  internal network without approval.
- “The base URL points to a private/internal address.”: if the server runs on
  your own network, switch on **Allow private/internal addresses**. If the
  operator has blocked this approval, the server needs a publicly reachable
  address.
- “No active CalDAV connection.” on **Publish now**: the connection is off or
  incomplete.
- Rosters are missing in the calendar: **Rosters & leave** is not ticked under
  **Published content**, or the shifts have not been published yet.
