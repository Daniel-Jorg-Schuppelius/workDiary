---
title: "Jira task import"
topic: admin.jira
version: 1
keywords:
    - Jira
    - Jira Cloud
    - import issues
    - Atlassian
    - site
    - project mapping
    - preview
    - task import
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

The **Jira** page imports issues of selected Jira Cloud projects as tasks into
your WorkDiary projects. You book time on the work planned in Jira and bill it
as usual. The import only reads: WorkDiary writes nothing to Jira. You find the
page in the system menu (the **System** gear) under **Plugins** → **Jira** once
the plugin is active.

## Prerequisites

- The plugin is activated for your organization: **System** → **Plugins** →
  **Plugins**, then **Activate** on the Jira entry.
- The Kanban module is licensed; the page is open to administrators.
- The operator of your installation has set up the publisher’s Jira app. If it
  is missing, the page shows a notice; you neither need nor use your own app or
  an API token.
- Each organization has exactly one Jira connection and exactly one site.

## Connect and choose the site

1. Click **Connect with Jira** and sign in at Atlassian. WorkDiary only asks
   for read access to projects and issues.
2. Back on the page, choose the Jira site under **Choose Site** and click
   **Apply**. Only sites the sign-in can read are offered.
3. While project mappings exist, the site cannot be changed – imported tasks
   stay clearly tied to their site.

## Map projects and check the preview

1. **Map project** opens a dialog: choose the **Jira project** and the
   **WorkDiary project** the tasks belong to. Each Jira project has exactly one
   target.
2. The **Preview** opens next. It shows the open issues and what the first sync
   would do with them: **Will be created**, **Already linked**, **Different
   target**, **Case to review** or **Managed elsewhere**. The preview saves
   nothing.
3. If the target project already has a matching task, link it instead of
   creating a new one: with the same title via **Link task with same name**,
   otherwise in the section **Link existing task**. Differing fields go to the
   inbox for review; empty fields are filled from Jira.
4. **Confirm import** releases the mapping. Only now does the sync create the
   open issues as tasks. Issues that were done before are never created.

## What is imported

- **Title** from the summary, **description** as plain text (images, tables
  and other content that cannot be shown as text produce a case to review; an
  existing description then stays unchanged), **due date** as a date.
- **Status** via Jira’s status category: to do → **Open**, in progress → **In
  progress**, done → **Done**. The status names in your Jira do not matter.
- Customer, times, rates, budgets and assignees stay managed by WorkDiary.
- A task you completed in WorkDiary is not reopened by Jira. Only a completion
  that came from Jira is reopened with Jira.
- In the task dialog, **Open in Jira** leads to the issue.

## When it syncs

- Every 15 minutes WorkDiary fetches the issues changed since the last run,
  once a day all issues of the mapped projects. Change the schedule under
  **Scheduled jobs**.
- **Sync now** in the ⋯ menu starts the same sync right away.
- If a run is interrupted (for example by a Jira outage), it counts as
  incomplete: the mapping shows **incomplete**, and the next run resumes from
  the last complete state.

## Conflicts and cases to review

- If a field was changed differently in WorkDiary and in Jira, a conflict
  appears in the inbox where you decide which version applies. Nothing is
  written back to Jira.
- Cases to review appear, among others, when an issue is no longer visible in
  Jira (your task stays unchanged, nothing is deleted), when its task lives in
  another WorkDiary project (nothing is moved) or when an unknown status
  arrives.
- If **Data ownership** assigns tasks to another system, WorkDiary imports
  nothing and records a case per issue.
- **Cases to review** in the ⋯ menu opens the inbox filtered to Jira.

## Pause, remove, disconnect

- **Pause** and **Resume** in a mapping’s row stop and restart its sync.
  **Remove mapping** dissolves the mapping; imported tasks, links and times
  remain.
- **Disconnect** deletes the sign-in and pauses all mappings. If you later
  reconnect with the same site, they continue.

## Common errors

- “Jira is not configured for this installation”: the app registration is
  missing; contact the operator.
- No site to choose: the signed-in account has no read access to Jira on any
  site.
- The connection reports a rejected sign-in: the authorization was revoked at
  Atlassian or has expired. Reconnect.
- Nothing is created: the mapping is not confirmed yet, paused, or the issues
  are already done.
