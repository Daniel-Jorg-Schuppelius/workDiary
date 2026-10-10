---
title: "Importing the FRITZ!Box call list"
topic: admin.fritzbox
version: 2
keywords:
    - FRITZ!Box
    - call list
    - book phone calls
    - record calls as time
    - phone report
    - phone stamping
    - clock in by phone
    - CSV import calls
    - AVM
    - assign phone number
    - bill phone time
audience:
    - admin
related:
    - admin.integrations
    - admin.plugins
    - admin.integration-inbox
    - time-entries.edit
    - attendance.manage
    - contacts.manage
    - foreign-customers
---

The **FRITZ!Box import** page turns phone calls from the call list of a
FRITZ!Box into time entries. WorkDiary books calls of known customers and end
customers on its own; if a call overlaps a time already booked for the same
customer, such as a remote support session, it is merged into that time instead
of being billed twice. Unknown numbers are collected in the **Mapping Inbox**.
In addition, employees can clock in and out by calling one of your own numbers.

WorkDiary does not connect to the FRITZ!Box for this. It reads the exported
call list – as an uploaded file or as a phone report by email. You do not need
any credentials for the box.

## Prerequisites

- The **FRITZ!Box call list** plugin is activated under **Plugins**. After
  that, the **FRITZ!Box import** entry appears in the system menu (gear icon
  **System**) in the **Plugins** group.
- The phone numbers of your customers and end customers are stored in their
  master data (phone or mobile). This is how WorkDiary recognizes the caller.
- The page is open to administrators of your organization; the **Mapping
  Inbox** to people who are allowed to manage billing.

## Plugin settings

Under **Plugins**, open the **Configure** dialog for **FRITZ!Box call list**:

- **Book calls as billable** (default: on): when switched off, imported calls
  are never marked as billable.
- **Book times for user**: the user the calls are booked for; you choose them
  from the list. Without a selection, WorkDiary books to the owner of the
  organization or to the first user.
- **Minimum duration (minutes)** (default: 2): shorter calls are skipped.
- **Lead window (minutes)** (default: 15): if a call ends no more than this
  many minutes before a booked time of the same customer, it is merged into
  that time.
- **Own numbers only**: comma-separated list of your own numbers whose calls
  should be imported, for example only the main company line. Empty imports
  all. Enter the numbers exactly as they appear in the “Eigene Rufnummer” (own
  number) column of the call list.
- **Treat type 3 as outgoing**: only for lists from older FRITZ!OS versions
  that export outgoing calls as type 3.
- **Match external contacts** (default: on): unknown numbers are also matched
  against connected contact directories such as Lexoffice and Microsoft 365.
- **Stamp number: clock in**, **Stamp number: clock out** and **Stamp number:
  clock in/out**: your own phone numbers for phone stamping (see below).

## Uploading the call list

1. Export the call list in the FRITZ!Box: FRITZ!Box → Telephony → Calls → Save
   (CSV).
2. On the page, select the file in the **Upload call list** section
   (extension .csv or .txt, at most 20 MB) and click **Import**.
3. A message summarizes the result: booked, merged, stamped, open (inbox),
   skipped, filtered out and locked.

You can safely upload the same list again: WorkDiary skips calls that were
already imported.

The **Contact matching** box shows which external contact sources are
currently connected. Without an external source, WorkDiary still matches
against your customers and end customers.

## Phone report by email

Instead of uploading the file, the FRITZ!Box can send its call list as a phone
report by email. To use this, set up a mailbox that receives these emails
under **Email intake** and switch on **Phone report mailbox: import FRITZ!Box
call lists (CSV) into the call list import** there. Email intake checks
mailboxes every five minutes by default; recognized call lists go into the
same import as an upload. Reports delivered twice do not lead to duplicate
bookings. When such a mailbox is connected, the plugin's health check reports
“Ready — phone report mail intake connected.”

## What happens to each call

- **Filtered out:** calls with a withheld number, missed and rejected calls,
  calls via own numbers outside **Own numbers only**, and numbers that were
  ignored in the inbox.
- **Skipped:** calls already imported and calls below the minimum duration. If
  you lower the minimum duration, a new import catches up on such calls.
- **Merged:** for a known number, WorkDiary looks for a booked time of the same
  user for the same customer that the call overlaps or that starts no later
  than the lead window after the call. The call is attached to that time as
  evidence, and its start moves forward to the start of the call.
- **Booked:** if there is no such time, a separate time entry is created on the
  default project of the customer or end customer (it is created if needed).
  The description states direction, name and number; whether the entry is
  billable follows the setting.
- **Locked:** if the call falls into a closed month, WorkDiary does not create
  an entry. Times that were already exported are never changed.
- **Open (inbox):** unknown numbers and numbers marked as shared go to the
  **Mapping Inbox**.

When recognizing numbers, remembered numbers take priority, followed by the
master data; if a number matches an end customer, the end customer wins as the
more precise target. If a number in a connected contact directory is already
assigned to a customer, WorkDiary books directly.

## Assigning unknown phone numbers

The **Mapping Inbox** section shows the number of open import groups; **To the
inbox** takes you there. The calls of one number appear there as a group,
often with a suggested customer already:

- Select a customer or end customer and click **Assign & book**. All calls of
  the group are booked by the same rules as during the import. With
  **Remember number permanently** (preselected), future calls from this number
  go through without asking.
- **Shared number** is meant for numbers through which several customers call,
  such as a service provider's hotline. Future calls from this number land in
  the inbox one by one for assignment and are never booked automatically.
- **Ignore number** filters the number out permanently, for example private
  calls; future calls are no longer imported.
- **Dismiss group** dismisses only the calls shown. They do not come back even
  with a new import; new calls from the number appear again.

## Phone stamping

This is how employees clock in and out by phone:

1. In the plugin settings, enter one or more of your own phone numbers as
   **Stamp number: clock in**, **Stamp number: clock out** or **Stamp number:
   clock in/out** – exactly as they appear in the call list. The **Phone
   stamping** section then lists the active stamp numbers.
2. In the **Phone stamping** section, assign each employee their phone number:
   select the **Employee**, enter the **Phone number** (for example +49 151
   2345678) and click **Assign**. Without a country code, Germany applies. The
   table shows all assignments; **Remove** deletes one.
3. The employee calls the stamp number. The call does not have to be answered –
   the caller's number serves as the ID.
4. With the next import of the call list, the call becomes a clock-in or
   clock-out stamp at the time of the call. For **clock in/out**: if a stamp is
   open, the employee clocks out, otherwise in.

Limits: stamping only happens during the import, not at the moment of the
call. Outgoing calls, withheld and unassigned numbers are ignored, as is a
clock-out without an open clock-in. The minimum duration does not apply here.
Calls to a stamp number are never booked as a phone call.

## Typical problems

- **File rejected:** if the import reports that no FRITZ!Box call list was
  recognized (empty file or missing header row), use the CSV export of the call
  list unchanged.
- **“No bookable user in the organization.”** or a health check reporting that
  the configured default user no longer exists: check **Book times for user**
  or clear the selection.
- **Outgoing calls missing:** if the list comes from older firmware, switch on
  **Treat type 3 as outgoing**.
- **Almost everything filtered out:** check **Own numbers only** – the spelling
  must match the call list exactly.
- **Many locked results:** the month is already closed; calls in this period
  are no longer booked.
- **Stamp missing:** is the employee's phone number assigned, and was it
  transmitted during the call? Does the stamp number in the settings match the
  call list?
