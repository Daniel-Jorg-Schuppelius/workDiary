---
title: "Redmine task and time import"
topic: admin.redmine
version: 1
keywords:
    - Redmine
    - import issues
    - import time entries
    - API key
    - self-hosted
    - user mapping
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

The **Redmine** page imports issues of selected Redmine projects as tasks into
WorkDiary projects and, if desired, the time entries recorded there as time
bookings. The import only reads: WorkDiary writes nothing to Redmine. You find
the page in the system menu (the **System** gear) under **Plugins** →
**Redmine** once the plugin is active.

## Set up access

1. In Redmine, enable the REST web service under **Administration** →
   **Settings** → **API** and copy the **API access key** under **My account**.
2. On the page, open **Credentials** in the ⋯ menu and enter **Base URL** and
   **API key**. For an instance in your own network, also set **Allow private
   addresses**. There you also decide whether imported times are billable
   (**Import times as billable**) and how many days back each run reads (**Time
   window (days)**).
3. Click **Check connection**. While mappings exist, no other instance can be
   entered.

The key's account needs a role in every mapped project that may view issues
and – for the time import – spent time. Set the visibility of issues and time
entries of this role to **all**; otherwise WorkDiary only takes over what the
account created itself or what is assigned to it.

## Map projects and check the preview

1. **Map project** picks a Redmine project and the WorkDiary project.
   Subprojects are not included, neither for issues nor for times; map them
   separately if needed.
2. The **Preview** shows the open issues and what the first sync would do; it
   saves nothing. Link existing tasks via **Link task with same name** or
   **Link existing task**.
3. **Confirm import** releases the mapping. Only then are tasks created from the
   open issues; closed issues never.

## What is imported

- **Title**, **description** as text and **due date**.
- **Status**: an issue with a closed status (marked as closed in Redmine) becomes
  **Done**, otherwise **Open**; status names do not matter. A local **In
  progress** stays.
- In the task dialog, **Open in Redmine** leads to the issue.

## Time entries

1. In the **Time entries** section, switch on **Import times** per mapping.
2. Redmine only knows day and hours. WorkDiary therefore books a duration
   without clock time in the target project; if the entry belongs to a linked
   issue, it belongs to that issue’s task.
3. Times are only booked for mapped Redmine users. Under **User mapping** the
   Redmine users with waiting entries appear; choose the matching user and click
   **Map**. The next sync books the waiting entries.
4. Each run reads the time window completely. Changed hours are updated, entries
   deleted in Redmine disappear here too – except for times already billed:
   there the inbox reports a conflict and the time stays unchanged.

## When it syncs

Every 15 minutes WorkDiary fetches changed issues and the time entries of the
window, once a day all issues. Change the schedule under **Scheduled jobs**;
**Sync now** in the ⋯ menu starts right away.

## Conflicts, cases to review and disconnecting

Different changes on both sides produce a conflict in the inbox; nothing is
written back to Redmine. Issues no longer visible, data managed elsewhere and
unknown statuses are reported as cases to review. **Disconnect** ends the sync
and pauses the mappings; imported tasks and times remain. Revoke the key in
Redmine or delete it under **Credentials**.

## Common errors

- The check fails with 401 or 403: the API key is wrong or the REST web service
  is disabled in Redmine.
- Times do not appear: **Import times** is off, the Redmine user is not mapped
  yet or the entry lies outside the time window.
- The sync stops with 401: Redmine treats an invalid key like a visitor who is
  not signed in and then shows only public data. WorkDiary therefore checks the
  account before each run and reads nothing with an invalid key.
