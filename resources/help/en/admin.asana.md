---
title: "Asana task import"
topic: admin.asana
version: 1
keywords:
    - Asana
    - import tasks
    - workspace
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

The **Asana** page imports tasks of selected Asana projects into your WorkDiary
projects. You book time on the work planned in Asana and bill it as usual. The
import only reads: WorkDiary writes nothing to Asana. You find the page in the
system menu (the **System** gear) under **Plugins** → **Asana** once the plugin
is active.

## Prerequisites

- The plugin is activated for your organization: **System** → **Plugins** →
  **Plugins**, then **Activate** on the Asana entry.
- The Kanban module is licensed; the page is open to administrators.
- The operator of your installation has set up the publisher’s Asana app. If it
  is missing, the page shows a notice; a personal access token is neither needed
  nor used.
- Each organization has exactly one Asana connection and exactly one workspace.

## Connect and choose the workspace

1. Click **Connect with Asana** and sign in at Asana. WorkDiary only asks for
   read access to tasks, projects, workspaces and your own account.
2. Back on the page, choose the workspace under **Choose Workspace** and click
   **Apply**.
3. While project mappings exist, the workspace cannot be changed.

## Map projects and check the preview

1. **Map project** opens a dialog: choose the **Asana project** and the
   **WorkDiary project** the tasks belong to.
2. The **Preview** shows the open tasks and what the first sync would do with
   them. It saves nothing.
3. Link existing tasks of the target project via **Link task with same name**
   or the section **Link existing task**. Differing fields go to the inbox for
   review.
4. **Confirm import** releases the mapping. Only now does the sync create the
   open tasks. Completed tasks are never created.

## What is imported

- **Title**, **notes** as description and **due date** as a date. If a task in
  Asana has a due time, the date counts in your organization’s time zone.
- **Status**: completed in Asana → **Done**, otherwise **Open**. If the task is
  **In progress** in WorkDiary, it stays that way – Asana has no progress state.
- A task in several mapped projects with the same target is created only once.
  If the projects point to different targets, WorkDiary does not create it and
  reports a case to review.
- A task you completed in WorkDiary is not reopened by Asana.
- In the task dialog, **Open in Asana** leads to the task.

## When it syncs

- Every 15 minutes WorkDiary reads the open tasks and those completed since the
  last run, once a day all tasks. Change the schedule under **Scheduled jobs**;
  **Sync now** in the ⋯ menu starts right away.
- If a run is interrupted, it counts as incomplete, and the next run resumes
  from the last complete state.

## Conflicts and cases to review

- Different changes on both sides produce a conflict in the inbox; nothing is
  written back to Asana.
- If a linked task is no longer visible in Asana, your task stays unchanged and
  the inbox reports a case to review.
- If **Data ownership** assigns tasks to another system, WorkDiary imports
  nothing.
- **Cases to review** in the ⋯ menu opens the inbox filtered to Asana.

## Pause, remove, disconnect

- **Pause** and **Resume** stop and restart a mapping’s sync; **Remove
  mapping** keeps imported tasks and times.
- **Disconnect** revokes the authorization at Asana, deletes the sign-in and
  pauses all mappings. Reconnected with the same workspace, they continue.

## Common errors

- “Asana is not configured for this installation”: the app registration is
  missing; contact the operator.
- The connection reports a rejected sign-in: the authorization was withdrawn
  in Asana. Reconnect.
- Nothing is created: the mapping is not confirmed yet, paused, or the tasks
  are already completed.
