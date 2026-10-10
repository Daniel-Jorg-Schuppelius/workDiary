---
title: "Connecting Microsoft 365"
topic: admin.msgraph
version: 2
keywords:
    - Microsoft 365
    - Office 365
    - Outlook calendar
    - send mail via Microsoft
    - Outlook contacts
    - Microsoft To Do
    - OneNote
    - Teams meeting
    - admin consent
    - Entra ID
    - out of office reply
    - Exchange Online
audience:
    - admin
related:
    - admin.integrations
    - admin.plugins
    - admin.sharepoint
    - admin.google-calendar
    - events.manage
    - admin.integration-inbox
    - admin.notification-rules
    - knowledge.collections
    - cloud-intake.overview
    - backup-targets.overview
---

The **Microsoft 365** page bundles the connections to Microsoft 365 via
Microsoft Graph: calendar, email sending, contacts to Outlook, Microsoft To Do
and the import from OneNote, plus the tenant-wide grant. Each feature has its
own connection with its own sign-in and only the permissions it needs – you
connect only what you actually use. Each connection applies to the whole
organization and works with the Microsoft account that consents during
sign-in.

## Prerequisites

- The **Microsoft 365** plugin is activated under **Plugins**. After that, the
  **Microsoft 365** entry appears in the system menu (gear icon **System**) in
  the **Plugins** group.
- There is an app registration in Microsoft Entra ID. Either the operator has
  stored an app for the whole installation, or your organization uses its own:
  under **Plugins**, open the **Configure** dialog for **Microsoft 365** and
  enter **Client ID (own app registration)**, **Client secret** and **Tenant
  (directory ID)**. The tenant is the GUID of your directory or one of the
  values common, organizations or consumers; if empty, the value of the
  installation app applies.
- If no app is available, the page shows a notice and the connect buttons are
  missing.
- You need a Microsoft account that is allowed to consent to the permissions.
  The page is open to administrators of your organization.

## Calendar

At the top of the page you connect the calendar with **Connect to Microsoft
365**. Afterwards, **Publish now** and **Disconnect** are available there; next
to the title a badge shows **Connected**, **Unreachable** or **Inactive**.

- **Direction:** events from WorkDiary are transferred to the calendar of the
  connected account – from 30 days back to 180 days ahead, with title,
  description, time and location (booked rooms). Changes are carried over,
  cancelled and deleted events are removed there, and repeated runs do not create
  duplicates. WorkDiary stays in charge.
- **Timing:** a sync runs daily, by default at 4:45 a.m.; you change the
  interval under **Scheduled tasks**. **Publish now** starts it immediately in
  the background. In addition, notifications with a due date are sent right
  away as calendar entries if a notification rule uses the **Calendar**
  channel.
- **Target calendar:** with an active connection, select a calendar of the
  account under **Calendar** in the section of the same name; without a
  selection, the **Default calendar** applies. Then click **Save**.
- **Create new events as Teams meetings (join link):** newly transferred
  events get a Teams join link. The option does not change events that were
  already transferred.
- **Two-way: import external changes as inbox proposals:** the target calendar
  is read back every hour, and Microsoft additionally reports changes right
  away. New external events become proposals, changes to transferred events
  become conflicts, and deleted events appear as “Appointment deleted in
  Microsoft 365” – all in the **Mapping Inbox**, never as an event created
  blindly. Recurring events appear as individual events and can be accepted or
  dismissed there as a group. If you switch the target calendar, the import
  starts from scratch.

The calendar connection is also used by:

- **Check availability (Microsoft 365)** in the dialog of an event: shows free
  or busy for the selected participants, without event details.
- the import of clock entries and project times, which offers the connected
  calendar as a source.
- the **Team (Teams status)** box on the **Time clock** page. It only appears if
  the installation has enabled read access to the Teams status and the calendar
  connection was established again afterwards.

## Email sending via Microsoft 365

With **Connect mail sending**, an account allows WorkDiary to send emails on
its behalf – such as invoices, reminders and notifications, without SMTP
access. Afterwards you see the **Connected account** and set:

- **Sender address (optional)**: if empty, the account sends as itself. A
  different address, such as a shared mailbox, needs the “Send As” right in
  Exchange and an additional permission that the operator enables for the app.
- **Save a copy to the Sent Items folder**.
- **Save**.

**Send test email** immediately sends a message through this connection, to
**Recipient (optional)** or, if left empty, to the connected account. The test
uses the same sender address as real sending, so missing send rights show up
immediately. Whether WorkDiary actually sends its emails through this
connection is decided by the operator of the installation; if this is not set
up, the card shows a notice. **Disconnect mail sending** removes the access.

## Pushing contacts to Outlook

After **Connect contact push**, the **To Outlook** button appears on the detail
page of a customer. It transfers the customer as a contact into Outlook of the
connected account: name, contact person, company, email, phone, mobile number,
website and address. Transferring again updates the contact instead of
duplicating it; if it was deleted in Outlook, WorkDiary creates it again.
Transfers only happen at the push of the button and only in this direction;
this requires the right to edit the customer.

The Outlook contacts of this account also serve as a contact directory when
matching unknown phone numbers, for example in the FRITZ!Box import.

## Syncing Microsoft To Do

1. Click **Connect To Do sync**.
2. Create a link: select the **To Do list**, choose a **Project** or the
   **Global kanban** as the **Target**, select the **Project** if the target is
   a project, set the **Direction** (**Both directions**, **To Do → WorkDiary
   only** or **WorkDiary → To Do only**) and click **Link**.
3. The table shows all links. **Remove** deletes one; tasks already synced are
   kept.

Each To Do list can be linked once; a new link of the same list replaces the
old one. Title, description, status (open, in progress, done), priority and
due date are synchronized. The sync runs every hour; changes in WorkDiary are
also sent right away, and Microsoft reports changes to importing lists right
away. If both sides changed the same task, a conflict is created in the
**Mapping Inbox** – the last change does not simply win. WorkDiary never
transfers deletions; tasks deleted in To Do are only marked. This sync does not
know subtasks, assignees or sections.

## Importing from OneNote

1. Under **Plugins**, switch on the **Allow OneNote import** option in the
   **Configure** dialog for **Microsoft 365**. Until then the card shows
   **Switched off**, and the connection does not request access to notebooks.
2. Click **Connect OneNote**. The access is read-only.
3. **Go to “Knowledge”** leads to the import: there, **Import OneNote** brings
   in a notebook once or on demand as notes or knowledge articles. The notebook
   becomes a collection, its sections become sub-collections. There is no
   writing back and no ongoing sync.

## Entra app and tenant-wide grant

If a policy of your Microsoft tenant prevents users from consenting
themselves, an Entra administrator grants the permissions once for the whole
organization: **Grant for organization (admin consent)**. The sign-in requires
an Entra administrator role in the target tenant. The grant covers calendar,
email sending, contacts, tasks and document intake, and with the OneNote
import switched on also reading the notebooks. After that, users connect
without being asked for consent themselves.

**Redirect URIs for a custom app registration** lists the addresses that an
own app must register as redirect URIs of type “Web”: for calendar, email
sending, contacts, tasks, OneNote, document intake, admin consent, – only for
the installation app – the backup target, and **SharePoint storage**, which
uses the same app unless the operator assigns it its own.

## Further plugin features

- **Set Outlook automatic replies for approved vacation** (in the plugin
  settings, off by default): as soon as a vacation is finally approved,
  WorkDiary sets the automatic reply in the person's mailbox. For this, the app
  needs the application permission MailboxSettings.ReadWrite with admin
  consent. Errors do not hold up the approval.
- The **Cloud document intake** and the **Cloud backup targets** use their own
  Microsoft connections, which you set up on those pages.

## Disconnecting and reconnecting

Each card has its own disconnect action. It removes the access keys of that
connection; transferred events and Outlook contacts remain at Microsoft. You
can reconnect at any time; WorkDiary also resets the error count when you do.
If a connection was shut down after repeated consecutive errors, the connect
button appears again. As long as a calendar connection is failing, an
operations task is listed for it.

## Typical problems

- **No connect buttons:** the app registration is missing (see
  prerequisites).
- **“The OAuth flow has expired or is invalid. Please start again.”** The
  sign-in took too long or was completed in a different session. The person
  who connects must finish the process themselves.
- **“The connection was declined or cancelled.”** Consent was refused. If the
  account may not consent itself, use the admin consent.
- **Badge Unreachable:** Microsoft Graph cannot be reached or denies access.
  Check the account and connect again.
- **“Test send failed: …”** With a different sender address, the “Send As”
  right is often missing.
- **“The selected To Do list is no longer available.”** The list was deleted
  in To Do or does not belong to the connected account.
- **Notice about side connections:** if the health check under **Plugins**
  reports that Microsoft 365 side connections need attention, a connection for
  document intake, backup or email sending is disrupted. Sign in there again.
