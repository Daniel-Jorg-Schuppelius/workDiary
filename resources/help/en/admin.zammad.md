---
title: "Zammad connection"
topic: admin.zammad
version: 3
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
    - time booking to the ticket
    - mapped groups only
    - service tickets
    - ticket target
    - private addresses
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
Optionally WorkDiary reports completed tasks back to the ticket and books
recorded times to the ticket. You find the
page in the system menu (the **System** gear in the header) under **Plugins**
→ **Zammad** once the plugin is active.

## Prerequisites

- The plugin is activated for your organization: **System** → **Plugins** →
  **Plugins**, then **Activate** on the Zammad entry. You set up the
  connection itself on the **Zammad** page, not in the plugin dialog.
- The page is open to administrators.
- You need the address of your Zammad instance and an API token (in Zammad
  under Profile → Token Access). The token must be able to read the tickets
  and, if you use the status return or the time booking, also change them.
- The instance must be publicly reachable. If Zammad runs on your own
  network, switch on **Allow private/internal addresses** (see below).
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
  secret when you save. Once the connection is saved, the **Webhook
  address** appears below the field: enter it in Zammad under Webhook as the
  endpoint, with the secret as HMAC SHA1 signature token, and fire the
  webhook from a trigger. Without a webhook, the regular polling fetches the
  tickets.
- **Default project**: target for tickets whose group is not mapped to a
  project. **— no project (global) —** creates them as global tasks without
  a project.
- **Status return (target state)**: optional, see below.
- **Time booking to the ticket**: optional, see below.
- **Allow private/internal addresses**: switch it on only if Zammad runs on
  your own network (for example 192.168.x.x). Without this switch WorkDiary
  rejects internal addresses as soon as you save. Switching it on is
  audited. If the operator of your installation has blocked this approval,
  the switch has no effect.
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

**Mapped groups only** (off by default) limits the import: when it is on,
WorkDiary only creates tasks for tickets of the groups mapped here; the
**Default project** then no longer applies. When it is off, all tickets the
API token can see arrive. With the switch on and not a single mapping,
WorkDiary imports nothing.

## Import and schedule

- Every 15 minutes WorkDiary asks Zammad for tickets. The interval can be
  changed under **Scheduled tasks**.
- **Import now** starts an import in the background.
- With a webhook secret, a webhook from Zammad also triggers the import
  immediately. If it fails, the regular polling catches up.
- WorkDiary fetches all tickets the API token can see; with **Mapped groups
  only**, tasks are only created from the mapped groups. Every run reads the
  complete ticket list, page by page.
- Every open ticket becomes a task exactly once. Its title is the ticket
  number and ticket title, and it is billable. Closed or merged tickets that
  WorkDiary does not know yet are not imported retroactively.
- If an already linked ticket is closed or merged in Zammad, WorkDiary sets
  the task to **Done** – without reporting back to the ticket. If you reopen
  the task afterwards, it stays open. A ticket reopened in Zammad does not
  change the task.
- WorkDiary does not take over changes to the ticket title or group.
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
An empty field switches the return off. Apart from the state, the note and –
with the time booking – times, WorkDiary writes nothing to Zammad.

## Time booking to the ticket

Under **Time booking to the ticket**, choose the unit in which your Zammad
records time: **Minutes** or **Hours**, matching the time accounting unit in
Zammad. When someone records a time in WorkDiary on a linked task, WorkDiary
books it to the ticket as time accounting; in hours rounded to two decimal
places. Each time is booked at most once. The transfer runs in the
background and is retried on errors. WorkDiary does not transfer times that
are changed or deleted later. **Off** switches the time booking off.

## Ticket target

In the **Ticket target** section, **Current** shows how new tickets arrive:
as **Tasks** (default) or as **Service tickets** of a queue. To switch,
choose the target under **New tickets as**, for service tickets also the
**Queue**, and click **Switch target**; WorkDiary asks for confirmation
first.

- Service tickets require the Helpdesk module. You create the queue under
  **Service desk** → **Queues**. Zammad then leads the tickets of this queue.
- Tickets already imported stay where they are.
- If there are open data ownership conflicts in the Mapping Inbox, WorkDiary
  refuses the switch until they are resolved.
- Every switch is audited.
- Service tickets get no customer suggestion, no status return and no time
  booking; these apply to tasks only.

## Limits

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
- “The instance URL points to a private/internal address.”: if Zammad runs on
  your own network, switch on **Allow private/internal addresses**. If the
  operator has blocked this approval, the instance needs a publicly reachable
  address.
- **Health failing** with “Zammad API unreachable or token invalid.”: check
  the address and token. A Zammad API error with RuntimeException often
  points to an address on an internal network without approval.
- “Please choose a queue.” on **Switch target**: the queue for service
  tickets is missing.
- Tickets land in the wrong project: check the group IDs under **Queue →
  project** and the customer suggestions in the inbox.
