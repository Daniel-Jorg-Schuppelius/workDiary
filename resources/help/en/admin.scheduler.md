---
title: "Scheduled jobs"
topic: admin.scheduler
version: 1
audience:
    - admin
related:
    - admin.diagnostics
    - admin.operations
---

This page lists all recurring background jobs of the platform – from
housekeeping and integration synchronization to deadline escalations.

**Registry instead of sprawl:** All schedulable jobs come from a
central registry with a fixed **default plan**. Only registered jobs
appear here and can be controlled – you deliberately cannot schedule
arbitrary commands through this page.

**Overview:** For each job you see the effective plan including its
**origin** (default, setting, or manual reschedule), the last run
with its result, an error counter and the next due time. This lets
you spot at a glance whether a job is stuck or failing persistently.

**Filtering and sorting:** Above the table you narrow the list by
name, key or command, by criticality, last result and plan source –
for example to all failed jobs, jobs that never ran or jobs moved by
the operating window. The **Paused only** switch shows what is
currently on hold. The columns Job, Last run, Next due and Consecutive
failures sort with a click; jobs without a value (never ran, paused)
always stay at the end.

**Rescheduling with guard rails:** Each job defines which cadences
are allowed for it (e.g. hourly or daily at a given time).
Rescheduling is only possible within these allowed cadences – so a
critical job cannot accidentally be put on an unsuitable rhythm.
Free cron expressions remain an operator-level function. **Reset**
returns a job to its default plan at any time.

**Operating window:** If the server does not run around the clock –
for example because it shuts down at night – set the start and end of
the operating window under **Settings** (an end of 00:00 means
midnight). Jobs with a fixed time outside the window then run
together within the first two operating hours, in their original
order and never earlier than planned. The overview shows the
effective time and notes which time a job was moved from; the
watchdog checks against that time. Hourly and shorter intervals stay
unchanged.

**Pausing and test runs:** Jobs can be paused and resumed later – a
paused job no longer becomes due but stays visible in the overview.
A **test run** starts the job immediately, out of schedule; a short
cooldown applies between test runs so executions do not overlap.

**Run records:** Every run is recorded with start time, duration and
result. Records are kept for a configurable period (30 days by
default) and cleaned up automatically afterwards.

**Watchdog:** A dedicated monitoring job checks the scheduler itself:
if due runs stop happening or errors accumulate, it raises operations
tasks and alerts. This way even a completely stalled scheduler gets
noticed – not only once reports start missing.

**Recommendation:** Change plans conservatively and watch the next
runs after every reschedule. A persistently elevated error counter is
a case for diagnostics, not for pausing the job.
