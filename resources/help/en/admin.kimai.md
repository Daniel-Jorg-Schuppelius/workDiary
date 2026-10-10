---
title: "Kimai import"
topic: admin.kimai
version: 3
keywords:
    - Kimai
    - import time entries
    - import timesheets
    - Kimai CSV
    - Kimai API
    - switch from Kimai
    - write times back
    - write-back
    - user mapping
    - write corrections back
    - hourly import
    - self-hosted Kimai
    - allow private addresses
audience:
    - admin
related:
    - admin.integrations
    - admin.plugins
    - admin.integration-inbox
    - admin.clockify
    - admin.toggl
    - finance.open-times
    - admin.organization-settings
    - admin.scheduler
---

The **Kimai import** page brings time entries from the Kimai time tracker
into WorkDiary – as an uploaded CSV export or directly through the Kimai API.
If you wish, it also writes times recorded in WorkDiary back to Kimai as
timesheets and sends corrections to imported times back to Kimai. You find the
page in the system menu (the **System** gear in the header) under **Plugins**
→ **Kimai import** once the plugin is active.

## Prerequisites

- The plugin is activated for your organization: **System** → **Plugins** →
  **Plugins**, then **Activate** on the Kimai entry. Activation and settings
  apply to the current organization only.
- The page and the settings are open to administrators. The **Mapping
  Inbox**, where you resolve open cases, is also open to accounting.
- For the CSV route, a timesheet export from Kimai is all you need.
- For the API route you need the address of a Kimai 2 instance and the API
  token of a Kimai user (in Kimai under Profile → API access). If the times
  of all people should come across, that user needs the view_other_timesheet
  permission in Kimai.
- The Kimai instance must be publicly reachable, or you allow a self-hosted
  instance on your own network with **Allow private addresses** (see Setup).

## Setup

You store the credentials on the **Plugins** page via **Configure** on the
Kimai entry:

1. **Kimai base URL**: the address at which you open Kimai in the browser –
   without /api at the end.
2. **Allow private addresses**: only for a self-hosted instance on your own
   network (for example 192.168.x.x). Without this switch WorkDiary rejects
   internal addresses. The change is audited. If the operator of your
   installation has blocked this approval, the switch has no effect.
3. **Kimai API token**: the token from Kimai. It is stored encrypted; an
   empty field keeps the previous value when you save.
4. **Retrieve times of all users**: on (default) if the token user may read
   other people's times; otherwise only that user's own times arrive.
5. **Sync window (days)**: how far back an API import without a period looks
   (default 30 days), the hourly one included.
6. **Adopt billable flag**: on takes over the billable flag from Kimai; off
   never marks imported times as billable.
7. **Single-user mode** and **Book times for user**: for single
   workstations only, see below. You choose the user from the list.
8. For write-back, **Enable write-back**, **Kimai activity ID for
   write-backs** and optionally **Instant push-back of new times**; for the
   return path of corrections, **Write corrections back**.
9. **Save**. Use **Test connection** in the dialog to check the access.
   Without a token the plugin reports CSV mode – that is not an error.

## Importing times

**Upload CSV:** Export the times from Kimai as CSV (Kimai → Times → Export →
CSV), select the file on the **Kimai import** page and click **Import**.
WorkDiary recognises the columns by the header row, in German or English –
for example date, from, to or duration, customer, project, activity,
description, billable, tags and email. Both comma and semicolon work as
separators, and the file may be at most 20 MB. The times are read as local
time of your organization.

**Import directly from the Kimai API:** Optionally choose a period (**From**,
**To**) and click **Import from API**. Without a period WorkDiary queries the
last days according to the sync window. The import skips running timesheets
that have no end yet.

**Hourly import:** Once the base URL and API token are stored, the API
import also runs by itself every hour – over the sync window and with
deletion matching (see below). You change the interval under **Scheduled
tasks** on the “Kimai import” entry. Without API access there is no automatic
import; you always upload CSV files here.

After an import on this page, the page reports how many entries were created, skipped and left
open in the inbox, and how many could not be matched to a user.

## Matching customers, projects and people

- **Projects:** The import does not create customers or projects. An entry is
  booked when its project is found: through a remembered mapping, in the API
  import through the project number from Kimai, otherwise through the same
  project name at the matching customer. If **Assign time to projects by
  keyword** is switched on in the organization settings, an unambiguous
  keyword match helps as a last step.
- **Mapping Inbox:** Everything else collects there, grouped by customer,
  project and activity. The **Mapping Inbox** card on the page shows the
  number of open groups, and **To the inbox** takes you there. Choose the
  customer, optionally the end customer, and the project, then book the
  group. The mapping is remembered, so later imports book without asking.
- **People:** Every time belongs to the person who recorded it in Kimai. The
  CSV import uses the email column, the API import the email address of the
  Kimai user (if it is missing, the user name). WorkDiary compares either with
  the email address of the active users; a choice remembered in the inbox for
  a user name still applies.
  Without a match, an “Unknown user” or “Entry without user signal” case is
  created in the inbox instead of the time silently landing with the main
  user. Choose the user there; the choice is remembered.
- **Single-user mode:** Only when it is switched on does the import book
  entries without an identifiable person to the default user. That is the
  user from **Book times for user**, otherwise the owner of the
  organization or the first user.

## Re-importing and changes

- A repeated import never creates already imported entries twice.
- In the API import WorkDiary recognises each entry by its Kimai number. If a
  known entry changed in Kimai (start, end, duration, description), WorkDiary
  takes over the change. If the time has already been billed or exported
  here, WorkDiary changes nothing; the case appears in the inbox for your
  information.
- If an API import with **Retrieve times of all users** no longer finds a
  previously imported entry within the queried period, the entry counts as
  deleted in Kimai. WorkDiary then deletes the time as well, unless it has
  been billed; in that case a case is created in the inbox.
- In the CSV route WorkDiary recognises an entry by its time, customer,
  project, activity, description and email. If it was changed in Kimai,
  uploading again creates an additional entry. CSV imports never trigger
  deletions. The API route is better suited to ongoing synchronisation.
- Tags from Kimai are added, never removed.

## Writing times back to Kimai

The **Write times back to Kimai** section appears as soon as API access is
stored and **Enable write-back** is switched on.

- Written back are times recorded in WorkDiary with a start and an end that
  have not been exported yet and whose project is linked to a Kimai project.
  That link is created by the API import – for projects found automatically
  and for API groups you book in the inbox. A CSV import does not provide it.
- WorkDiary never writes times imported from Kimai back.
- Kimai requires an activity for every timesheet: the **Kimai activity ID
  for write-backs** – the number of the activity in Kimai – applies to all
  times written back. Description and billable flag go along.
- **Export to Kimai** starts the write-back after a confirmation, optionally
  for a period. The message lists posted, skipped and failed entries.
- A time written back counts as exported in WorkDiary: it no longer appears
  under **Open times** and is no longer billed here. With **Instant push-back
  of new times** this happens right when the time is recorded, without any
  chance to correct it.

## Writing corrections back

With **Write corrections back**, WorkDiary sends changes to times imported
through the API (description, start, end, duration, billable) and their
deletion to Kimai. Beforehand WorkDiary compares the current state in Kimai:
if the entry was changed there in the meantime, WorkDiary overwrites nothing
and creates a conflict in the inbox instead. Billed times and times imported
via CSV are never written back. The transfer runs in the background and is
retried on errors.

## Common errors

- **No API access configured** instead of the import section: the base URL or
  the token is missing in the plugin settings.
- “No Kimai activity ID configured — write-back not possible.”: enter the
  number of a Kimai activity.
- “No project is mapped to a Kimai project”: run an API import first or book
  the API groups in the inbox.
- Many “Unknown user” cases: the email addresses in Kimai or the email column
  do not match the email addresses in WorkDiary. Map each person once in the inbox.
- Only the token user's times arrive: that user lacks the
  view_other_timesheet permission in Kimai, or **Retrieve times of all
  users** is off.
- The CSV import creates nothing: the header row needs at least a date and
  either an end time or a duration.
- The API cannot be reached: the page shows the error and nothing is
  imported. Enter the address without /api, check the token and use **Test
  connection**. If the instance is on an internal network, WorkDiary reports
  a private address; switch on **Allow private addresses**. If the operator
  has blocked this approval, the instance needs a publicly reachable
  address. If errors pile up, WorkDiary
  deactivates the plugin automatically; once the cause is fixed, reset it on
  the **Plugins** page with **Reset & reactivate**.
