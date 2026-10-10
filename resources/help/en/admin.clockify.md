---
title: "Clockify import"
topic: admin.clockify
version: 3
keywords:
    - Clockify
    - import time entries
    - detailed report
    - Clockify CSV
    - Clockify API
    - switch from Clockify
    - transfer times
    - remote support to Clockify
    - user mapping
    - write corrections back
    - hourly import
audience:
    - admin
related:
    - admin.integrations
    - admin.plugins
    - admin.integration-inbox
    - admin.kimai
    - admin.toggl
    - admin.scheduler
    - admin.organization-settings
---

The **Clockify import** page brings time entries from Clockify into WorkDiary
– as an uploaded detailed report (CSV) or directly through the Clockify API.
If you wish, it also transfers times recorded in WorkDiary to Clockify, such
as remote support sessions, and sends corrections to imported times back. You
find the page in the system menu (the **System** gear in the header) under
**Plugins** → **Clockify import** once the plugin is active.

## Prerequisites

- The plugin is activated for your organization: **System** → **Plugins** →
  **Plugins**, then **Activate** on the Clockify entry. Activation and
  settings apply to the current organization only.
- The page and the settings are open to administrators. The **Mapping
  Inbox**, where you resolve open cases, is also open to accounting.
- For the CSV route, a detailed report from Clockify is all you need.
- For the API route you need an API key (in Clockify under Profile → Advanced
  → API). The free Clockify plan allows only 30 API requests per hour; there
  the CSV route is the recommended one.

## Setup

You store the credentials on the **Plugins** page via **Configure** on the
Clockify entry:

1. **Clockify API key**: the key from Clockify. It is stored encrypted; an
   empty field keeps the previous value when you save.
2. **Workspace ID**: optional. If empty, WorkDiary uses the default workspace
   of the API key.
3. **API base URL** and **Reports API base URL**: only change these if your
   account is hosted on a regional Clockify instance; the help text in the
   dialog gives an example.
4. **Sync window (days)**: how far back an API import without a period – the
   hourly one included – looks and how far the hourly transfer reaches
   (default 30 days).
5. **Adopt billable flag**: on takes over the billable flag from Clockify;
   off never marks imported times as billable.
6. **Single-user mode** and **Book times for user**: for single
   workstations only, see below. You choose the user from the list.
7. Optionally **Enable time transfer**, **Write corrections back** and
   **Webhook secret** (see “Webhook”).
8. **Save**. Use **Test connection** in the dialog to check the access.
   Without an API key the plugin reports CSV mode – that is not an error.

## Importing times

**Upload CSV:** In Clockify, export the detailed report as CSV (Clockify →
Reports → Detailed → Export → CSV), select the file on the **Clockify
import** page and click **Import**. WorkDiary recognises the columns by the
header row – Project, Client, Description, Task, Email, Tags, Billable, Start
Date, Start Time, End Date, End Time and Duration (h) or Duration (decimal).
Columns you do not need may be missing; Start Date and either an end time or
a duration are required. Both comma and semicolon work, and the file may be
at most 20 MB.

**Import directly from the Clockify API:** Optionally choose a period
(**From**, **To**) and click **Import from API**. WorkDiary fetches the time
entries of all users in the workspace; without a period, the last days
according to the sync window. The import skips running entries that have no
end yet.

**Hourly import:** Once an API key is stored, the API import also runs by
itself every hour – over the sync window and with deletion matching (see
below). You change the interval under **Scheduled tasks** on the “Clockify
import” entry. Each run uses requests from the quota of your Clockify plan.
Without an API key there is no automatic import; you always upload CSV files
here.

If the Clockify API reports an error, the page shows it and nothing is
imported. After an import on this page, the page reports how many entries were created, skipped and left
open in the inbox, and how many could not be matched to a user.

## Matching customers, projects and people

- **Projects:** The import does not create customers or projects. An entry is
  booked when its project is found: through a remembered mapping, otherwise
  through the same project name at the matching customer. If **Assign time to
  projects by keyword** is switched on in the organization settings, an
  unambiguous keyword match helps as a last step.
- **Mapping Inbox:** Everything else collects there, grouped by client,
  project and task. The **Mapping Inbox** card on the page shows the number
  of open groups, and **To the inbox** takes you there. Choose the customer,
  optionally the end customer, and the project, then book the group. The
  mapping is remembered, so later imports book without asking.
- **People:** Every time belongs to the person who recorded it in Clockify.
  WorkDiary compares that person's email address (Email column or the value
  from the API) with the email address of the active users. Without a match,
  an “Unknown user” or “Entry without user signal” case is created in the
  inbox instead of the time silently landing with the main user. Choose the
  user there; the choice is remembered.
- **Single-user mode:** Only when it is switched on does the import book
  entries without an identifiable person to the default user. That is the
  user from **Book times for user**, otherwise the owner of the
  organization or the first user.

## Re-importing and changes

- A repeated import never creates already imported entries twice.
- In the API import WorkDiary recognises each entry by its Clockify ID. If a
  known entry changed in Clockify (start, end, duration, description),
  WorkDiary takes over the change. If the time has already been billed or
  exported here, WorkDiary changes nothing; the case appears in the inbox for
  your information.
- If an API import no longer finds a previously imported or transferred
  entry within the queried period, the entry counts as deleted in Clockify.
  WorkDiary then deletes the time as well, unless it has been billed; in that
  case a case is created in the inbox.
- In the CSV route WorkDiary recognises an entry by its time, client,
  project, task, description and email. If it was changed in Clockify,
  uploading again creates an additional entry. CSV imports never trigger
  deletions.
- Tags from Clockify are added, never removed.

## Transferring times to Clockify

With **Enable time transfer**, WorkDiary mirrors working times recorded in
WorkDiary with a start and an end to Clockify:

- Only times in projects linked to a Clockify project are transferred. A
  project counts as linked once you have booked a Clockify group onto it in
  the Mapping Inbox and a project with the same client and name exists in
  Clockify. Projects the import found by name alone do not count.
- Entries are always created for the owner of the API key – Clockify does not
  allow anything else.
- New times go out right after they are recorded. In addition, an hourly run
  catches up on anything still missing within the sync window. With
  **Transfer to Clockify** in the **Transfer times to Clockify** section you
  start the transfer by hand, optionally for a period.
- Unlike a write-back, the time remains billable in WorkDiary. Afterwards it
  behaves like an imported one: changes and deletions are synchronised in
  both directions.
- Times already transferred and times imported from Clockify are skipped.

## Writing corrections back

With **Write corrections back**, WorkDiary sends changes to times imported or
transferred through the API (description, start, end, duration, billable) and
their deletion to Clockify. Beforehand WorkDiary compares the current state in
Clockify: if the entry was changed there in the meantime, WorkDiary
overwrites nothing and creates a conflict in the inbox instead. Billed times
and times imported via CSV are never written back.

## Webhook

With a paid Clockify plan, Clockify can notify WorkDiary about new and
changed entries; the import then starts by itself:

1. The **Clockify import** page shows, in the **Webhook (optional)** section,
   the address Clockify should call.
2. In Clockify, create a webhook pointing to this address under workspace
   settings → Webhooks.
3. Enter its signature token in the plugin settings under **Webhook secret**
   and also set the **Workspace ID** there.

Many events in quick succession trigger only one import. Without a webhook
secret the webhook stays off. The hourly fetch remains the reliable source:
it catches up on whatever a failed webhook missed.

## Common errors

- **No API key configured** instead of the import section: the API key is
  missing in the plugin settings.
- The message mentions the free plan with 30 requests per hour: the quota is
  used up. Import via CSV or wait; the next run continues an interrupted
  transfer.
- “Clockify: no workspace could be determined”: enter the **Workspace ID**.
- “No project is mapped to a Clockify project”: first book Clockify groups
  onto the desired projects in the inbox.
- Many “Unknown user” cases: the email addresses in Clockify differ from
  those in WorkDiary. Map each person once in the inbox.
- Wrong dates in the CSV import: dates in slash format where both day and
  month are 12 or less are read as month/day. Set an unambiguous date format
  in Clockify.
- If errors pile up, WorkDiary deactivates the plugin automatically; once the
  cause is fixed, reset it on the **Plugins** page with **Reset &
  reactivate**.
