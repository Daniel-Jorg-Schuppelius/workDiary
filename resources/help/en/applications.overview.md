---
title: "Applications & tenders"
topic: applications.overview
version: 2
keywords:
    - recruiting
    - applicant tracking
    - ATS
    - job posting
    - job interview
    - talent pool
    - reject candidate
    - new hire
    - bid management
    - tender submission
    - contract negotiation
    - go no-go decision
audience: []
modules:
    - module.applications
related:
    - documents.manage
---

The module keeps two upstream case files before operational orders or
employee data exist:

**Tenders (company applications):** case file with deadlines, value
potential, go/no-go decision, document checklist and versioned submission
packages (snapshot with SHA-256 hash). Won tenders are transferred to a
project in a controlled way; lost ones remain analysable with their loss
reason.

**Job applications:** staffing need → posting → application case file with
interviews, reviews and decision. Applicant data is stored encrypted and is
only visible to the HR area (recruiting permissions). Rejections start the
deletion reminder automatically (default six months, configurable); the
talent pool requires an explicit, time-limited consent. Acceptances create
an employee draft — a live account is only created by the deliberate invite.
A decision is final: interviews, appointment offers and further decisions
are locked afterwards. Published postings are set to “Expired” every day
once their expiry date or application deadline has passed; they can be
published again with a new date or closed.

A decision also ends open interviews and appointment offers: planned
interviews are marked as cancelled (the note is kept), appointment links not
yet chosen expire immediately. The only exception is the talent pool: as
long as the consent is valid, “Readmit from the talent pool” puts the file
back at the start of the pipeline; the deletion reminder and the consent are
removed, and the deletion deadline is set anew with the next decision.
Without a valid consent the file stays in the talent pool until the deletion
reminder takes effect.
**Contract negotiations:** a separate, versioned step between the win or
acceptance decision and the handover. Open blocker items and missing
approvals (commercial + technical, self-approval blocked) prevent the
conclusion. An approval applies to the version on file: if a new version is
added after an approval level has been granted, approval starts over in a new
round; the earlier round remains visible in the file as history.

The approval steps also appear under “Approvals” for the role the
organization assigns to the step kind — default: commercial → Accounting,
technical → Team Lead, HR → Personnel Administration; this can be changed
when editing the organization in the “Approvals” section. A decision made
there has the same effect as approving on the record; a step can also be
rejected there (with a reason) — a new version then starts the next round.

Legal note: WorkDiary documents the process but does not replace legal
advice.
