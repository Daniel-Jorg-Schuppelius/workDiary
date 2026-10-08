---
title: "Connecting Google Calendar"
topic: admin.google-calendar
version: 1
keywords:
    - Google Calendar
    - Google calendar sync
    - events to Google
    - synchronize calendar
    - Google Workspace
    - calendar sync
    - two-way calendar
    - export appointments
    - publish calendar
    - Google Cloud Console
audience:
    - admin
related:
    - admin.integrations
    - admin.plugins
    - admin.msgraph
    - events.manage
    - admin.integration-inbox
    - admin.notification-rules
    - admin.import
    - admin.scheduler
---

The **Google Calendar** page transfers events from WorkDiary into a calendar of
a Google account. WorkDiary stays in charge: changes are carried over,
cancelled events disappear from the Google calendar, and repeated runs do not
create duplicates. If you wish, WorkDiary also reads the calendar back and
presents external changes as proposals for review.

## Prerequisites

- The **Google Calendar** plugin is activated under **Plugins**. After that,
  the **Google Calendar** entry appears in the system menu (gear icon
  **System**) in the **Plugins** group.
- There is an OAuth client in the Google Cloud Console. Either the operator has
  stored one for the whole installation, or your organization uses its own:
  under **Plugins**, open the **Configure** dialog for **Google Calendar** and
  enter **Client ID (own Google Cloud app)** and **Client secret**. An own
  client must know your WorkDiary address with the path
  /admin/google-calendar/oauth/callback as an authorized redirect URI.
- Google classifies calendar access as sensitive. The app therefore needs
  verification by Google, or you set the consent screen type to “Internal” in
  Google Workspace.
- If no OAuth client is available, the page shows a notice instead of the
  connect button.
- You need a Google account with write access to the target calendar. The
  connection may edit events and read the calendar list.
- The page is open to administrators of your organization. Each organization
  has one connection.

## Connecting

1. Click **Connect to Google**. The Google sign-in opens; sign in and allow the
   access.
2. Google returns you to the page. The message “Google account connected.”
   confirms the connection; next to the title you see the **Connected** badge.

The same person who started the process must finish it in the same session.

## Choosing the target calendar

In the **Target calendar** section, select one of the calendars of the
connected account under **Calendar**. Without a selection, the **Primary
calendar** applies. There you can also switch on **Two-way: import external
changes as inbox proposals** if needed. Then click **Save**. If you switch the
calendar, the import starts from scratch.

## What is transferred and when

- **Content:** events from 30 days back to 180 days ahead, with title,
  description, time and location (booked rooms). Cancelled events are removed
  from the Google calendar.
- **Timing:** a sync runs daily, by default at 4:55 a.m.; you change the
  interval under **Scheduled tasks**. **Publish now** starts it immediately in
  the background.
- **Notifications:** notifications with a due date are sent right away as
  calendar entries if a notification rule uses the **Calendar** channel.
- **Without two-way sync**, WorkDiary does not read any events from the Google
  calendar.

## Two-way import

With two-way sync switched on, WorkDiary reads the target calendar back every
hour. This only creates entries in the **Mapping Inbox**, never events created
on their own:

- A new event that does not come from WorkDiary becomes a proposal.
- A transferred event that was changed in Google becomes a conflict –
  otherwise the next sync would overwrite the change silently.
- A transferred event that was deleted or cancelled in Google appears as
  “Appointment deleted in Google Calendar”.
- Recurring events appear as individual events within the import period and
  can be accepted or dismissed as a group.

The **Mapping Inbox** is open to people who are allowed to manage billing.
Independently of two-way sync, the import of clock entries and project times
offers the connected calendar as a source.

## Disconnecting and reconnecting

**Disconnect** removes the access. Events already transferred remain in the
Google calendar. With **Connect to Google** you can restore the connection at
any time; the selected calendar stays saved, and the error count starts over.

## Typical problems

- **No connect button:** no OAuth client is stored (see prerequisites).
- **“The OAuth flow has expired or is invalid. Please start again.”** The
  sign-in took too long or was completed in a different session. Start the
  connection again.
- **“The connection was declined or cancelled.”** Consent was refused, or
  Google does not allow the app for this account – for example because it has
  not been verified yet. Check the consent screen in the Google Cloud Console.
- **Badge Unreachable:** the Google Calendar API cannot be reached or denies
  access, for example after the access was revoked in the Google account.
  **Disconnect** and connect again.
- **“The selected calendar was not found.”** The calendar was deleted, or the
  account lost access. Select a different one.
- **Shut down:** after repeated consecutive errors, WorkDiary shuts the
  connection down; **Connect to Google** then appears again. Check the cause
  and connect again.
