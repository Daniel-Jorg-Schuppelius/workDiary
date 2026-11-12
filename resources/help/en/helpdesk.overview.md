---
title: "Helpdesk & service desk"
topic: helpdesk.overview
version: 5
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

**Changing the status:** Use **Change status** on the ticket to pick the new
status; only permitted transitions are offered. When resolving and closing
you record the **Close code** and the **Resolution summary**. A waiting state
needs a reason, a follow-up date and a responsible person; once the follow-up
is due, WorkDiary reminds that person. Whether the SLA clock stops is decided
by the SLA contract. You reopen a resolved ticket only with a reason; the
previous response and resolution times are kept.

**Queue settings:** For each queue you define the supported ticket types,
business hours, holiday region, sender name and reply address. New tickets
without an assignment go to the default queue; if it does not accept the
ticket type, they go to the first queue that does. The default SLA of the
queue applies if neither the ticket's asset nor the project nor the customer
has a contract of its own.

**Externally led queue:** If a connected system such as Zammad leads the
tickets of a queue, status changes and replies to the customer are locked in
WorkDiary and the ticket shows a notice. Assignment, internal notes, times
and billing remain possible. Once you switch the ticket target of the
integration back, WorkDiary leads the queue again.

**Confidentiality:** A confidential ticket is visible only to the assignee,
watchers, the queue's team and administrators. You set the level when
creating the ticket or in the classification.

**Ordering a service:** Under **Order a service** employees order service
catalog items for themselves. The details from the order form are shown on
the ticket and in the decision dialog of the approval; the ticket also shows
which record fulfils the order.

**Delivery status:** Every reply sent by e-mail shows whether sending is
queued, done or has failed.

**Ticket from an open issue:** On the open issue, **Open ticket** creates a
ticket with the title, description and reference of the issue. At most one
ticket is created per issue; the issue links to it.

**Customer portal:** When the customer confirms the resolution or reopens the
ticket with a reason, this runs like an internal status change — with history
and unchanged response and resolution times. For externally led queues, reply,
acceptance and reopening are locked in the portal as well; there the portal
only shows the state.

**Changing the queue and mailbox:** When a ticket changes its queue, the
default SLA of the new queue applies if its contract so far came from the
queue or the organisation or was missing; a contract of the asset, project
or customer and an explicitly set contract stay. Under **Inbound mailbox** you
assign a mailbox to the queue — one per queue.

**Confidential tickets in lists:** Pick lists and references show a
confidential ticket only to authorised people. In report drill-downs it still
counts but appears to others only as “Confidential ticket”.
