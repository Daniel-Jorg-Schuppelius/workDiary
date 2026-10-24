---
title: "Gitea/Forgejo task import"
topic: admin.gitea
version: 1
keywords:
    - Gitea
    - Forgejo
    - import issues
    - repository
    - self-hosted
    - access token
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

The **Gitea/Forgejo** page imports issues of selected repositories of your
self-hosted Gitea or Forgejo instance as tasks into WorkDiary projects. You book
time on the work planned there. The import only reads: WorkDiary writes nothing
to the instance. You find the page in the system menu (the **System** gear)
under **Plugins** → **Gitea/Forgejo** once the plugin is active.

## Set up access

1. In Gitea or Forgejo, create an access token under **Settings** →
   **Applications** with the scopes **read:user**, **read:repository** and
   **read:issue**. A token that applies only to selected repositories also sees
   only those in the dialog.
2. On the page, open **Credentials** in the ⋯ menu and enter the **Base URL**
   (e.g. https://git.example.com, without /api/v1) and the **Access token**. If
   the instance runs in your own network, also set **Allow private addresses** –
   it only takes effect if the operator permits it.
3. Click **Check connection**. WorkDiary signs in to the instance and shows
   instance, address and account.

While mappings exist, no other instance can be entered; imported tasks stay
clearly tied to their instance.

## Map repositories and check the preview

1. **Map project** opens a dialog with the repositories the token can access
   and the WorkDiary projects. Each repository has exactly one target.
2. The **Preview** shows the open issues and what the first sync would do with
   them. It saves nothing.
3. Link existing tasks of the target project via **Link task with same name**
   or **Link existing task**; differing fields go to the inbox for review.
4. **Confirm import** releases the mapping. Only then does the sync create the
   open issues as tasks; closed issues are never created.

## What is imported

- **Title**, **description** as text and **due date** as a date as the
  instance shows it.
- **Status**: closed → **Done**, open → **Open**. If a task is **In progress**
  in WorkDiary, it stays that way.
- Pull requests are not imported. If a repository is renamed, the import keeps
  working.
- In the task dialog, **Open in Gitea/Forgejo** leads to the issue.

## When it syncs

Every 15 minutes WorkDiary fetches the issues changed since the last run, once a
day all issues. Change the schedule under **Scheduled jobs**; **Sync now** in
the ⋯ menu starts right away. An interrupted run counts as **incomplete**; the
next one resumes from the last complete state.

## Conflicts and cases to review

Different changes on both sides produce a conflict in the inbox; nothing is
written back to the instance. If a linked issue is no longer visible, your task
stays unchanged and the inbox reports a case to review. If **Data ownership**
assigns tasks to another system, WorkDiary imports nothing. **Cases to review**
in the ⋯ menu opens the inbox filtered to Gitea/Forgejo.

## Pause, remove, disconnect

**Pause** and **Resume** stop and restart a mapping’s sync; **Remove mapping**
keeps imported tasks and times. **Disconnect** ends the sync and pauses all
mappings. Revoke the token in Gitea/Forgejo or delete it under **Credentials**;
checking the same instance again resumes the mappings.

## Common errors

- The check reports an internal address: the base URL points into your own
  network, but **Allow private addresses** is off or not permitted by the
  operator.
- The check fails with 401: the token is wrong, expired or lacks read access.
- A repository is missing in the dialog: the token cannot see it, for example
  because it applies only to selected repositories.
- The check fails with 403: the token lacks one of the scopes **read:user**,
  **read:repository** or **read:issue**.
