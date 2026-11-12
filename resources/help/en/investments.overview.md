---
title: "Investment planning"
topic: investments.overview
version: 4
keywords:
    - capital expenditure
    - CapEx
    - investment request
    - budget request
    - investment approval
    - compare quotes
    - budget overrun
    - budget variance
    - plan vs actual
    - business case
    - post-investment review
    - lessons learned
audience: []
modules:
    - module.investments
related:
    - documents.manage
---

The investment case file tracks an investment from idea to post-review:
reason, objective/benefit, risk, option comparison with supplier offers
(one-time and recurring cost, delivery time, quality, recommendation),
versioned budget request and approval chain.

**Approvals:** Below the threshold a commercial approval suffices; from
the threshold upwards (configurable, default €10,000) the four-eyes
principle applies with an additional management step. The requester can
never approve their own request. Approved budgets are frozen as a
snapshot — an increase always requires an approved budget deviation and
a supplement (new request, the old state remains as "superseded").

**Approval inbox:** Open approval steps of a budget request appear under
“Approvals” and in the work queue for the role your organisation assigns
to the step type (default: commercial → accounting, management →
management). On submission and when the next step is due, the people
responsible are notified. Decisions there work as on the record; only
people allowed to approve investments may approve.

**Deciding on the record:** The same responsibility applies as in the
inbox. “Release” and “Reject” are shown only to the person who is
responsible for the open step and may decide it — not to the requester and
not to whoever decided an earlier step of the same round. Everyone else sees
what the request is waiting for, for example “Waiting for step 2:
Management (role Management).” Admins, too, decide only with the role of
the step; you set which role that is in the organisation settings under
“Approvals”.

**Deferring & rejection:** During planning the case can be parked with
"Defer"; "Resume" returns it to exactly the phase it was in. The
rejection of a budget request is final: a rejected case stays rejected
and can neither be deferred nor submitted again — a new attempt starts
with a new case.

**Implementation & actuals:** After approval, projects, purchase orders,
assets, incoming invoices or documents are linked. The plan/actual view
shows the approved budget, committed funds and actual costs as a
projection — the leading modules stay leading; WorkDiary does not
replace accounting.

**Post-review:** After completion or cancellation, the actual benefit,
economics, lessons learned and follow-up actions are documented.

**Useful life and supplier:** Each option takes a useful life in years; it
appears in the option comparison and prefills the transfer to fixed assets.
When an asset is linked to the file or the file is completed, the asset takes
the supplier of the recommended option as “Acquired from”, unless one is
already set.

**Filtering the list:** The investment list also filters by period, responsible
person, cost centre, budget status (state of the most recent request), urgency
and risk note. The report can also be exported as a PDF.
