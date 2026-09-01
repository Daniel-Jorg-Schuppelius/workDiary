---
title: "Applications & tenders"
topic: applications.overview
version: 1
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

**Contract negotiations:** a separate, versioned step between the win or
acceptance decision and the handover. Open blocker items and missing
approvals (commercial + technical, self-approval blocked) prevent the
conclusion.

Legal note: WorkDiary documents the process but does not replace legal
advice.
