---
title: "Helpdesk & service desk"
topic: helpdesk.overview
version: 1
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

**Satisfaction & reports:** After closure, the customer can leave a short
rating in the portal — one per ticket. Reports show volume per queue,
response and resolution times, SLA compliance, waiting reasons, change
outcomes, problem backlog and catalogue demand. Rankings of individual
agents are deliberately omitted.
