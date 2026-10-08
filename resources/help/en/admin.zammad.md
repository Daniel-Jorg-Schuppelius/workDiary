---
title: "Zammad connection"
topic: admin.zammad
version: 1
keywords:
    - Zammad
    - helpdesk
    - import tickets
    - ticket system
    - tickets as tasks
    - map queue
    - group ID
    - webhook
    - close ticket
    - report status back
audience:
    - admin
related:
    - admin.integrations
    - admin.plugins
    - admin.integration-inbox
    - helpdesk.overview
    - admin.data-ownership
    - admin.scheduler
    - projects.manage
---

The **Zammad** page brings tickets from the Zammad ticket system into
WorkDiary as tasks, so that you can record time, keep records and bill there.
Zammad remains the leading system; importing again never creates duplicates.
Optionally WorkDiary reports completed tasks back to the ticket. You find the
page in the system menu (the **System** gear in the header) under **Plugins**
→ **Zammad** once the plugin is active.

## Prerequisites

- The plugin is activated for your organization: **System** → **Plugins** →
  **Plugins**, then **Activate** on the Zammad entry. You set up the
  connection itself on the **Zammad** page, not in the plugin dialog.
- The page is open to administrators.
- You need the address of your Zammad instance and an API token (in Zammad
  under Profile → Token Access). The token must be able to read the tickets
  and, if you use the status return, also change them.
- The instance must be publicly reachable. WorkDiary rejects addresses on an
  internal network.
- There is exactly one Zammad connection per organization.

## Setting up the connection

In the **Connection** section you fill in:

- **Label**: a name of your choice.
- **Instance URL**: the address at which you open Zammad in the browser. It
  must start with http:// or https://.
- **API token**: required the first time you save. It is stored encrypted;
  later, an empty field keeps the stored token.
- **Webhook secret (optional)**: a shared secret for webhook calls from
  Zammad that trigger the import immediately. An empty field keeps the stored
  secret when you save. The page does not show the webhook
  address; without a webhook, the regular polling fetches the tickets.
- **Default project**: target for tickets whose group is not mapped to a
  project. **— no project (global) —** creates them as global tasks without
  a project.
- **Status return (target state)**: optional, see below.
- **Active**: switches the connection on or off.

**Save** applies your entries. When the connection is active, the page shows
its health (for example **Health ok**) and **Test connection**.

## Queue → project

Under **Queue → project** you map Zammad groups to a WorkDiary project: the
**Group ID** from Zammad on the left, the project on the right. There are
always three empty rows; for more groups, save and then enter them. WorkDiary
discards rows without a group ID or without a project when saving. The
project selection shows at most 500 projects.

A ticket lands in the project of its group, otherwise in the **Default
project**, otherwise as a global task.

## Import and schedule

- Every 15 minutes WorkDiary asks Zammad for tickets. The interval can be
  changed under **Scheduled tasks**.
- **Import now** starts an import in the background.
- With a webhook secret, a webhook from Zammad also triggers the import
  immediately. If it fails, the regular polling catches up.
- WorkDiary fetches the tickets the API token can see – not only the mapped
  groups.
- Every ticket becomes a task exactly once. Its title is the ticket number
  and ticket title, and it is billable. Closed or merged tickets arrive as
  completed tasks.
- WorkDiary does not take over later changes to the ticket (title, status,
  group); the task stays as it was created.
- If, according to **Data ownership**, another system leads the tasks,
  WorkDiary creates no task but a case in the Mapping Inbox.

## Recognising customers

If a ticket contains a customer email or an organization, WorkDiary looks for
the matching customer. With an unambiguous match it moves the task to a
project of that customer, preferably that customer's default project.
Otherwise a suggestion is created in the Mapping Inbox, where you confirm or
choose the customer.

## Status return

Enter a Zammad state under **Status return (target state)**, for example
closed. When someone sets a linked task in WorkDiary to **Done**, WorkDiary
sets the ticket to that state and adds an internal note “Resolved in
WorkDiary.”. The transfer runs in the background and is retried on errors.
An empty field switches the return off. WorkDiary writes no other data to
Zammad.

## Limits

- One run only fetches the first page of the ticket list, at most 100
  tickets.
- **Disconnect** only switches the connection off. Tasks and links are kept,
  and nothing changes in Zammad. To switch it on again, set **Active** and
  save.
- Tasks are never deleted, even if the ticket disappears in Zammad.

## Common errors

- “The instance URL must start with http:// or https://.”: enter the full
  address.
- “A new connection requires an API token.”: the token is missing the first
  time you save.
- “No active Zammad connection.” on **Import now**: the connection is off or
  incomplete.
- **Health failing** with “Zammad API unreachable or token invalid.”: check
  the address and token. A Zammad API error with RuntimeException often
  points to an address on an internal network.
- Tickets land in the wrong project: check the group IDs under **Queue →
  project** and the customer suggestions in the inbox.
