---
title: "Task imports from open-source tools"
topic: admin.oss-task-imports
version: 1
keywords:
    - Vikunja
    - Kanboard
    - Plane
    - Taiga
    - Leantime
    - FreeScout
    - GLPI
    - task import
    - import tickets
    - self-hosted
    - API token
    - case to review
audience:
    - admin
related:
    - admin.plugins
    - admin.integration-inbox
    - admin.data-ownership
    - projects.manage
    - admin.scheduler
---

Seven plugins take over items from widely used open-source tools as tasks in
WorkDiary projects: **Vikunja**, **Kanboard**, **Plane**, **Taiga** and
**Leantime** for project planning, **FreeScout** and **GLPI** for support and
tickets. You then book time on the work that is planned or requested there. Each
import only reads: WorkDiary writes nothing to the source system. You find the
pages in the system menu (the **System** gear) under **Plugins** once the
respective plugin is active. All seven work the same way; this text names the
differences per system.

## Set up access

1. Create an access with read permission in the source system (see table).
2. On the plugin page, open **Credentials** in the ⋯ menu and enter the
   **address** and the access. If the instance runs in your own network, also
   set **Allow private addresses** – this only takes effect if the operator
   permits it.
3. Click **Check connection**. WorkDiary signs in and shows the instance, the
   address and – where the system reports it – the account.

| System | Address | Access |
| --- | --- | --- |
| Vikunja | Address of the instance | API token from Settings → API Tokens |
| Kanboard | Address of the instance | Application token from Settings → API (username empty) or username and personal access token |
| Plane | https://api.plane.so or your own instance | Workspace slug and personal access token |
| Taiga | https://api.taiga.io or your own instance | Username and password |
| Leantime | Address of the instance | API key from the company settings |
| FreeScout | Address of the instance | API key of the “API & Webhooks” module |
| GLPI | Address of the instance | User token (“remote access key”), app token if required |

As long as mappings exist, no other instance can be entered; this keeps the
imported tasks clearly assigned to their instance. For Plane, the workspace
counts as the instance.

## Requirements per system

- **Vikunja** from version 1.0. The API token needs read rights for projects
  and tasks; with the right “other → user” the check also shows the account
  name.
- **Kanboard** offers active projects only; inactive ones do not appear.
- **Plane** from version 0.28. Only projects in which the token's account is a
  member are offered. Plane allows 60 requests per minute, so the first sync of
  large projects takes a few minutes.
- **Taiga**: The account needs the right to view user stories in the project.
  Without it, Taiga returns an empty list; WorkDiary then reports the run as
  incomplete instead of treating tasks as deleted.
- **Leantime** from version 3.8.
- **FreeScout**: Only published conversations are taken over, no drafts and no
  deleted ones.
- **GLPI**: The REST API must be enabled, and the account needs read rights on
  the tickets of the entity. WorkDiary ends the session after each run.

## Map sources and check the preview

The source is a project in each system, a **mailbox** in FreeScout and an
**entity** in GLPI (without sub-entities). In the dialog, mailboxes and
entities appear in the list of projects.

1. **Map project** opens a dialog with the sources the access can see and the
   WorkDiary projects. Each source has exactly one target.
2. The **preview** shows the open items and what the first sync would do with
   them. It saves nothing.
3. You link existing tasks of the target project via **Link task with same name** or
   **Link existing task**; differing fields go to the inbox for review.
4. **Confirm import** releases the mapping. Only then does the sync create the
   open items as tasks; items that are already done never.

## What is taken over

**Title**, **description** as text and, where available, the **due date** are
taken over. The status always comes from a fixed field of the system, never from
freely named column or status names:

| System | Open | In progress | Done | Due date |
| --- | --- | --- | --- | --- |
| Vikunja | not done | – | done | due date |
| Kanboard | active | – | closed | due date |
| Plane | group Backlog, Unstarted, Triage | group Started | group Completed, Cancelled | target date |
| Taiga | open | – | closed | due date |
| Leantime | status type “New” | status type “In progress” | status type “Done” | due date |
| FreeScout | active | pending | closed, spam | – |
| GLPI | new, approval | assigned, planned, pending | solved, closed | time to resolve |

- If a task is **In progress** in WorkDiary, it stays that way as long as the
  source does not close it.
- In FreeScout, the description is the first message of the conversation;
  internal notes are left out. In Taiga, WorkDiary reads the description only
  for new or changed user stories.
- If Taiga closes a user story because all its tasks are done, this arrives
  with the next sync. If a new task reopens it, WorkDiary only sees this in the
  daily sync of all items.
- Milestones from Leantime are not taken over.
- In the task dialog, **Open in …** leads to the original.

## When syncing happens

Every 15 minutes WorkDiary fetches the items changed since the last run, and all
of them once a day. You change the schedule under **Scheduled jobs**; **Sync
now** in the ⋯ menu starts immediately. An aborted run counts as
**incomplete**; the next one resumes from the last complete state.

## Conflicts and cases to review

Different changes on both sides result in a conflict in the inbox; nothing is
written back to the source system. If a linked item is no longer visible, your
task stays unchanged and the inbox reports a case to review. If, according to
**data ownership**, another system leads the tasks, WorkDiary takes over
nothing. **Cases to review** in the ⋯ menu opens the inbox filtered to the
plugin.

## Pause, remove, disconnect

**Pause** and **Resume** stop and restart the sync of a mapping; **Remove
mapping** keeps imported tasks and times. **Disconnect** ends the sync and
pauses all mappings. You revoke the access in the source system or delete it
under **Credentials**; checking again with the same instance resumes the
mappings.

## Typical errors

- The check reports an internal address: the address points into your own
  network, but **Allow private addresses** is off or not permitted by the
  operator.
- The check fails with 401: the token, key or password is wrong, expired or has
  no read access.
- The check reports “…VersionUnsupportedException”: the instance is older than
  the minimum version named above.
- Plane does not show a project: the token's account is not a member there,
  even if the project is public.
- Kanboard shows only some projects: with a username, the access only sees that
  user’s projects; use the application token without a username for all
  projects.
- Taiga rejects the sign-in: accounts that sign in via GitHub or GitLab need
  their own password.
- Leantime or GLPI shows no source: the API key is not assigned to any project,
  or the REST API is not enabled in GLPI.
- FreeScout answers with 404: the “API & Webhooks” module is not active.
