---
title: "Todoist connection"
topic: admin.todoist
version: 2
keywords:
    - Todoist
    - sync tasks
    - task synchronisation
    - connect Todoist
    - project mapping
    - preflight
    - sections
    - map assignees
    - kanban
    - task conflict
audience:
    - admin
related:
    - admin.integrations
    - admin.plugins
    - admin.integration-inbox
    - work.overview
    - projects.manage
    - admin.scheduler
---

The **Todoist** page synchronises tasks between WorkDiary and Todoist. Only
Todoist projects that you explicitly map to a WorkDiary project or to the
global kanban are synchronised; conflicts go to the integration inbox, and
nothing is overwritten or deleted silently. You find the page in the system
menu (the **System** gear in the header) under **Plugins** → **Todoist** once
the plugin is active.

## Prerequisites

- The plugin is activated for your organization: **System** → **Plugins** →
  **Plugins**, then **Activate** on the Todoist entry.
- The page is open to administrators.
- A registered Todoist app is required. Either the operator of your
  installation has stored one, or you enter your own: on the **Plugins** page
  via **Configure** on the Todoist entry, in the fields **Client ID (own
  Todoist app)** and **Client secret**. If empty, the installation's app
  applies. Your own app must register, as its redirect URI in Todoist, the
  address of your WorkDiary installation with the path
  `/admin/todoist/oauth/callback`.
- There is exactly one Todoist connection, and therefore one Todoist account,
  per organization.

## Connecting to Todoist

1. Open the **Todoist** page. The **Connection** section states beforehand
   which data is transferred: titles, descriptions, status, due dates and
   assignees of the mapped tasks. WorkDiary does not request delete
   permissions.
2. Click **Connect to Todoist** and sign in to Todoist. After you grant
   access you return to the page.
3. The page then shows **Status**, **Account**, **Connected since** and
   **Last sync**. **Renew connection** signs you in again, **Disconnect** ends
   the connection; mappings and links are kept.

## Mapping projects

With an active connection the **Project mappings** table appears. Below the
table you create a new mapping:

1. **Todoist project**: a selection from your Todoist account.
2. **Target**: **WorkDiary project** (then choose the project; the list shows
   at most 500 projects) or **Global kanban** for tasks without a project.
3. **Direction**: **Todoist → WorkDiary**, **WorkDiary → Todoist** or
   **Bidirectional**.
4. **Map**. Every new mapping starts as **Draft** and does not synchronise
   anything yet.

In the table you open the **Preflight** for each mapping, switch it with
**Activate** or **Pause**, and remove it with the bin icon (references are
kept). The **Last run** column shows the time and counters: created,
updated, unchanged and conflicts.

## Preflight: assignees and sections

Before activation, the **Preflight** shows what the synchronisation will
find:

- **Counters**: active tasks, subtasks, recurring tasks, due dates with a
  time, assignees that cannot be mapped and tasks already linked. Recurring
  tasks arrive as a single task with the next due date; only Todoist knows
  the recurrence. For due dates with a time, WorkDiary takes over the date
  only.
- **Assignee mapping**: for each Todoist collaborator you choose a WorkDiary
  user and click **Save**. WorkDiary shows a matching email address only as a
  **Suggestion**; the assignment takes effect only once you choose. Without a
  mapping a task stays without an assignee.
- **Sections → status**: for each Todoist section you choose **Open** or
  **In progress**. Unmapped sections leave the status untouched.

Only then do you switch the mapping live with **Activate**.

## What is synchronised

- **Todoist → WorkDiary**: every active Todoist task becomes a WorkDiary task
  in the target project or in the global kanban. Synchronised are title,
  description, priority (Todoist p1 to p4 correspond to Urgent, High, Medium,
  Low), due date, duration as time budget, assignee and status. Completed in
  Todoist means **Done** here. Subtasks stay under their parent task.
- **WorkDiary → Todoist**: new tasks created after activation in the mapped
  project or in the global kanban are created in Todoist by WorkDiary.
  Changes to linked tasks go across as well; a status change moves the task
  to the mapped section or completes or reopens it. Tasks that already
  existed before activation are not transferred.
- **Bidirectional** combines both directions.
- For linked tasks the task dialog shows the link **Open in Todoist**.

## When synchronisation runs

- Every hour WorkDiary fetches the changes since the last run from Todoist.
  The interval can be changed under **Scheduled tasks**.
- **Sync now** starts a full synchronisation in the background. Only this run
  also notices tasks that disappeared from the Todoist project without being
  deleted, for example because they were moved.
- Changes from WorkDiary go across right away through a queue and are retried
  on errors.
- If you use your own Todoist app, you can additionally enter a webhook there
  pointing to the address of your installation with the path
  `/api/webhooks/todoist`. It triggers a targeted synchronisation on changes;
  the hourly run remains the reliable source.

## Conflicts and deletions

- WorkDiary compares every field with its state at the last synchronisation.
  If a field was changed differently on both sides, a conflict is created in
  the inbox; there you decide which state applies. Until then WorkDiary does
  not transfer that field.
- WorkDiary does not pass on deletions in either direction. If a task
  disappears in Todoist or a linked task is deleted here, a case is created
  in the inbox.
- A subtask whose parent task is missing here also ends up in the inbox.
- If a task was completed in WorkDiary, reopening it in Todoist does not
  reset it.

**Integration inbox** on the page opens the Mapping Inbox filtered to
Todoist.

## Common errors

- “Todoist is not configured”: no Todoist app is stored – neither by the
  operator nor in the plugin settings.
- “Invalid or expired OAuth state”: the sign-in took too long or ran in
  another session. Connect again.
- “Token exchange failed”: the client ID, client secret or redirect URI of
  your own app are wrong.
- The list of Todoist projects is empty: the connection cannot reach Todoist.
  Check the status on the **Plugins** page and renew the connection.
- Tasks arrive without an assignee: the Todoist collaborator has not yet been
  mapped to a user in the preflight.
- Nothing is synchronised: the mapping is still **Draft** or **Paused**.
- The connection shows **Paused**: Todoist rejected the access, for example
  because the app authorisation was revoked in Todoist. Synchronisation is
  paused; reconnect with **Renew connection**. If a sync fails for another
  reason, the page shows the last error until a sync succeeds again; an
  operations task is created as well.
