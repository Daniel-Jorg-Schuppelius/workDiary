---
title: "Helpdesk & service desk"
topic: helpdesk.overview
version: 3
keywords:
    - ticket system
    - create ticket
    - incident
    - support request
    - service request
    - support queue
    - SLA
    - response time
    - internal note
    - ticket routing
    - customer satisfaction
    - change approval
    - ITSM
audience: []
related:
    - open-issues
    - customer-portal.overview
---

The helpdesk bundles incidents and service requests as tickets — each with a
number, title, priority, status, customer, optional asset reference and an
assigned person.

**Queues:** Tickets are managed in queues (areas of responsibility), each
with a responsible team and an optional SLA contract. Exactly one queue is
the default queue for newly arriving tickets; changing it is a controlled
step. A queue can only be deleted once no tickets are assigned to it —
nothing is silently reassigned.

**Priorities & SLA:** The SLA contract defines response and resolution
deadlines per priority. Running deadlines are visible on the ticket; if a
deadline passes without the first response or the resolution happening in
time, this is recorded as a breach and feeds into the SLA evaluation.

**Public vs. internal:** Replies to the customer and internal notes are two
separate actions with different permissions. A public reply is visible to
the customer and can be sent by email to recipients; an internal note stays
strictly within the team. The separation is enforced technically — an
accidental publication of internal remarks is impossible.

**Intake:** Tickets are created manually, by email (replies to an existing
ticket are automatically attached to the case), via the customer portal,
from open issues, from maintenance plans or through the API. The source
remains recorded on the ticket.

**Routing:** Rules distribute incoming tickets automatically — for example
into a queue, with a priority or an assignee — and are applied in a defined
order. A test mode checks a rule against a sample ticket and logs the
result without changing anything.

**Approvals:** Open approval steps of service requests, changes and
contract negotiations are collected under “Approvals” — per item only the
next open step, and only for those responsible: a person, a role or, for
contract negotiations, the role the organization assigns to the step kind
(default: commercial → Accounting, technical → Team Lead, HR → Personnel
Administration). Approving, rejecting, asking a question and delegating
have the same effect as on the record; a rejection ends the round, and
self-approval stays blocked.

**Business services and service map:** A ticket can carry a business service
(such as "email" or "accounting server") and a service offering from the
service catalogue; tickets can then be filtered, routed and reported by them.
The service map shows how business services depend on each other: for each
service, what it depends on, who needs it and who would be affected by an
outage — together with the open tickets of those services. Dependencies are
maintained on the business service (multiple selection); a cycle is rejected.
On the ticket, the "Impact" card shows which other business services an
outage of the reported service would hit and how many tickets are open there —
so an incident can be prioritised before the follow-up reports arrive.

**Satisfaction & reports:** After closure, the customer can leave a short
rating in the portal — one per ticket. Reports show volume per queue,
response and resolution times, SLA compliance, waiting reasons, change
outcomes, problem backlog and catalogue demand. Rankings of individual
agents are deliberately omitted.
